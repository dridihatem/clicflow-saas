@props([
    'title' => null,
    'preheader' => null,
    'brand' => null,
    'eyebrow' => null,
])
@php
    $brand = $brand ?: (string) config('flowdesk.brand_name', 'ClicFlow');
    $colors = flowdesk_brand_colors();
    $logoUrl = flowdesk_email_logo_url();
    $homeUrl = flowdesk_email_home_url();
    $supportEmail = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title ?: $brand }}</title>
    @if ($preheader)
        <span style="display:none !important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;">{{ $preheader }}</span>
    @endif
</head>
<body style="margin:0;padding:0;background:#F8FAFC;color:#0f172a;font-family:Inter,ui-sans-serif,system-ui,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#F8FAFC;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:560px;width:100%;">
                    <tr>
                        <td style="padding:0 0 20px;text-align:center;">
                            <a href="{{ $homeUrl }}" style="text-decoration:none;display:inline-block;background:#ffffff;border-radius:16px;padding:14px 24px;border:1px solid #e2e8f0;box-shadow:0 4px 14px rgba(15,23,42,0.10);">
                                <img
                                    src="{{ $logoUrl }}"
                                    alt="{{ $brand }}"
                                    width="220"
                                    style="display:block;margin:0 auto;width:220px;max-width:80%;height:auto;border:0;outline:none;text-decoration:none;"
                                />
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;box-shadow:0 12px 40px -24px rgba(15,23,42,.22);">
                            {{-- Multicolour accent bar from the motif: sky, green, gold, orange, raspberry, purple --}}
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="height:6px;">
                                <tr>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['sky'] }};width:16.66%;">&nbsp;</td>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['green'] }};width:16.66%;">&nbsp;</td>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['gold'] }};width:16.66%;">&nbsp;</td>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['orange'] }};width:16.66%;">&nbsp;</td>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['raspberry'] }};width:16.66%;">&nbsp;</td>
                                    <td style="height:6px;line-height:6px;font-size:0;background:{{ $colors['purple'] }};width:16.7%;">&nbsp;</td>
                                </tr>
                            </table>
                            <div style="padding:28px 28px 24px;font-size:15px;line-height:1.65;color:#334155;">
                                {{ $slot }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 8px 0;text-align:center;font-size:12px;line-height:1.55;color:#94a3b8;">
                            <img
                                src="{{ $logoUrl }}"
                                alt=""
                                width="110"
                                style="display:inline-block;width:110px;height:auto;border:0;opacity:0.85;"
                            />
                            <div style="margin-top:10px;">
                                © {{ date('Y') }} {{ $brand }}
                                @if (is_string($supportEmail) && filter_var($supportEmail, FILTER_VALIDATE_EMAIL))
                                    · <a href="mailto:{{ $supportEmail }}" style="color:{{ $colors['orange'] }};text-decoration:none;">{{ __('Contact') }}</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
