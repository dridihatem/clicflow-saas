@php
    $pdfTheme = is_array($pdfTheme ?? null) ? $pdfTheme : [];
    $logoDataUri = $logoDataUri ?? null;
    $signatureDataUri = $signatureDataUri ?? null;
    $clientSignatureDataUri = $clientSignatureDataUri ?? null;
    $company = $contract->company;
    $addrLine = trim(implode(' ', array_filter([$company?->postal_code, $company?->city])));
    $sellerLines = collect([
        $company?->address_line1,
        $addrLine !== '' ? $addrLine : null,
        $company?->phone,
        $company?->contact_email,
        $company?->tax_id ? __('VAT / TVA').': '.$company->tax_id : null,
    ])->filter()->values();
    $compact = filter_var($pdfTheme['compact_header'] ?? true, FILTER_VALIDATE_BOOL);
    $pad = $compact ? '14mm 16mm' : '16mm 18mm';
    $border = $pdfTheme['border_color'] ?? '#d4d4d8';
    $text = $pdfTheme['text_color'] ?? '#27272a';
    $muted = $pdfTheme['muted_color'] ?? '#71717a';
    $primary = $pdfTheme['primary_color'] ?? '#18181b';
    $accent = $pdfTheme['accent_color'] ?? '#0d9488';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: {{ $compact ? '8.5pt' : '9pt' }}; color: {{ $text }}; line-height: 1.5; margin: 0; padding: {{ $pad }}; background: #fff; }
        .doc { width: 100%; }
        table.doc-top { width: 100%; border-collapse: collapse; margin-bottom: {{ $compact ? '6mm' : '8mm' }}; }
        table.doc-top td { vertical-align: middle; padding: 0; }
        .doc-logo { max-height: {{ $compact ? '14mm' : '18mm' }}; max-width: {{ $compact ? '48mm' : '56mm' }}; object-fit: contain; display: block; }
        .doc-title { font-size: {{ $compact ? '20pt' : '24pt' }}; font-weight: normal; color: {{ $accent }}; text-align: right; letter-spacing: 0.06em; line-height: 1.1; margin: 0; }
        .doc-rule { height: 0; border: none; border-top: 1.5px solid {{ $accent }}; margin: 0 0 {{ $compact ? '5mm' : '6mm' }} 0; }
        table.doc-parties { width: 100%; border-collapse: collapse; margin-bottom: {{ $compact ? '5mm' : '6mm' }}; }
        table.doc-parties td { vertical-align: top; width: 50%; padding: 0; }
        table.doc-parties td:first-child { padding-right: 6mm; }
        table.doc-parties td:last-child { padding-left: 6mm; border-left: 0.5px solid {{ $border }}; }
        .doc-label { font-size: {{ $compact ? '6.5pt' : '7pt' }}; color: {{ $accent }}; text-transform: uppercase; letter-spacing: 0.12em; font-weight: bold; margin: 0 0 2mm 0; }
        .doc-name { font-size: {{ $compact ? '10pt' : '11pt' }}; font-weight: bold; color: {{ $primary }}; margin: 0 0 1.5mm 0; }
        .doc-line { font-size: {{ $compact ? '7.5pt' : '8pt' }}; color: {{ $text }}; margin-top: 0.5mm; }
        table.doc-meta { width: 100%; border-collapse: collapse; margin-bottom: {{ $compact ? '5mm' : '6mm' }}; }
        table.doc-meta td { padding: {{ $compact ? '2mm 3mm' : '2.5mm 3.5mm' }}; border: 0.5px solid {{ $border }}; text-align: center; font-size: {{ $compact ? '7pt' : '7.5pt' }}; width: 25%; }
        .doc-meta-lbl { display: block; color: {{ $muted }}; font-size: {{ $compact ? '6pt' : '6.5pt' }}; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.8mm; }
        .doc-meta-val { display: block; font-weight: bold; color: {{ $primary }}; }
        .doc-body { margin-top: 4mm; font-size: {{ $compact ? '8.5pt' : '9pt' }}; color: {{ $text }}; }
        .doc-body.doc-body-rtl { text-align: right; }
        .doc-body h2 { font-size: 12pt; color: {{ $primary }}; margin: 4mm 0 2mm; }
        .doc-body h3 { font-size: 10pt; color: {{ $primary }}; margin: 3mm 0 1.5mm; }
        .doc-body p { margin: 0 0 2mm; }
        /* Keep Arabic glyphs readable after Ar-PHP shaping (DomPDF has no native RTL shaping). */
        .doc-body, .doc-body * { font-family: DejaVu Sans, sans-serif; }
        table.doc-sigs { width: 100%; border-collapse: collapse; margin-top: {{ $compact ? '8mm' : '10mm' }}; }
        table.doc-sigs td { width: 50%; vertical-align: top; padding: 0 4mm 0 0; }
        table.doc-sigs td:last-child { padding: 0 0 0 4mm; }
        .sig-label { font-size: 7pt; color: {{ $muted }}; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 2mm; }
        .sig-img { max-height: {{ $compact ? '16mm' : '22mm' }}; max-width: 55mm; object-fit: contain; display: block; }
        .sig-date { font-size: 7.5pt; color: {{ $muted }}; margin-top: 1.5mm; }
        .doc-footer { margin-top: 6mm; padding-top: 3mm; border-top: 0.5px solid {{ $border }}; font-size: 7pt; color: {{ $muted }}; text-align: center; }
    </style>
