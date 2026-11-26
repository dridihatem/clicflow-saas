@props(['label', 'variant' => 'indigo', 'icon' => null, 'tone' => null])
@php
    $accents = [
        'indigo' => ['icon' => 'bg-orange-50 text-orange-700', 'bar' => 'bg-orange-500'],
        'cyan' => ['icon' => 'bg-sky-50 text-sky-700', 'bar' => 'bg-sky-500'],
        'emerald' => ['icon' => 'bg-emerald-50 text-emerald-700', 'bar' => 'bg-emerald-500'],
        'amber' => ['icon' => 'bg-amber-50 text-amber-800', 'bar' => 'bg-amber-500'],
    ];
    $a = $accents[$variant] ?? $accents['indigo'];
    $toneByVariant = ['indigo' => 'orange', 'cyan' => 'sky', 'emerald' => 'emerald', 'amber' => 'amber'];
    $iconTone = $tone ?? ($toneByVariant[$variant] ?? 'orange');
@endphp
<div {{ $attributes->merge(['class' => 'cf-kpi cf-enter group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm shadow-slate-900/5 transition duration-200 hover:-translate-y-1 hover:shadow-lg hover:shadow-orange-500/10 dark:border-slate-700 dark:bg-slate-900']) }}>
    <span class="absolute inset-y-0 start-0 w-1 {{ $a['bar'] }}" aria-hidden="true"></span>
    <div class="flex items-start justify-between gap-3">
        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-slate-500 dark:text-slate-400">{{ $label }}</p>
        @if ($icon)
            <x-cf-icon :name="$icon" variant="tile" :tone="$iconTone" class="cf-stat-tile" />
        @endif
    </div>
    <div class="mt-2 text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
        {{ $slot }}
    </div>
</div>
