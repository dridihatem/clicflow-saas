<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoicePdfService
{
    /**
     * Raw PDF bytes for an invoice (same output as the single download).
     */
    public function output(Invoice $invoice): string
    {
        return Pdf::loadHTML($this->html($invoice))->output();
    }

    /** HTML fed to Dompdf (also used by tests / previews). */
    public function html(Invoice $invoice): string
    {
        if ($invoice->exists) {
            $invoice->load(['client', 'items', 'company', 'payments']);
        } else {
            // Unsaved preview from the editor: relations are set in memory.
            $invoice->loadMissing(['company']);
            foreach (['items', 'payments'] as $rel) {
                if (! $invoice->relationLoaded($rel)) {
                    $invoice->setRelation($rel, collect());
                }
            }
        }
        $documentTemplates = CompanySetting::query()->withoutGlobalScopes()->where('company_id', $invoice->company_id)->first()?->document_templates ?? [];
        $documentTemplates = is_array($documentTemplates) ? $documentTemplates : [];

        $pdfTheme = app(InvoicePdfThemeService::class)->forInvoice($invoice, $documentTemplates);
        $themes = app(CompanyThemeService::class);
        $logoDataUri = $themes->logoDataUriForPdf($invoice->company);
        $signatureDataUri = $themes->signatureDataUriForPdf($invoice->company);

        $paymentQr = $invoice->exists ? flowdesk_invoice_payment_qr($invoice) : [];
        $completedTotal = $invoice->exists ? (int) $invoice->completedPaymentsTotalMinor() : 0;
        $balanceMinor = max(0, (int) $invoice->amount - $completedTotal);

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'documentTemplates' => $documentTemplates,
            'pdfMeta' => $this->buildPdfMeta($invoice),
            'pdfTheme' => $pdfTheme,
            'logoDataUri' => $logoDataUri,
            'signatureDataUri' => $signatureDataUri,
            'paymentUrl' => $paymentQr['url'] ?? null,
            'paymentQrDataUri' => $paymentQr['data_uri'] ?? null,
            'balanceMinor' => $balanceMinor,
        ])->render();

        return app(DomPdfArabicFixer::class)->reshapeHtml($html);
    }

    public function stream(Invoice $invoice): StreamedResponse
    {
        $filename = 'invoice-'.($invoice->number ?? $invoice->id).'.pdf';

        return response()->streamDownload(function () use ($invoice): void {
            echo $this->output($invoice);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array{vat_percent: float, fiscal_stamp_enabled: bool}
     */
    private function buildPdfMeta(Invoice $invoice): array
    {
        $invoice->loadMissing('company.settings');
        $company = $invoice->company;
        $billing = is_array($company?->settings?->billing) ? $company->settings->billing : [];
        $configuredVat = (float) ($billing['vat_percent'] ?? 0);
        $sub = (int) $invoice->subtotal_amount;
        $vatAmt = (int) $invoice->vat_amount;
        $vatPercent = ($sub > 0 && $vatAmt > 0)
            ? round(100.0 * $vatAmt / $sub, 2)
            : ($configuredVat > 0 ? $configuredVat : 0.0);

        return [
            'vat_percent' => $vatPercent,
            'fiscal_stamp_enabled' => filter_var($billing['fiscal_stamp_enabled'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }

    /**
     * Safe unique filename inside a ZIP (number can repeat across companies; id disambiguates).
     */
    public function zipEntryName(Invoice $invoice): string
    {
        $num = (string) ($invoice->number ?? $invoice->id);
        $slug = Str::slug($num, '-');
        if ($slug === '') {
            $slug = 'invoice';
        }

        return $slug.'-'.$invoice->id.'.pdf';
    }
}
