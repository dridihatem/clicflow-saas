<x-emails.layout :title="__('client_access_link_mail_button')" :preheader="__('client_access_link_mail_intro', ['company' => $company->name])">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $client->name]) }}</p>
    <p style="margin:0 0 16px;">{{ __('client_access_link_mail_intro', ['company' => $company->name]) }}</p>
    <div style="margin:0 0 18px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 8px;"><strong style="color:#0f172a;">{{ __('Email') }}</strong><br>{{ $email }}</p>
        <p style="margin:0;"><strong style="color:#0f172a;">{{ __('client_access_link_mail_login_label') }}</strong><br><a href="{{ $loginUrl }}" style="color:#EA580C; word-break:break-all;">{{ $loginUrl }}</a></p>
    </div>
    <p style="margin:0 0 14px;">
        <a href="{{ $setPasswordUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('client_access_link_mail_button') }}</a>
    </p>
    <p style="margin:0 0 8px; font-size:13px; color:#64748b;">{{ __('client_access_link_mail_expiry') }}</p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $setPasswordUrl }}</span></p>
</x-emails.layout>
