@php
    $loginUrl = flowdesk_tenant_url($company, route('login', [], false));
@endphp
<x-emails.layout :title="__('client_credentials_mail_button')" :preheader="__('client_credentials_mail_intro', ['company' => $company->name])">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $client->name]) }}</p>
    <p style="margin:0 0 16px;">{{ __('client_credentials_mail_intro', ['company' => $company->name]) }}</p>
    <div style="margin:0 0 18px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 8px;"><strong style="color:#0f172a;">{{ __('Email') }}</strong><br>{{ $client->email }}</p>
        <p style="margin:0;"><strong style="color:#0f172a;">{{ __('Password') }}</strong><br><code style="background:#FFF7ED; color:#9a3412; padding:0.15rem 0.45rem; border-radius:6px; font-size:14px;">{{ $plainPassword }}</code></p>
    </div>
    <p style="margin:0 0 14px;">
        <a href="{{ $loginUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('client_credentials_mail_button') }}</a>
    </p>
    <p style="margin:0 0 8px; font-size:13px; color:#64748b;">{{ __('client_credentials_mail_security_note') }}</p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $loginUrl }}</span></p>
</x-emails.layout>
