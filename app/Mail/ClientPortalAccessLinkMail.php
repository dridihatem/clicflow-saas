<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Portal access email with a set-password link (no plaintext password). */
class ClientPortalAccessLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Client $client,
        public Company $company,
        public string $email,
        public string $setPasswordUrl,
        public string $loginUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('client_access_link_mail_subject', ['company' => $this->company->name]))
            ->view('emails.client-portal-access-link');
    }
}
