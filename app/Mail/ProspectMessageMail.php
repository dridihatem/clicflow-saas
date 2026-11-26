<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\Prospect;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ProspectMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public Prospect $prospect,
        public string $emailSubject,
        public string $emailBody,
        public string $senderName,
    ) {}

    public function build(): self
    {
        return $this
            ->subject($this->emailSubject)
            ->view('emails.prospect-message');
    }
}
