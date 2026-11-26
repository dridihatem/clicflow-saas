<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProposalPdfService
{
    public function output(Proposal $proposal): string
    {
        return Pdf::loadHTML($this->html($proposal))->output();
    }

    /** HTML fed to Dompdf (also used by tests / previews). */
    public function html(Proposal $proposal): string
    {
        if ($proposal->exists) {
            $proposal->load(['client', 'items', 'company']);
        } else {
            $proposal->loadMissing(['company']);
            if (! $proposal->relationLoaded('items')) {
                $proposal->setRelation('items', collect());
            }
        }
        $documentTemplates = CompanySetting::query()->withoutGlobalScopes()->where('company_id', $proposal->company_id)->first()?->document_templates ?? [];
        $documentTemplates = is_array($documentTemplates) ? $documentTemplates : [];

        $pdfTheme = app(InvoicePdfThemeService::class)->forDocumentTemplates($documentTemplates);
        $themes = app(CompanyThemeService::class);
        $logoDataUri = $themes->logoDataUriForPdf($proposal->company);
        $signatureDataUri = $themes->signatureDataUriForPdf($proposal->company);

        $html = view('proposals.pdf', [
            'proposal' => $proposal,
            'documentTemplates' => $documentTemplates,
            'pdfMeta' => $this->buildPdfMeta($proposal),
            'pdfTheme' => $pdfTheme,
            'logoDataUri' => $logoDataUri,
            'signatureDataUri' => $signatureDataUri,
        ])->render();

        return app(DomPdfArabicFixer::class)->reshapeHtml($html);
    }

    public function stream(Proposal $proposal): StreamedResponse
    {
        $filename = 'quote-'.($proposal->number ?? $proposal->id).'.pdf';

        return response()->streamDownload(function () use ($proposal): void {
            echo $this->output($proposal);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array{vat_percent: float, fiscal_stamp_enabled: bool}
     */
    private function buildPdfMeta(Proposal $proposal): array
    {
        $proposal->loadMissing('company.settings');
        $company = $proposal->company;
        $billing = is_array($company?->settings?->billing) ? $company->settings->billing : [];
        $configuredVat = (float) ($billing['vat_percent'] ?? 0);
        $sub = (int) $proposal->subtotal_amount;
        $vatAmt = (int) $proposal->vat_amount;
        $vatPercent = ($sub > 0 && $vatAmt > 0)
            ? round(100.0 * $vatAmt / $sub, 2)
            : ($configuredVat > 0 ? $configuredVat : 0.0);

        return [
            'vat_percent' => $vatPercent,
            'fiscal_stamp_enabled' => filter_var($billing['fiscal_stamp_enabled'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }
}
