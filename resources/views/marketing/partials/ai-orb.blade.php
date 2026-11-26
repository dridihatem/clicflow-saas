{{-- Light marketing AI orb (hero / panels) --}}
@props(['size' => 'md'])
@php
    $sizeClass = match ($size) {
        'sm' => 'mkt-ai-orb--sm',
        'lg' => 'mkt-ai-orb--lg',
        default => 'mkt-ai-orb--md',
    };
@endphp
<div {{ $attributes->class(['mkt-ai-orb', $sizeClass]) }} aria-hidden="true">
    <span class="mkt-ai-orb__glow"></span>
    <span class="mkt-ai-orb__ring mkt-ai-orb__ring--a"></span>
    <span class="mkt-ai-orb__ring mkt-ai-orb__ring--b"></span>
    <span class="mkt-ai-orb__core">
        <span class="mkt-ai-orb__wave">
            <i style="--i:0"></i>
            <i style="--i:1"></i>
            <i style="--i:2"></i>
            <i style="--i:3"></i>
            <i style="--i:4"></i>
        </span>
    </span>
    <span class="mkt-ai-orb__nodes">
        <i></i><i></i><i></i>
    </span>
</div>
