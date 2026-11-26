<x-emails.layout :title="__('Checkout request')">
    <p style="margin:0 0 6px; font-family:'Space Grotesk', Inter, ui-sans-serif, system-ui, sans-serif; font-size:18px; font-weight:600; color:#0f172a;">
        @if ($checkoutType === 'billing')
            {{ __('billing_checkout_admin_notify_heading') }}
        @else
            {{ __('marketing_checkout_admin_notify_heading') }}
        @endif
    </p>

    <p style="margin:0 0 16px;">{{ __('A customer submitted a checkout request because no payment method is configured.') }}</p>

    <div style="margin:0 0 16px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 8px;"><strong>{{ __('Name') }}:</strong> {{ $customerName }}</p>
        <p style="margin:0 0 8px;"><strong>{{ __('Email') }}:</strong> {{ $customerEmail }}</p>
        @if (! empty($context['company']))
            <p style="margin:0 0 8px;"><strong>{{ __('Company') }}:</strong> {{ $context['company'] }}</p>
        @endif
        @if (! empty($context['order_number']))
            <p style="margin:0 0 8px;"><strong>{{ __('Order') }}:</strong> {{ $context['order_number'] }}</p>
        @endif
        @if (! empty($context['plan']))
            <p style="margin:0 0 8px;"><strong>{{ __('Plan') }}:</strong> {{ $context['plan'] }} ({{ $context['period_months'] ?? '—' }} {{ __('months') }})</p>
        @endif
        @if (! empty($context['total']))
            <p style="margin:0 0 8px;"><strong>{{ __('Total') }}:</strong> {{ $context['total'] }}</p>
        @endif
        @if (! empty($context['items']))
            <p style="margin:0 0 8px;"><strong>{{ __('Items') }}:</strong> {{ $context['items'] }}</p>
        @endif
        @if (! empty($context['admin_url']))
            <p style="margin:0;"><strong>{{ __('Admin') }}:</strong> <a href="{{ $context['admin_url'] }}" style="color:#EA580C;">{{ $context['admin_url'] }}</a></p>
        @endif
    </div>

    @if (filled($customerMessage))
        <p style="margin:0 0 8px; font-weight:600; color:#0f172a;">{{ __('Customer message') }}</p>
        <p style="margin:0; white-space:pre-wrap; background:#F6F7FB; padding:12px 14px; border-radius:12px; border:1px solid rgba(91,100,140,0.14);">{{ $customerMessage }}</p>
    @endif
</x-emails.layout>
