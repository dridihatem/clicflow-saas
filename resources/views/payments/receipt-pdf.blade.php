<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __('Payment receipt') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>{{ __('Payment receipt') }}</h1>
    <p class="muted">{{ $invoice->company?->name ?? config('app.name') }}</p>
    <p>{{ __('Receipt for first installment / deposit') }}</p>

    <table>
        <tr>
            <th>{{ __('Invoice') }}</th>
            <td>{{ $invoice->number ?? $invoice->id }}</td>
        </tr>
        @if ($invoice->proposal)
            <tr>
                <th>{{ __('Quote') }}</th>
                <td>{{ $invoice->proposal->number ?? $invoice->proposal->name }}</td>
            </tr>
        @endif
        <tr>
            <th>{{ __('Client') }}</th>
            <td>{{ $invoice->client?->name }}</td>
        </tr>
        <tr>
            <th>{{ __('Amount') }}</th>
            <td>{{ flowdesk_format_minor((int) $payment->amount, $invoice->currency) }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <th>{{ __('Paid at') }}</th>
            <td>{{ optional($payment->paid_at ?? $payment->created_at)->toDateTimeString() }}</td>
        </tr>
        <tr>
            <th>{{ __('Payment ID') }}</th>
            <td>{{ $payment->id }}</td>
        </tr>
    </table>
</body>
</html>
