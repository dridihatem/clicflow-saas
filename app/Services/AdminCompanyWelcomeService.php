<?php

namespace App\Services;

use App\Mail\AdminCompanyWelcomeMail;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Throwable;

class AdminCompanyWelcomeService
{
    public function expireMinutes(): int
    {
        $minutes = (int) config('flowdesk.admin_welcome_password_expire_minutes', 10080);

        return $minutes > 0 ? $minutes : 10080;
    }

    public function resetUrl(Company $company, User $user): string
    {
        $token = Password::broker('admin_welcome')->createToken($user);
        $path = route('password.reset', ['token' => $token], false);

        return flowdesk_tenant_url($company, $path).'?email='.urlencode($user->email);
    }

    public function loginUrl(Company $company): string
    {
        return flowdesk_tenant_url($company, '/login');
    }

    /**
     * @return array{sent: bool, error: ?string}
     */
    public function send(Company $company, User $owner, ?string $plainPassword = null, bool $isTest = false, ?string $to = null): array
    {
        $loginUrl = $this->loginUrl($company);
        // A TEST send must not create a real password-reset token on any user.
        $resetUrl = $isTest
            ? 'https://exemple.clicflow.fr/reset-password/jeton-exemple?email='.urlencode($to ?: $owner->email)
            : $this->resetUrl($company, $owner);

        $mailable = new AdminCompanyWelcomeMail(
            company: $company,
            owner: $owner,
            loginUrl: $loginUrl,
            resetUrl: $resetUrl,
            expireMinutes: $this->expireMinutes(),
            plainPassword: $plainPassword,
            isTest: $isTest,
        );

        $recipient = $to ?: $owner->email;

        try {
            // Welcome mail is rare and must actually go out: send synchronously
            // even if a queue is configured, so a stopped worker cannot drop it.
            Mail::to($recipient)->send($mailable);

            return ['sent' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::error('Admin company welcome email failed', [
                'company_id' => $company->id,
                'user_id' => $owner->id,
                'to' => $recipient,
                'message' => $e->getMessage(),
            ]);

            return ['sent' => false, 'error' => $e->getMessage()];
        }
    }
}
