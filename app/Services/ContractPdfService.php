<?php

namespace App\Services;

use App\Models\ClientContract;
use App\Models\CompanySetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractPdfService
{
    public function __construct(
        private ContractMergeService $merge,
        private DomPdfArabicFixer $arabicFixer,
    ) {}

    public function output(ClientContract $contract): string
    {
        $contract->load(['client', 'company']);
        $documentTemplates = CompanySetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $contract->company_id)
            ->first()?->document_templates ?? [];
        $documentTemplates = is_array($documentTemplates) ? $documentTemplates : [];

        $pdfTheme = app(InvoicePdfThemeService::class)->forDocumentTemplates($documentTemplates);
        $themes = app(CompanyThemeService::class);
        $logoDataUri = $themes->logoDataUriForPdf($contract->company);
        $signatureDataUri = $themes->signatureDataUriForPdf($contract->company);
        $clientSignatureDataUri = $this->clientSignatureDataUri($contract);

        $bodyHtml = $this->merge->interpolate((string) $contract->body_html, $contract);
        $bodyRtl = $this->arabicFixer->prefersRtlLayout($bodyHtml);

        $html = view('contracts.pdf', [
            'contract' => $contract,
            'bodyHtml' => $bodyHtml,
            'bodyRtl' => $bodyRtl,
            'documentTemplates' => $documentTemplates,
            'pdfTheme' => $pdfTheme,
            'logoDataUri' => $logoDataUri,
            'signatureDataUri' => $signatureDataUri,
            'clientSignatureDataUri' => $clientSignatureDataUri,
        ])->render();

        $html = $this->arabicFixer->reshapeHtml($html);

        return Pdf::loadHTML($html)->output();
    }

    public function stream(ClientContract $contract): StreamedResponse
    {
        $filename = 'contract-'.($contract->reference ?? $contract->id).'.pdf';

        return response()->streamDownload(function () use ($contract): void {
            echo $this->output($contract);
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function clientSignatureDataUri(ClientContract $contract): ?string
    {
        $path = $contract->client_signature_path;
        if ($path === null || $path === '') {
            return null;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($path);
        if (! is_file($fullPath) || ! is_readable($fullPath)) {
            return null;
        }

        $mime = @mime_content_type($fullPath) ?: 'image/png';
        $binary = @file_get_contents($fullPath);
        if ($binary === false || $binary === '') {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }
}
