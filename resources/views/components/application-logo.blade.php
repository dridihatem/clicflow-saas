@props([
    'tagline' => true,
    'inverse' => false,
    'collapsibleWordmark' => false,
    'markOnly' => false,
    'themed' => false,
])
@php
    $brandName = (string) config('flowdesk.brand_name', 'ClicFlow');
    $taglineText = __('Your AI Business Assistant');
    $alt = $tagline ? $brandName.' — '.$taglineText : $brandName;
    $size = match (true) {
        (bool) $markOnly => 'sm',
        (bool) $tagline => 'lg',
        default => 'md',
    };
    $logoUrl = flowdesk_brand_logo_url($inverse ? 'dark' : 'light', (bool) $markOnly);
@endphp
<span
    {{ $attributes->class([
        'flow-brand-logo inline-flex min-w-0 items-center',
        'flow-brand-logo--sm' => $size === 'sm',
        'flow-brand-logo--md' => $size === 'md',
        'flow-brand-logo--lg' => $size === 'lg',
        'flow-brand-logo--themed' => $themed && ! $inverse,
        'flow-brand-logo--inverse' => $inverse,
        'flow-brand-logo--mark-only' => $markOnly,
        'flow-sidebar-wordmark' => $collapsibleWordmark && ! $markOnly,
    ]) }}
    role="img"
    aria-label="{{ $alt }}"
>
    <img
        src="{{ $logoUrl }}"
        alt="{{ $alt }}"
        class="flow-brand-img"
    />
    @if ($tagline && ! $markOnly)
        <span class="flow-brand-tagline sr-only">{{ $taglineText }}</span>
    @endif
</span>
