@props(['url'])
@php
    $brand = (string) config('flowdesk.brand_name', 'ClicFlow');
    $logoUrl = flowdesk_email_logo_url();
    $home = $url ?: flowdesk_email_home_url();
    $colors = flowdesk_brand_colors();
@endphp
<tr>
<td class="header" style="padding:24px 0 16px;text-align:center;">
<a href="{{ $home }}" style="display:inline-block;text-decoration:none;background:#ffffff;border-radius:16px;padding:14px 24px;border:1px solid #e2e8f0;box-shadow:0 4px 14px rgba(15,23,42,0.10);" target="_blank" rel="noopener">
<img src="{{ $logoUrl }}" width="220" alt="{{ $brand }}" style="display:block;margin:0 auto;width:220px;max-width:80%;height:auto;border:0;outline:none;">
</a>
</td>
</tr>
