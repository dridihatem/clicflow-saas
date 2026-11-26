<x-emails.layout :title="__('marketplace_order_paid_mail_intro', ['number' => $order->order_number])" :preheader="__('marketplace_order_paid_mail_intro', ['number' => $order->order_number])">
    <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $order->customer_name]) }}</p>
    <p style="margin:0 0 8px;">{{ __('marketplace_order_paid_mail_intro', ['number' => $order->order_number]) }}</p>
    <p style="margin:0 0 16px; font-size:14px;"><strong>{{ __('marketing_checkout_payment_reference_label') }}:</strong> <code style="background:#eeeafd; color:#EA580C; padding:0.15rem 0.45rem; border-radius:6px;">{{ $order->paymentReferenceLabel() }}</code></p>

    @if ($installedModuleNames !== [])
        <div style="margin:0 0 16px; padding:14px 16px; background:#ecfdf5; border-radius:12px; border:1px solid #a7f3d0;">
            <p style="margin:0 0 8px; font-weight:600; color:#065f46;">{{ __('marketplace_order_paid_mail_workspace_installed') }}</p>
            <ul style="margin:0; padding-left:1.25rem; color:#047857;">
                @foreach ($installedModuleNames as $name)
                    <li>{{ $name }}</li>
                @endforeach
            </ul>
            @if ($company && Route::has('settings.modules'))
                <p style="margin:12px 0 0;">
                    <a href="{{ flowdesk_tenant_url($company, route('settings.modules', [], false)) }}" style="color:#EA580C; font-weight:600;">{{ __('marketplace_order_paid_mail_open_modules') }}</a>
                </p>
            @endif
        </div>
    @endif

    <div style="margin:0 0 16px; padding:14px 16px; background:#F6F7FB; border:1px solid rgba(91,100,140,0.14); border-radius:12px;">
        <p style="margin:0 0 12px; font-weight:600; color:#0f172a;">{{ __('marketplace_order_paid_mail_downloads_heading') }}</p>
        @foreach ($downloads as $download)
            <p style="margin:0 0 12px;">
                <strong style="color:#0f172a;">{{ $download['name'] }}</strong>
                @if (! empty($download['url']))
                    <br>
                    <a href="{{ $download['url'] }}" style="color:#EA580C;">{{ __('marketplace_order_paid_mail_download_link') }}</a>
                @elseif (($download['attach_path'] ?? null) !== null)
                    <br><span style="font-size:13px; color:#64748b;">{{ __('marketplace_order_paid_mail_attached_zip') }}</span>
                @else
                    <br><span style="font-size:13px; color:#64748b;">{{ __('marketing_checkout_no_zip') }}</span>
                @endif
            </p>
        @endforeach
        <p style="margin:0; font-size:13px; color:#64748b;">{{ __('marketplace_order_paid_mail_links_expire') }}</p>
    </div>

    <p style="margin:0; font-size:13px; color:#64748b;">{{ __('marketplace_order_paid_mail_footer') }}</p>
</x-emails.layout>
