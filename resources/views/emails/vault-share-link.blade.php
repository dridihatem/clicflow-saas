<x-emails.layout :title="__('vault_link_mail_title')" :preheader="__('vault_link_mail_intro', ['sender' => $senderName ?: $company->name, 'company' => $company->name])">
    <p style="margin:0 0 12px;">{{ __('vault_link_mail_hello') }}</p>
    <p style="margin:0 0 16px;">{{ __('vault_link_mail_intro', ['sender' => $senderName ?: $company->name, 'company' => $company->name]) }}</p>
    @if ($personalMessage)
        <div style="margin:0 0 16px; padding:12px 14px; border-left:3px solid #EA580C; background:#FFF7ED; border-radius:8px; color:#334155; white-space:pre-line;">{{ $personalMessage }}</div>
    @endif
    <div style="margin:0 0 18px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 6px;"><strong style="color:#0f172a;">{{ $file->original_name }}</strong></p>
        <p style="margin:0; font-size:13px; color:#64748b;">{{ $file->humanSize() }}
            · {{ $share->expires_at ? __('vault_link_expires_at', ['date' => $share->expires_at->format('d/m/Y H:i')]) : __('vault_link_no_expiry') }}
            @if ($share->max_downloads) · {{ __('vault_link_mail_max', ['count' => $share->max_downloads]) }}@endif
        </p>
        @if ($linkPassword)
            <p style="margin:10px 0 0;"><strong style="color:#0f172a;">{{ __('vault_link_password') }}</strong> : <code style="font-family:Menlo,Consolas,monospace; background:#fff; border:1px solid #e2e8f0; border-radius:6px; padding:2px 8px; color:#0f172a;">{{ $linkPassword }}</code></p>
        @elseif ($share->hasPassword())
            <p style="margin:10px 0 0; font-size:13px; color:#64748b;">{{ __('vault_link_mail_password_separate') }}</p>
        @endif
    </div>
    <p style="margin:0 0 14px;">
        <a href="{{ $url }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('vault_link_mail_button') }}</a>
    </p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('If the button does not work, copy this link:') }}<br><span style="word-break:break-all; color:#EA580C;">{{ $url }}</span></p>
</x-emails.layout>
