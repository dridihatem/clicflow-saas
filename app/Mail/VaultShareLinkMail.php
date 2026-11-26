<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\VaultFile;
use App\Models\VaultFileShare;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** Sends a Coffre-fort share link; the password is included only when explicitly chosen. */
class VaultShareLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VaultFileShare $share,
        public VaultFile $file,
        public Company $company,
        public string $url,
        public ?string $linkPassword = null,
        public ?string $personalMessage = null,
        public ?string $senderName = null,
    ) {}

    public function build(): self
    {
        return $this
            ->subject(__('vault_link_mail_subject', ['company' => $this->company->name, 'file' => $this->file->original_name]))
            ->view('emails.vault-share-link');
    }
}
