<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $study->title }}</title>
    <style>
        @page { margin: 22mm 16mm 24mm 16mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            line-height: 1.55;
        }
        .header {
            width: 100%;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }
        .header td { vertical-align: middle; }
        .logo { max-height: 42px; max-width: 140px; }
        .brand { font-size: 14pt; font-weight: bold; color: #0f766e; margin: 0; }
        .meta { font-size: 8.5pt; color: #64748b; margin: 2px 0 0; }
        h1 { font-size: 16pt; margin: 0 0 6px; color: #0f172a; }
        h2 {
            font-size: 11pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #0f766e;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin: 18px 0 8px;
        }
        .section { white-space: pre-wrap; margin: 0 0 8px; }
        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -14mm;
            font-size: 8pt;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 55%;">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" class="logo" alt="">
                @else
                    <p class="brand">{{ $company->name }}</p>
                @endif
                <p class="meta">{{ $company->name }}</p>
            </td>
            <td style="width: 45%; text-align: right;">
                <p class="brand">{{ __('Project studies') }}</p>
                <p class="meta">{{ $generatedAt }}</p>
            </td>
        </tr>
    </table>

    <h1>{{ $study->title }}</h1>
    <p class="meta">
        {{ __('Status') }}: {{ __('project_study_status_'.$study->status) }}
        @if ($study->client)
            · {{ __('Client') }}: {{ $study->client->name }}
        @endif
        @if (! empty($study->currency))
            · {{ $study->currency }}
        @endif
    </p>

    @foreach ([
        'executive_summary' => __('project_study_section_executive'),
        'technical_study' => __('project_study_section_technical'),
        'financial_study' => __('project_study_section_financial'),
        'methodology' => __('project_study_section_methodology'),
        'risks' => __('project_study_section_risks'),
        'conclusion' => __('project_study_section_conclusion'),
    ] as $field => $label)
        @if (filled($study->{$field} ?? null))
            <h2>{{ $label }}</h2>
            <div class="section">{{ $study->{$field} }}</div>
        @endif
    @endforeach

    <div class="footer">
        <table width="100%">
            <tr>
                <td>
                    @if (! empty($footerHtml))
                        {!! nl2br(e($footerHtml)) !!}
                    @else
                        {{ $company->name }} · {{ __('project_study_pdf_footer') }}
                    @endif
                </td>
                <td style="text-align: right;"><span class="page"></span></td>
            </tr>
        </table>
    </div>
</body>
</html>
