@php
    $brand = (string) config('flowdesk.brand_name', 'ClicFlow');
    $support = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
    $days = max(1, (int) ceil($expireMinutes / (60 * 24)));
    $ownerName = $owner->name ?: $owner->email;
    $colors = flowdesk_brand_colors();
@endphp
<x-emails.layout :title="($isTest ? '[TEST] ' : '').$brand" :preheader="__('admin_welcome_mail_intro', ['brand' => $brand, 'company' => $company->name])">
    @if ($isTest)
        <div style="margin:-8px 0 18px;background:#FEF3C7;color:#92400E;text-align:center;padding:8px 12px;font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;border-radius:10px;">{{ __('admin_welcome_mail_test_banner') }}</div>
    @endif
    <p style="margin:0 0 8px;font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:{{ $colors['orange'] }};">{{ __('admin_welcome_mail_eyebrow') }}</p>
    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;letter-spacing:-.03em;color:#0f172a;">{{ __('admin_welcome_mail_title', ['company' => $company->name]) }}</h1>
    <p style="margin:0 0 16px;">{{ __('admin_welcome_mail_hello', ['name' => $ownerName]) }}</p>
    <p style="margin:0 0 18px;">{{ __('admin_welcome_mail_intro', ['brand' => $brand, 'company' => $company->name]) }}</p>

    <div style="margin:0 0 20px;padding:16px 18px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:14px;">
        <p style="margin:0 0 10px;"><strong style="color:#9a3412;">{{ __('admin_welcome_mail_workspace') }}</strong><br>
            <a href="{{ $loginUrl }}" style="color:{{ $colors['orange'] }};word-break:break-all;font-weight:600;text-decoration:none;">{{ $loginUrl }}</a>
        </p>
        <p style="margin:0;"><strong style="color:#9a3412;">{{ __('Email') }}</strong><br>{{ $owner->email }}</p>
        @if ($plainPassword)
            <p style="margin:14px 0 0;"><strong style="color:#9a3412;">{{ __('Password') }}</strong><br>
                <code style="display:inline-block;margin-top:4px;background:#fff;border:1px solid #FED7AA;color:#9a3412;padding:4px 10px;border-radius:8px;font-size:15px;font-weight:700;letter-spacing:.04em;">{{ $plainPassword }}</code>
            </p>
        @endif
    </div>

    @if ($resetUrl)
        <x-emails.button :href="$resetUrl">{{ __('admin_welcome_mail_set_password') }}</x-emails.button>
        <p style="margin:0 0 16px;font-size:13px;color:#64748b;">{{ __('admin_welcome_mail_link_expires', ['days' => $days]) }}</p>
        <p style="margin:0 0 16px;font-size:12px;color:#94a3b8;">{{ __('If the button does not work, copy this link:') }}<br>
            <span style="word-break:break-all;color:{{ $colors['orange'] }};">{{ $resetUrl }}</span>
        </p>
    @endif

    <p style="margin:0;font-size:13px;color:#64748b;">{{ __('admin_welcome_mail_support') }}
        @if (is_string($support) && filter_var($support, FILTER_VALIDATE_EMAIL))
            <a href="mailto:{{ $support }}" style="color:{{ $colors['orange'] }};text-decoration:none;font-weight:600;">{{ $support }}</a>
        @endif
    </p>
</x-emails.layout>
