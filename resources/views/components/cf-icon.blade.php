@props([
    'name' => null,
    'variant' => 'plain', // plain | tile
    'tone' => null,
    'size' => 'md', // sm | md | lg | xl
    'label' => null,
])

@php
    $id = \App\Support\ClicflowIcon::resolve((string) $name);
    $svg = $id ? \App\Support\ClicflowIcon::svg($id) : '';
    $sizes = [
        'sm' => 'cf-icon--sm',
        'md' => 'cf-icon--md',
        'lg' => 'cf-icon--lg',
        'xl' => 'cf-icon--xl',
    ];
    $tones = ['orange','amber','violet','emerald','teal','sky','rose','slate','blue','red'];
    $toneClass = $tone && in_array($tone, $tones, true) ? 'cf-icon-tone--'.$tone : '';
    $wrapperClass = trim(implode(' ', array_filter([
        'cf-icon',
        $variant === 'tile' ? 'cf-icon--tile' : 'cf-icon--plain',
        $sizes[$size] ?? $sizes['md'],
        $toneClass,
    ])));
@endphp

@if ($svg)
    <span {{ $attributes->class([$wrapperClass])->merge(['aria-hidden' => $label ? 'false' : 'true']) }} @if ($label) role="img" aria-label="{{ $label }}" @endif>
        {!! $svg !!}
    </span>
@elseif (is_string($name) && str_contains($name, 'fa-'))
    {{-- Fallback: keep Font Awesome when the name is not in the set. --}}
    <i {{ $attributes->class([$name, 'fa-fw shrink-0']) }} aria-hidden="true"></i>
@endif
