@php
    $company = $provider->company;
    $signUrl = flowdesk_tenant_url($company, route('provider.partnership.contract', [], false));
@endphp
<x-emails.layout :title="__('Sign partnership')">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $provider->name]) }}</p>
    <p style="margin:0 0 16px;">{{ __('You registered as a business provider for :company. Please review and sign the partnership below to activate your workspace access.', ['company' => $company->name]) }}</p>
    <p style="margin:0 0 18px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px; white-space:pre-wrap;">{{ $termsText }}</p>
    <p style="margin:0 0 12px;">
        <a href="{{ $signUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('Sign partnership') }}</a>
    </p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $signUrl }}</span></p>
</x-emails.layout>
