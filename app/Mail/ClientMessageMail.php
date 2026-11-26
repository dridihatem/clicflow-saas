<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ClientMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Client $client,
        public string $emailSubject,
        public string $emailBody,
        public string $senderName,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->emailSubject)
            ->view('emails.client-message');
    }
}
