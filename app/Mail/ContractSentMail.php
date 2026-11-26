<?php

namespace App\Mail;

use App\Models\ClientContract;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractSentMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ClientContract $contract,
        public Company $company,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('contract_sent_mail_subject', [
                'company' => $this->company->name,
                'title' => $this->contract->title,
            ]))
            ->view('emails.contract-sent');
    }
}
