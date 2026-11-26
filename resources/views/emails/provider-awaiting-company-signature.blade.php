@php
    $company = $provider->company;
    $signUrl = flowdesk_tenant_url($company, route('providers.partnership.show', $provider, false));
@endphp
<x-emails.layout :title="__('Open partnership signing')">
    <p style="margin:0 0 12px;">{{ __('Hello,') }}</p>
    <p style="margin:0 0 18px;">{{ __(':provider has signed the business provider partnership for :company. Open the workspace to review and sign on your side to finalize access.', ['provider' => $provider->name, 'company' => $company->name]) }}</p>
    <p style="margin:0 0 12px;">
        <a href="{{ $signUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('Open partnership signing') }}</a>
    </p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $signUrl }}</span></p>
</x-emails.layout>
