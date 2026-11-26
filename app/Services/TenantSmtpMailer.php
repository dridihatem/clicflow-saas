<?php

namespace App\Services;

use App\Jobs\SendTenantMailJob;
use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Sends mail using each company's workspace SMTP when it is complete and working.
 * Otherwise falls back to the platform ClicFlow mailer (MAIL_* from the server .env):
 * From = platform address with the company name as display name,
 * Reply-To = the company contact email.
 *
 * Queued mailables (ShouldQueue) are never pushed with a pre-built mailer: a
 * SendTenantMailJob carrying only the company id, the mailable and the recipient is
 * queued, and the SMTP / fallback decision is made inside the worker at send time.
 * SMTP credentials are therefore never serialized into the queue payload.
 */
class TenantSmtpMailer
{
    /** When > 0, send() delivers immediately even for ShouldQueue mailables. */
    private static int $syncDepth = 0;

    /**
     * Run $callback with every send() delivered synchronously (used inside jobs
     * that need to know whether the email was really sent).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function synchronously(callable $callback): mixed
    {
        self::$syncDepth++;
        try {
            return $callback();
        } finally {
            self::$syncDepth--;
        }
    }

    public static function isSynchronous(): bool
    {
        return self::$syncDepth > 0;
    }

    /**
     * @return array<string, mixed>|null Complete SMTP settings, or null when missing/incomplete.
     */
    public function smtpFor(Company $company): ?array
    {
        $settings = CompanySetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $smtp = $settings?->smtp;
        if (! is_array($smtp)) {
            return null;
        }

        $host = trim((string) ($smtp['host'] ?? ''));
        $port = (int) ($smtp['port'] ?? 0);
        if ($host === '' || $port <= 0) {
            return null;
        }

        return $smtp;
    }

    public function hasSmtp(Company $company): bool
    {
        return $this->smtpFor($company) !== null;
    }

    public function companyReplyTo(Company $company): ?string
    {
        foreach ([$company->contact_email ?? null, $company->email ?? null] as $candidate) {
            if (is_string($candidate)) {
                $email = trim($candidate);
                if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return $email;
                }
            }
        }

        return null;
    }

    /**
     * Apply platform From (company display name) and Reply-To (company email).
     */
    public function applyPlatformIdentity(Mailable $mailable, Company $company): void
    {
        $fromAddress = trim((string) config('mail.from.address'));
        $fromName = trim((string) $company->name);
        if ($fromName === '') {
            $fromName = (string) config('mail.from.name', 'ClicFlow');
        }

        // Replace (not append) any From/Reply-To set by a failed SMTP attempt.
        $mailable->from = [];
        $mailable->replyTo = [];

        if ($fromAddress !== '') {
            $mailable->from($fromAddress, $fromName);
        }

        $replyTo = $this->companyReplyTo($company);
        if ($replyTo !== null) {
            $mailable->replyTo($replyTo, $fromName);
        }
    }

    /**
     * Send with company SMTP when complete; on missing, incomplete, or failed SMTP,
     * send via the platform mailer unless $requireSmtp is true.
     *
     * @throws RuntimeException|Throwable
     */
    public function send(Company $company, Mailable $mailable, string $toEmail, bool $requireSmtp = false): void
    {
        $toEmail = trim($toEmail);
        if ($toEmail === '' || ! filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(__('Client has no email address.'));
        }

        if ($mailable instanceof ShouldQueue && ! $requireSmtp && ! self::isSynchronous()
            && config('queue.default') !== 'sync') {
            SendTenantMailJob::dispatch((string) $company->id, $mailable, $toEmail);

            return;
        }

        $this->deliver($company, $mailable, $toEmail, $requireSmtp);
    }

    /**
     * Send right now (bypassing ShouldQueue): company SMTP when complete, platform
     * mailer on missing/incomplete/failed SMTP (unless $requireSmtp).
     *
     * @throws RuntimeException|Throwable
     */
    public function deliver(Company $company, Mailable $mailable, string $toEmail, bool $requireSmtp = false): void
    {
        $toEmail = trim($toEmail);
        $smtp = $this->smtpFor($company);

        if ($smtp === null) {
            if ($requireSmtp) {
                throw new RuntimeException(__('tenant_smtp_required'));
            }

            $this->sendViaPlatform($company, $mailable, $toEmail, 'missing_or_incomplete');

            return;
        }

        $enc = $smtp['encryption'] ?? 'tls';
        if ($enc === 'null' || $enc === '') {
            $enc = null;
        }

        $fromAddress = $smtp['from_address'] ?? config('mail.from.address');
        $fromName = $smtp['from_name'] ?? ($company->name ?: config('mail.from.name'));

        if (is_string($fromAddress) && $fromAddress !== '') {
            $mailable->from($fromAddress, is_string($fromName) ? $fromName : null);
        }

        $replyTo = $this->companyReplyTo($company);
        if ($replyTo !== null && empty($mailable->replyTo)) {
            $mailable->replyTo($replyTo, is_string($fromName) ? $fromName : $company->name);
        }

        try {
            $mailer = Mail::build([
                'transport' => 'smtp',
                'host' => (string) $smtp['host'],
                'port' => (int) ($smtp['port'] ?? 587),
                'encryption' => $enc,
                'username' => $smtp['username'] ?? null,
                'password' => $smtp['password'] ?? null,
                'timeout' => 30,
            ]);

            $mailer->to($toEmail)->sendNow($mailable);
        } catch (Throwable $e) {
            Log::warning('tenant_smtp_send_failed', [
                'company_id' => $company->id,
                'to' => $toEmail,
                'host' => $smtp['host'] ?? null,
                'message' => $e->getMessage(),
            ]);

            if ($requireSmtp) {
                throw new RuntimeException(__('tenant_smtp_send_failed'), 0, $e);
            }

            $this->sendViaPlatform($company, $mailable, $toEmail, 'smtp_failed', $e);
        }
    }

    private function sendViaPlatform(
        Company $company,
        Mailable $mailable,
        string $toEmail,
        string $reason,
        ?Throwable $previous = null,
    ): void {
        $this->applyPlatformIdentity($mailable, $company);

        Log::info('tenant_smtp_platform_fallback', [
            'company_id' => $company->id,
            'to' => $toEmail,
            'reason' => $reason,
            'previous' => $previous?->getMessage(),
        ]);

        Mail::to($toEmail)->sendNow($mailable);
    }
}
