<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminCompanyWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public User $owner,
        public string $loginUrl,
        public string $resetUrl,
        public int $expireMinutes,
        public ?string $plainPassword = null,
        public bool $isTest = false,
    ) {}

    public function envelope(): Envelope
    {
        $brand = (string) config('flowdesk.brand_name', 'ClicFlow');
        $subject = $this->isTest
            ? '[TEST] '.$brand.' — e-mail de bienvenue (exemple)'
            : __('admin_welcome_mail_subject', ['brand' => $brand, 'company' => $this->company->name]);

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.admin-company-welcome');
    }
}
