<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckoutAdminNotifyMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $checkoutType,
        public string $customerName,
        public string $customerEmail,
        public ?string $customerMessage,
        public array $context = [],
    ) {}

    public function build(): self
    {
        $subject = $this->checkoutType === 'billing'
            ? __('checkout_admin_notify_mail_subject_billing', ['number' => $this->context['order_number'] ?? ''])
            : __('checkout_admin_notify_mail_subject_marketplace', ['number' => $this->context['order_number'] ?? '']);

        return $this
            ->subject($subject)
            ->view('emails.checkout-admin-notify');
    }
}
