<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #27272a;
            line-height: 1.45;
            margin: 0;
            padding: 14mm 16mm;
            background: #fff;
        }
        .banner {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
            border-bottom: 2px solid #0f766e;
            padding-bottom: 4mm;
        }
        .banner td { vertical-align: middle; padding: 0 0 4mm; }
        .logo { max-height: 16mm; max-width: 48mm; display: block; }
        .doc-title {
            font-size: 18pt;
            font-weight: bold;
            color: #0f766e;
            margin: 0 0 1mm;
            letter-spacing: 0.02em;
        }
        .doc-sub { color: #71717a; font-size: 8pt; }
        .meta-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }
        .meta-grid td {
            width: 50%;
            vertical-align: top;
            padding: 0;
        }
        .box {
            border: 1px solid #e4e4e7;
            border-radius: 2mm;
            padding: 3.5mm 4mm;
            background: #fafafa;
        }
        .box-title {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #71717a;
            margin: 0 0 2mm;
            font-weight: bold;
        }
        .box strong { font-size: 10pt; color: #18181b; }
        .muted { color: #71717a; font-size: 8pt; }
        .section-title {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #52525b;
            margin: 5mm 0 2mm;
            font-weight: bold;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
        }
        table.lines th {
            background: #0f766e;
            color: #fff;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 2.5mm 2.5mm;
            text-align: left;
            font-weight: bold;
        }
        table.lines th.num, table.lines td.num { text-align: right; }
        table.lines td {
            border-bottom: 1px solid #e4e4e7;
            padding: 2.8mm 2.5mm;
            font-size: 9pt;
        }
        table.lines tr:nth-child(even) td { background: #fafafa; }
        .kind-earning { color: #0f766e; font-size: 7.5pt; font-weight: bold; }
        .kind-deduction { color: #b91c1c; font-size: 7.5pt; font-weight: bold; }
        .totals-wrap { margin-top: 5mm; }
        table.totals {
            width: 52%;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 2mm 2.5mm;
            border-bottom: 1px solid #e4e4e7;
        }
        table.totals td.label { color: #52525b; }
        table.totals td.val { text-align: right; font-variant-numeric: tabular-nums; }
        table.totals tr.grand td {
            border-bottom: none;
            border-top: 2px solid #0f766e;
            background: #f0fdfa;
            font-weight: bold;
            font-size: 11pt;
            color: #0f766e;
            padding-top: 3mm;
            padding-bottom: 3mm;
        }
        .footer {
            margin-top: 10mm;
            padding-top: 3mm;
            border-top: 1px solid #e4e4e7;
            color: #a1a1aa;
            font-size: 7.5pt;
        }
        .badge {
            display: inline-block;
            padding: 0.8mm 2.5mm;
            border-radius: 2mm;
            font-size: 7.5pt;
            font-weight: bold;
            letter-spacing: 0.04em;
            background: #ecfdf5;
            color: #0f766e;
            border: 1px solid #99f6e4;
        }
    </style>
</head>
<body>
    <table class="banner">
        <tr>
            <td style="width: 58%;">
                @if ($logoDataUri)
                    <img class="logo" src="{{ $logoDataUri }}" alt="">
                    <div style="height: 2mm;"></div>
                @endif
                <div class="doc-title">{{ __('hr_payslip_title') }}</div>
                <div class="doc-sub">{{ $company?->name }}</div>
                @if ($company?->tax_id)
                    <div class="doc-sub">{{ __('VAT / TVA') }}: {{ $company->tax_id }}</div>
                @endif
            </td>
            <td style="width: 42%; text-align: right;">
                <div style="font-size: 10pt; font-weight: bold;">{{ $run?->title }}</div>
                <div class="muted" style="margin-top: 1mm;">
                    {{ $run?->period_start?->format('d/m/Y') }} → {{ $run?->period_end?->format('d/m/Y') }}
                </div>
                <div class="muted">{{ __('hr_pay_date') }}: {{ $run?->pay_date?->format('d/m/Y') }}</div>
                <div style="margin-top: 2.5mm;"><span class="badge">{{ strtoupper((string) $payslip->status) }}</span></div>
            </td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td style="padding-right: 2mm;">
                <div class="box">
                    <div class="box-title">{{ __('Employee') }}</div>
                    <strong>{{ $employee?->full_name }}</strong>
                    <div class="muted" style="margin-top: 1.5mm;">{{ __('hr_employee_number') }}: {{ $employee?->employee_number ?? '—' }}</div>
                    <div class="muted">{{ __('Job title') }}: {{ $employee?->job_title ?? '—' }}</div>
                    <div class="muted">{{ __('hr_department') }}: {{ $employee?->department?->name ?? '—' }}</div>
                </div>
            </td>
            <td style="padding-left: 2mm;">
                <div class="box">
                    <div class="box-title">{{ __('hr_payslip_summary') }}</div>
                    <div class="muted">{{ __('Gross') }}</div>
                    <strong>{{ flowdesk_format_minor((int) $payslip->gross_minor, $payslip->currency) }} {{ $payslip->currency }}</strong>
                    <div class="muted" style="margin-top: 2mm;">{{ __('Net') }}</div>
                    <strong style="color: #0f766e;">{{ flowdesk_format_minor((int) $payslip->net_minor, $payslip->currency) }} {{ $payslip->currency }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <div class="section-title">{{ __('hr_payslip_lines') }}</div>
    <table class="lines" dir="ltr" style="direction: ltr; width: 100%;">
        <thead>
            <tr>
                <th style="width: 52%; text-align: left;">{{ __('Description') }}</th>
                <th style="width: 20%; text-align: left;">{{ __('Type') }}</th>
                <th style="width: 28%; text-align: right;">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                @php
                    $kind = strtolower((string) ($line['kind'] ?? 'earning'));
                    $isDeduction = $kind === 'deduction';
                    $kindLabel = $isDeduction
                        ? __('hr_rule_kind_deduction')
                        : __('hr_rule_kind_earning');
                    $amountMinor = (int) ($line['amount_minor'] ?? 0);
                    $amountText = flowdesk_format_minor($amountMinor, $payslip->currency);
                @endphp
                <tr>
                    <td style="text-align: left;">
                        {{ $line['name'] ?? '—' }}
                        @if (! empty($line['percent']))
                            <span class="muted"> ({{ rtrim(rtrim(number_format((float) $line['percent'], 2, '.', ''), '0'), '.') }}%)</span>
                        @endif
                    </td>
                    <td style="text-align: left; color: {{ $isDeduction ? '#b91c1c' : '#0f766e' }}; font-weight: bold; font-size: 8pt;">
                        {{ $kindLabel }}
                    </td>
                    <td style="text-align: right; font-variant-numeric: tabular-nums; direction: ltr;">
                        {{ $isDeduction ? '-' : '' }}{{ $amountText }} {{ $payslip->currency }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals">
            <tr>
                <td class="label">{{ __('Gross') }}</td>
                <td class="val">{{ flowdesk_format_minor((int) $payslip->gross_minor, $payslip->currency) }} {{ $payslip->currency }}</td>
            </tr>
            <tr>
                <td class="label">{{ __('Deductions') }}</td>
                <td class="val">−{{ flowdesk_format_minor((int) $payslip->deductions_minor, $payslip->currency) }} {{ $payslip->currency }}</td>
            </tr>
            <tr class="grand">
                <td>{{ __('Net') }}</td>
                <td class="val">{{ flowdesk_format_minor((int) $payslip->net_minor, $payslip->currency) }} {{ $payslip->currency }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">{{ __('hr_payslip_footer_note') }}</div>
</body>
</html>
