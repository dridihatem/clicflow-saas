<x-emails.layout :title="__('Invoice')">
    @if (! empty($isReminder))
        <p style="margin:0 0 14px; padding:10px 12px; border-radius:10px; background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; font-weight:600;">
            {{ __('This is a reminder.') }}
        </p>
    @endif
    @if (! empty($documentTemplates['invoice_email_intro'] ?? null))
        <div style="margin:0 0 14px;">{!! $documentTemplates['invoice_email_intro'] !!}</div>
    @endif
    <p style="margin:0 0 12px;">{{ __('Hello') }},</p>
    <p style="margin:0 0 12px;">{{ __(':company has sent you an invoice for :amount :currency.', [
        'company' => $company->name,
        'amount' => flowdesk_format_minor((int) $invoice->amount, flowdesk_invoice_currency($invoice)),
        'currency' => $invoice->currency,
    ]) }}</p>
    @if ($invoice->hasLineVatRates())
        <p style="margin:0 0 12px;">{{ __('email_vat_breakdown', ['detail' => collect($invoice->vatBreakdown())->map(fn ($r) => $r['label'].' : '.flowdesk_format_minor((int) $r['vat'], flowdesk_invoice_currency($invoice)).' '.$invoice->currency)->implode(' · ')]) }}</p>
    @endif
    @if ($invoice->hasDiscount())
        <p style="margin:0 0 12px;">{{ __('Including a discount of :discount (lines total :lines, net :net excl. VAT).', [
            'discount' => flowdesk_format_minor((int) $invoice->discount_amount, flowdesk_invoice_currency($invoice)).' '.$invoice->currency.($invoice->discountRateLabel() !== '' ? ' ('.$invoice->discountRateLabel().')' : ''),
            'lines' => flowdesk_format_minor($invoice->linesSubtotalMinor(), flowdesk_invoice_currency($invoice)).' '.$invoice->currency,
            'net' => flowdesk_format_minor((int) $invoice->subtotal_amount, flowdesk_invoice_currency($invoice)).' '.$invoice->currency,
        ]) }}</p>
    @endif
    @if ($invoice->due_date)
        <p style="margin:0 0 12px;"><strong>{{ __('Due date') }}:</strong> {{ $invoice->due_date->format('Y-m-d') }}</p>
    @endif
    @if (! empty($documentTemplates['invoice_email_footer'] ?? null))
        <div style="margin:16px 0 0;">{!! $documentTemplates['invoice_email_footer'] !!}</div>
    @else
        <p style="margin:16px 0 0;">{{ __('Thank you.') }}</p>
    @endif
</x-emails.layout>
