<?php

namespace App\Services;

use App\Mail\InvoiceSentMail;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\Invoice;

class InvoiceMailService
{
    public function __construct(
        private TenantSmtpMailer $tenantMailer,
    ) {}

    public function send(Invoice $invoice, Company $company, bool $isReminder = false): void
    {
        $settings = CompanySetting::query()->withoutGlobalScopes()->where('company_id', $company->id)->first();
        $dt = $settings?->document_templates;
        $documentTemplates = is_array($dt) ? $dt : [];
        $clientEmail = $invoice->client?->email;
        if ($clientEmail === null || $clientEmail === '') {
            throw new \RuntimeException(__('Client has no email address.'));
        }

        $mailable = new InvoiceSentMail($invoice, $company, $isReminder, $documentTemplates);
        $this->tenantMailer->send($company, $mailable, $clientEmail);
    }
}
