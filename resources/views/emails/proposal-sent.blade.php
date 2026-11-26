<x-emails.layout :title="__('Quote')">
    @if (! empty($documentTemplates['quote_email_intro'] ?? null))
        <div style="margin:0 0 14px;">{!! $documentTemplates['quote_email_intro'] !!}</div>
    @endif
    <p style="margin:0 0 12px;">{{ __('Hello') }},</p>
    <p style="margin:0 0 12px;">{{ __(':company has sent you a quote for :amount :currency.', [
        'company' => $company->name,
        'amount' => flowdesk_format_minor((int) $proposal->amount, strtoupper($proposal->currency)),
        'currency' => $proposal->currency,
    ]) }}</p>
    @if ($proposal->hasLineVatRates())
        <p style="margin:0 0 12px;">{{ __('email_vat_breakdown', ['detail' => collect($proposal->vatBreakdown())->map(fn ($r) => $r['label'].' : '.flowdesk_format_minor((int) $r['vat'], strtoupper($proposal->currency)).' '.$proposal->currency)->implode(' · ')]) }}</p>
    @endif
    @if ($proposal->hasDiscount())
        <p style="margin:0 0 12px;">{{ __('Including a discount of :discount (lines total :lines, net :net excl. VAT).', [
            'discount' => flowdesk_format_minor((int) $proposal->discount_amount, strtoupper($proposal->currency)).' '.$proposal->currency.($proposal->discountRateLabel() !== '' ? ' ('.$proposal->discountRateLabel().')' : ''),
            'lines' => flowdesk_format_minor($proposal->linesSubtotalMinor(), strtoupper($proposal->currency)).' '.$proposal->currency,
            'net' => flowdesk_format_minor((int) $proposal->subtotal_amount, strtoupper($proposal->currency)).' '.$proposal->currency,
        ]) }}</p>
    @endif
    @if ($proposal->valid_until)
        <p style="margin:0 0 12px;"><strong>{{ __('Valid until') }}:</strong> {{ $proposal->valid_until->format('Y-m-d') }}</p>
    @endif
    @if ($proposal->resolvedAdvanceMinor() > 0)
        <p style="margin:0 0 12px;"><strong>{{ __('Advance due') }}:</strong>
            {{ flowdesk_format_minor($proposal->resolvedAdvanceMinor(), strtoupper($proposal->currency)) }} {{ $proposal->currency }}
            @if ($proposal->advance_percent)
                ({{ $proposal->advance_percent }}%)
            @endif
        </p>
    @endif
    @if (! empty($proposal->payment_terms_json['modalities']))
        <p style="margin:0 0 8px;"><strong>{{ __('Payment modalities') }}:</strong></p>
        <ul style="margin:0 0 12px; padding-left:18px;">
            @foreach ($proposal->payment_terms_json['modalities'] as $mod)
                <li>{{ __($mod) }}</li>
            @endforeach
        </ul>
    @endif
    @if (! empty($documentTemplates['quote_email_footer'] ?? null))
        <div style="margin:16px 0 0;">{!! $documentTemplates['quote_email_footer'] !!}</div>
    @else
        <p style="margin:16px 0 0;">{{ __('Thank you.') }}</p>
    @endif
</x-emails.layout>
