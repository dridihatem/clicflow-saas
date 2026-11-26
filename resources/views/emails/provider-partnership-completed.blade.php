@php
    $company = $provider->company;
    $dashboardUrl = flowdesk_tenant_url($company, route('provider.dashboard', [], false));
@endphp
<x-emails.layout :title="__('Open provider dashboard')">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $provider->name]) }}</p>
    <p style="margin:0 0 18px;">{{ __(':company has signed the partnership. Your provider account is now fully active.', ['company' => $company->name]) }}</p>
    <p style="margin:0;">
        <a href="{{ $dashboardUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('Open provider dashboard') }}</a>
    </p>
</x-emails.layout>
