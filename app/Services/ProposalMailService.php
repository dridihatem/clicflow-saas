<?php

namespace App\Services;

use App\Mail\ProposalSentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Proposal;

class ProposalMailService
{
    public function __construct(
        private TenantSmtpMailer $tenantMailer,
    ) {}

    public function send(Proposal $proposal, Company $company): void
    {
        $settings = CompanySetting::query()->withoutGlobalScopes()->where('company_id', $company->id)->first();
        $dt = $settings?->document_templates;
        $documentTemplates = is_array($dt) ? $dt : [];
        $clientEmail = $proposal->client?->email;
        if ($clientEmail === null || $clientEmail === '') {
            throw new \RuntimeException(__('Client has no email address.'));
        }

        $mailable = new ProposalSentMail($proposal, $company, $documentTemplates);
        $this->tenantMailer->send($company, $mailable, $clientEmail);
    }
}
