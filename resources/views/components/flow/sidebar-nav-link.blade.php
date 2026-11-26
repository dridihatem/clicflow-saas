@props([
    'href',
    'label',
    'icon',
    'active' => false,
    'variant' => 'sidebar',
])

@php
    $base = 'flex items-center gap-2 rounded-lg text-sm font-medium transition duration-150 ease-in-out';
    $classes = match ($variant) {
        'flyout' => $base.' px-2.5 py-2 '.($active ? 'flow-nav-flyout-link-active' : 'flow-nav-flyout-link'),
        default => $base.' px-3 py-2.5 '.($active ? 'flow-nav-item-active' : 'flow-nav-item-inactive'),
    };
@endphp

<a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
    <x-cf-icon :name="$icon" size="md" class="shrink-0 opacity-90" />
    <span class="min-w-0 truncate">{{ $label }}</span>
</a>
