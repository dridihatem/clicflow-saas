@props([
    'href',
    'color' => null,
])
@php
    $colors = flowdesk_brand_colors();
    $bg = $color ?: $colors['orange'];
@endphp
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:22px 0 8px;">
    <tr>
        <td style="border-radius:12px;background:{{ $bg }};">
            <a href="{{ $href }}" style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:12px;letter-spacing:-0.01em;">{{ $slot }}</a>
        </td>
    </tr>
</table>
