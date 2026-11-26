<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\TenantSmtpMailer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Delivers one tenant email from the queue. Only the company id, the mailable and the
 * recipient are serialized; the company SMTP (or the platform fallback) is resolved
 * here, at send time, so no SMTP password ever lands in the queue payload.
 */
class SendTenantMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public string $companyId,
        public Mailable $mailable,
        public string $toEmail,
    ) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(TenantSmtpMailer $mailer): void
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->companyId);
        if ($company === null) {
            Log::warning('tenant_mail_company_missing', [
                'company_id' => $this->companyId,
                'mailable' => $this->mailable::class,
            ]);

            return;
        }

        $mailer->deliver($company, $this->mailable, $this->toEmail);
    }

    public function failed(?Throwable $e): void
    {
        Log::warning('tenant_mail_job_failed', [
            'company_id' => $this->companyId,
            'mailable' => $this->mailable::class,
            'to' => $this->toEmail,
            'message' => $e?->getMessage(),
        ]);
    }
}
