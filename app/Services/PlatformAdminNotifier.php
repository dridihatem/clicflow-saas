<?php

namespace App\Services;

use App\Mail\CheckoutAdminNotifyMail;
use App\Models\Admin;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class PlatformAdminNotifier
{
    /**
     * @return list<string>
     */
    public function adminEmails(): array
    {
        $emails = Admin::query()
            ->whereNotNull('email')
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $configured = config('flowdesk.platform_admin_email');
        if (is_string($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $configured;
        }

        $inbox = config('flowdesk.contact_inbox_email');
        if (is_string($inbox) && filter_var($inbox, FILTER_VALIDATE_EMAIL)) {
            $emails[] = $inbox;
        }

        return array_values(array_unique(array_filter($emails)));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function notifyCheckoutRequest(
        string $checkoutType,
        string $customerName,
        string $customerEmail,
        ?string $customerMessage,
        array $context = [],
    ): void {
        $recipients = $this->adminEmails();
        if ($recipients === []) {
            Log::warning('checkout_admin_notify_no_recipients', [
                'type' => $checkoutType,
                'email' => $customerEmail,
            ]);

            return;
        }

        try {
            Mail::to($recipients)->send(new CheckoutAdminNotifyMail(
                $checkoutType,
                $customerName,
                $customerEmail,
                $customerMessage,
                $context,
            ));
        } catch (Throwable $e) {
            Log::warning('checkout_admin_notify_mail_failed', [
                'type' => $checkoutType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
