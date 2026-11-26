<x-emails.layout :title="__('Contract')">
    <p style="margin:0 0 12px;">{{ __('Hello') }},</p>
    <p style="margin:0 0 16px;">{{ __('contract_sent_mail_body', [
        'company' => $company->name,
        'title' => $contract->title,
        'reference' => $contract->reference ?? $contract->id,
    ]) }}</p>
    <p style="margin:0 0 14px;">
        <a href="{{ route('portal.contracts.show', $contract) }}" style="display:inline-block; padding:12px 20px; background:#EA580C; color:#fff; text-decoration:none; border-radius:999px; font-weight:600; font-size:14px;">{{ __('contract_sent_mail_cta') }}</a>
    </p>
    <p style="margin:0;">{{ __('Thank you.') }}</p>
</x-emails.layout>
