@props([
    'href',
    'icon',
    'title',
    'description',
    'tone' => 'orange',
    'delay' => 0,
])

@php
    $toneMap = [
        'bg-orange-500/12 text-orange-700 dark:text-orange-300' => 'orange',
        'bg-violet-500/12 text-violet-700 dark:text-violet-300' => 'violet',
        'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300' => 'emerald',
        'bg-teal-500/12 text-teal-700 dark:text-teal-300' => 'teal',
        'bg-amber-500/12 text-amber-700 dark:text-amber-300' => 'amber',
        'bg-sky-500/12 text-sky-700 dark:text-sky-300' => 'sky',
        'bg-slate-500/12 text-slate-700 dark:text-slate-300' => 'slate',
        'bg-rose-500/12 text-rose-700 dark:text-rose-300' => 'rose',
        'bg-blue-500/12 text-blue-700 dark:text-blue-300' => 'blue',
        'bg-red-500/12 text-red-700 dark:text-red-300' => 'red',
    ];
    $toneName = $toneMap[$tone] ?? (in_array($tone, array_values($toneMap), true) ? $tone : 'orange');
@endphp

<a href="{{ $href }}" class="portal-nav-card portal-enter" style="--portal-delay: {{ (int) $delay }}ms" {{ $attributes }}>
    <x-cf-icon :name="$icon" variant="tile" :tone="$toneName" size="xl" class="portal-nav-card__icon" />
    <span class="portal-nav-card__arrow text-slate-400" aria-hidden="true">
        <x-cf-icon name="arrow-right" size="sm" />
    </span>
    <p class="mt-4 text-sm font-semibold tracking-tight text-slate-900 dark:text-white">{{ $title }}</p>
    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $description }}</p>
</a>
