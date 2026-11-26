<?php

namespace App\Services;

use App\Models\CompanySetting;
use App\Models\ProjectStudy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ProjectStudyPdfService
{
    public function download(ProjectStudy $study): Response
    {
        $study->loadMissing(['company', 'client']);

        $documentTemplates = CompanySetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $study->company_id)
            ->first()
            ?->document_templates ?? [];
        $documentTemplates = is_array($documentTemplates) ? $documentTemplates : [];

        $logoDataUri = app(CompanyThemeService::class)->logoDataUriForPdf($study->company);
        $footer = trim((string) ($documentTemplates['invoice_pdf_footer'] ?? ''));
        $filename = 'etude-projet-'.Str::slug((string) $study->title).'.pdf';

        return Pdf::loadView('project-studies.pdf', [
            'company' => $study->company,
            'study' => $study,
            'logoDataUri' => $logoDataUri,
            'footerHtml' => $footer,
            'generatedAt' => now()->format('Y-m-d H:i'),
        ])->download($filename);
    }
}
