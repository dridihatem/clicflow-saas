<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Company;
use App\Models\VaultFile;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Notifies a client that a file was added to their Coffre-fort (no attachment, link to the portal). */
class VaultFileSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VaultFile $file,
        public Client $client,
        public Company $company,
        public string $portalUrl,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('vault_mail_subject', ['company' => $this->company->name]))
            ->view('emails.vault-file-shared');
    }
}