</head>
<body>
<div class="doc">
    <table class="doc-top">
        <tr>
            <td style="width: 55%;">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="" class="doc-logo" />
                @else
                    <div class="doc-name" style="font-size: {{ $compact ? '13pt' : '15pt' }};">{{ $company?->name ?? '—' }}</div>
                @endif
            </td>
            <td style="width: 45%;">
                <div class="doc-title">{{ __('Contract') }}</div>
            </td>
        </tr>
    </table>
    <hr class="doc-rule" />

    <table class="doc-parties">
        <tr>
            <td>
                <div class="doc-label">{{ __('Company') }}</div>
                <div class="doc-name">{{ $company?->name ?? '—' }}</div>
                @foreach ($sellerLines as $line)
                    <div class="doc-line">{{ $line }}</div>
                @endforeach
            </td>
            <td>
                <div class="doc-label">{{ __('Client') }}</div>
                @if ($contract->client)
                    <div class="doc-name">{{ $contract->client->name }}</div>
                    @if ($contract->client->email)
                        <div class="doc-line">{{ $contract->client->email }}</div>
                    @endif
                    @if ($contract->client->phone)
                        <div class="doc-line">{{ $contract->client->phone }}</div>
                    @endif
                @else
                    <div class="doc-name">—</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="doc-meta">
        <tr>
            <td>
                <span class="doc-meta-lbl">{{ __('Reference') }}</span>
                <span class="doc-meta-val">{{ $contract->reference ?? $contract->id }}</span>
            </td>
            <td>
                <span class="doc-meta-lbl">{{ __('Title') }}</span>
                <span class="doc-meta-val">{{ $contract->title }}</span>
            </td>
            <td>
                <span class="doc-meta-lbl">{{ __('Status') }}</span>
                <span class="doc-meta-val">{{ $contract->status->label() }}</span>
            </td>
            <td>
                <span class="doc-meta-lbl">{{ __('Date') }}</span>
                <span class="doc-meta-val">{{ $contract->created_at?->format('d/m/Y') ?? '—' }}</span>
            </td>
        </tr>
    </table>

    <div class="doc-body {{ ! empty($bodyRtl) ? 'doc-body-rtl' : '' }}">{!! $bodyHtml !!}</div>

    <table class="doc-sigs">
        <tr>
            <td>
                <div class="sig-label">{{ __('Company signature') }}</div>
                @if (! empty($signatureDataUri))
                    <img src="{{ $signatureDataUri }}" alt="" class="sig-img" />
                @else
                    <div class="doc-line" style="color: {{ $muted }};">—</div>
                @endif
                @if ($contract->company_signed_at)
                    <div class="sig-date">{{ $contract->company_signed_at->format('d/m/Y H:i') }}</div>
                @endif
            </td>
            <td>
                <div class="sig-label">{{ __('Client signature') }}</div>
                @if (! empty($clientSignatureDataUri))
                    <img src="{{ $clientSignatureDataUri }}" alt="" class="sig-img" />
                @else
                    <div class="doc-line" style="color: {{ $muted }};">—</div>
                @endif
                @if ($contract->client_signed_at)
                    <div class="sig-date">{{ $contract->client_signed_at->format('d/m/Y H:i') }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if (! empty($documentTemplates['invoice_pdf_footer'] ?? null))
        <div class="doc-footer">{!! $documentTemplates['invoice_pdf_footer'] !!}</div>
    @endif
</div>
</body>
</html>
