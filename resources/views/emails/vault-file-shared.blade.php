<x-emails.layout :title="__('vault_mail_title')" :preheader="__('vault_mail_intro', ['company' => $company->name])">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $client->contact_name ?: $client->name]) }}</p>
    <p style="margin:0 0 16px;">{{ __('vault_mail_intro', ['company' => $company->name]) }}</p>
    <div style="margin:0 0 18px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 6px;"><strong style="color:#0f172a;">{{ $file->original_name }}</strong></p>
        <p style="margin:0; font-size:13px; color:#64748b;">{{ $file->humanSize() }}@if($file->project) · {{ $file->project->title }}@endif</p>
        @if($file->description)
            <p style="margin:8px 0 0; font-size:13px; color:#334155;">{{ $file->description }}</p>
        @endif
    </div>
    <p style="margin:0 0 14px;">
        <a href="{{ $portalUrl }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('vault_mail_button') }}</a>
    </p>
    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('vault_mail_footer') }}</p>
</x-emails.layout>
