@props([
    'tone' => 'slate',
    'icon' => null,
    'title' => null,
    'hint' => null,
])

@php
    $tone = in_array($tone, ['indigo', 'violet', 'amber', 'emerald', 'sky', 'rose', 'slate'], true) ? $tone : 'slate';
    $shell = match ($tone) {
        'indigo' => 'border-indigo-200/80 bg-gradient-to-br from-indigo-50/95 via-white to-white dark:border-indigo-900/40 dark:from-indigo-950/35 dark:via-slate-900/50 dark:to-slate-900/40',
        'violet' => 'border-violet-200/80 bg-gradient-to-br from-violet-50/95 via-white to-white dark:border-violet-900/40 dark:from-violet-950/35 dark:via-slate-900/50 dark:to-slate-900/40',
        'amber' => 'border-amber-200/80 bg-gradient-to-br from-amber-50/95 via-white to-white dark:border-amber-900/40 dark:from-amber-950/30 dark:via-slate-900/50 dark:to-slate-900/40',
        'emerald' => 'border-emerald-200/80 bg-gradient-to-br from-emerald-50/95 via-white to-white dark:border-emerald-900/40 dark:from-emerald-950/30 dark:via-slate-900/50 dark:to-slate-900/40',
        'sky' => 'border-sky-200/80 bg-gradient-to-br from-sky-50/95 via-white to-white dark:border-sky-900/40 dark:from-sky-950/30 dark:via-slate-900/50 dark:to-slate-900/40',
        'rose' => 'border-rose-200/80 bg-gradient-to-br from-rose-50/90 via-white to-white dark:border-rose-900/40 dark:from-rose-950/25 dark:via-slate-900/50 dark:to-slate-900/40',
        default => 'border-slate-200/90 bg-white dark:border-slate-700/80 dark:bg-slate-900/40',
    };
    $iconWrap = match ($tone) {
        'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/70 dark:text-indigo-200',
        'violet' => 'bg-violet-100 text-violet-700 dark:bg-violet-950/70 dark:text-violet-200',
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-200',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-200',
        'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/70 dark:text-sky-200',
        'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/70 dark:text-rose-200',
        default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
    };
@endphp

<section {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border shadow-sm '.$shell]) }}>
    @if ($title || $icon)
        <div class="flex items-start gap-3 px-5 pt-4 pb-1">
            @if ($icon)
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $iconWrap }}">
                    <i class="{{ $icon }} text-sm" aria-hidden="true"></i>
                </span>
            @endif
            <div class="min-w-0">
                @if ($title)
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                @endif
                @if ($hint)
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</p>
                @endif
            </div>
        </div>
    @endif
    <div @class(['px-5 pb-5', 'pt-3' => ! ($title || $icon), 'pt-2' => $title || $icon])>
        {{ $slot }}
    </div>
</section>
