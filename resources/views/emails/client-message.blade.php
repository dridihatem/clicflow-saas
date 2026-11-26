<x-emails.layout :title="$company->name ?? config('flowdesk.brand_name', 'ClicFlow')">
<p style="font-family: system-ui, sans-serif; font-size: 15px; line-height: 1.55; color: #0f172a; white-space: pre-wrap;">{{ $emailBody }}</p>

<p style="font-family: system-ui, sans-serif; font-size: 13px; color: #64748b; margin-top: 28px;">
    — {{ $senderName }}<br>
    {{ $company->name }}
</p>
</x-emails.layout>
