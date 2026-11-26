@props([
    'href' => null,
    'size' => 'md', // sm | md | lg
    'src' => null,
])
@php
    $brand = (string) config('flowdesk.brand_name', 'ClicFlow');
    $logoPath = public_path('images/logo-clicflow-light.png');
    $src = $src ?: (asset('images/logo-clicflow-light.png').'?v='.(is_file($logoPath) ? (filemtime($logoPath) ?: time()) : time()));
    $imgClass = match ($size) {
        'sm' => 'h-6 w-auto max-w-[132px] object-contain sm:h-7 sm:max-w-[148px]',
        'lg' => 'h-9 w-auto max-w-[200px] object-contain sm:h-10 sm:max-w-[220px]',
        default => 'h-8 w-auto max-w-[168px] object-contain sm:h-9 sm:max-w-[188px]',
    };
    $padClass = match ($size) {
        'sm' => 'px-2 py-1.5',
        'lg' => 'px-3.5 py-2.5',
        default => 'px-3 py-2',
    };
    $tag = $href ? 'a' : 'span';
@endphp
@once
    <style>
        .cf-shell-collapsed .cf-brand-tile {
            width: 2.85rem;
            height: 2.85rem;
            padding: 0.4rem;
        }
        .cf-shell-collapsed .cf-brand-tile__img {
            height: 1.9rem;
            width: 1.9rem;
            max-width: none;
            object-fit: cover;
            object-position: left center;
        }
    </style>
@endonce
<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'cf-brand-tile group inline-flex shrink-0 items-center justify-center rounded-2xl bg-white shadow-md shadow-black/15 ring-1 ring-black/5 transition hover:shadow-lg hover:shadow-black/20',
        $padClass,
    ]) }}
    aria-label="{{ $brand }}"
>
    <img
        src="{{ $src }}"
        alt="{{ $brand }}"
        class="cf-brand-tile__img {{ $imgClass }}"
    />
</{{ $tag }}>
