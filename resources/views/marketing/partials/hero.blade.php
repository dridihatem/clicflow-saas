@php
    $heroCentered = $centered ?? true;
    $heroMax = $maxWidth ?? 'max-w-7xl';
@endphp
<section class="relative overflow-hidden border-b border-[rgba(124,92,252,0.08)]" data-mkt-hero>
    <div class="pointer-events-none absolute -end-24 -top-10 h-80 w-80 rounded-full bg-[rgba(124,92,252,0.12)] blur-3xl" data-mkt-parallax="40" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -start-24 bottom-0 h-64 w-64 rounded-full bg-[rgba(38,232,212,0.1)] blur-3xl" data-mkt-parallax="28" aria-hidden="true"></div>
    <div class="pointer-events-none absolute inset-x-0 top-0 h-1 mkt-gradient-bar" aria-hidden="true"></div>

    <div @class(['relative mx-auto px-6 py-16 sm:px-8 lg:py-20', $heroMax, 'text-center' => $heroCentered])>
        @if (! empty($eyebrow))
            <span class="mkt-eyebrow" data-mkt-hero-item>{{ $eyebrow }}</span>
        @endif
        <h1 class="mkt-display mt-4 text-4xl font-semibold tracking-tight text-slate-900 sm:text-5xl" data-mkt-hero-item>
            {{ $title }}@if (! empty($titleAccent)) <span class="mkt-accent">{{ $titleAccent }}</span>@endif
        </h1>
        @if (! empty($meta))
            <p class="mt-2 text-sm text-slate-500" data-mkt-hero-item>{{ $meta }}</p>
        @endif
        @if (! empty($lead))
            <p @class(['mt-5 max-w-2xl text-lg leading-relaxed text-slate-600', 'mx-auto' => $heroCentered]) data-mkt-hero-item>{{ $lead }}</p>
        @endif
        @if (! empty($sub))
            <p @class(['mt-3 max-w-2xl text-sm leading-relaxed text-slate-500', 'mx-auto' => $heroCentered]) data-mkt-hero-item>{{ $sub }}</p>
        @endif
    </div>
</section>
