@props([
    'summary' => [],
    'compact' => false, // kept for callers; layout is amount-first for both modes
])

@php
    $growth = $summary['growth_percent'] ?? null;
    $canClients = auth()->user()?->can('workspace.manage_clients');
    $canProjects = auth()->user()?->can('workspace.manage_projects');
    $canInvoices = auth()->user()?->can('workspace.manage_invoices');
    $assistantName = $summary['assistant_name'] ?? config('app.name').' '.config('flowdesk.ai_assistant_brand_name', 'Nova');

    $featured = null;
    $metrics = [];

    if ($canInvoices) {
        $featured = [
            'label' => __('nova_monthly_revenue'),
            'value' => $summary['monthly_revenue_formatted'] ?? '—',
            'icon' => 'fa-chart-line',
            'tone' => 'text-emerald-600 dark:text-emerald-300 bg-emerald-500/10',
        ];
    }

    if ($canClients) {
        $metrics[] = [
            'label' => __('Clients'),
            'value' => number_format($summary['clients_count'] ?? 0),
            'icon' => 'fa-users',
            'tone' => 'text-sky-600 dark:text-sky-300 bg-sky-500/10',
        ];
    }
    if ($canProjects) {
        $metrics[] = [
            'label' => __('nova_active_projects'),
            'value' => number_format($summary['active_projects'] ?? 0),
            'icon' => 'fa-diagram-project',
            'tone' => 'text-teal-600 dark:text-teal-300 bg-teal-500/10',
        ];
    }
    if ($canInvoices) {
        $metrics[] = [
            'label' => __('nova_unpaid_invoices'),
            'value' => number_format($summary['unpaid_invoices'] ?? 0),
            'icon' => 'fa-file-invoice-dollar',
            'tone' => 'text-amber-600 dark:text-amber-300 bg-amber-500/10',
        ];
    }

    $metricCols = match (count($metrics)) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-2',
        default => 'grid-cols-3',
    };
@endphp

<div {{ $attributes->merge(['class' => 'nova-summary portal-enter p-5 sm:p-6']) }}>
    <div class="pointer-events-none absolute -end-10 -top-10 h-36 w-36 rounded-full bg-sky-400/20 blur-2xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -bottom-12 -start-8 h-32 w-32 rounded-full bg-teal-400/15 blur-2xl" aria-hidden="true"></div>

    <div class="relative flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
            <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500 to-teal-500 text-white shadow-lg shadow-sky-500/25">
                <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
                <span class="nova-summary__pulse absolute -end-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-emerald-400 ring-2 ring-white dark:ring-slate-900"></span>
            </span>
            <div class="min-w-0">
                <h3 class="text-base font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('nova_summary_title') }}</h3>
                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $assistantName }}</p>
            </div>
        </div>
        @isset($summary['assistant_url'])
            <a
                href="{{ $summary['assistant_url'] }}"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-sky-200/80 bg-white/80 px-2.5 py-1.5 text-xs font-semibold text-sky-700 shadow-sm transition hover:-translate-y-0.5 hover:border-sky-300 hover:bg-sky-50 dark:border-sky-800/60 dark:bg-slate-900/60 dark:text-sky-300 dark:hover:bg-sky-950/40"
            >
                {{ __('nova_open_full') }}
                <i class="fa-solid fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        @endisset
    </div>

    <div class="relative mt-5 space-y-3">
        @if ($featured)
            <div class="nova-summary__metric nova-summary__metric--featured" style="--portal-delay: 60ms">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $featured['label'] }}</p>
                        <p class="nova-summary__amount mt-1.5 font-bold tracking-tight text-slate-900 dark:text-white flowdesk-ltr-num">
                            {{ $featured['value'] }}
                        </p>
                    </div>
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl {{ $featured['tone'] }}">
                        <i class="fa-solid {{ $featured['icon'] }} text-sm" aria-hidden="true"></i>
                    </span>
                </div>
                @if ($growth !== null)
                    <div class="mt-3 inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/70 px-2.5 py-1 text-xs dark:border-slate-700/70 dark:bg-slate-900/50">
                        <span class="font-medium text-slate-500 dark:text-slate-400">{{ __('nova_growth_label') }}</span>
                        <span @class([
                            'inline-flex items-center gap-1 font-semibold tabular-nums',
                            'text-emerald-600 dark:text-emerald-400' => $growth >= 0,
                            'text-rose-600 dark:text-rose-400' => $growth < 0,
                        ])>
                            <i class="fa-solid {{ $growth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-[10px]" aria-hidden="true"></i>
                            {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                        </span>
                    </div>
                @endif
            </div>
        @elseif ($growth !== null)
            <div class="inline-flex items-center gap-2 rounded-full border border-slate-200/80 bg-white/70 px-3 py-1 text-xs dark:border-slate-700/70 dark:bg-slate-900/50">
                <span class="font-medium text-slate-500 dark:text-slate-400">{{ __('nova_growth_label') }}</span>
                <span @class([
                    'inline-flex items-center gap-1 font-semibold tabular-nums',
                    'text-emerald-600 dark:text-emerald-400' => $growth >= 0,
                    'text-rose-600 dark:text-rose-400' => $growth < 0,
                ])>
                    <i class="fa-solid {{ $growth >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' }} text-[10px]" aria-hidden="true"></i>
                    {{ $growth >= 0 ? '+' : '' }}{{ $growth }}%
                </span>
            </div>
        @endif

        @if ($metrics !== [])
            <div class="grid gap-3 {{ $metricCols }}">
                @foreach ($metrics as $i => $metric)
                    <div class="nova-summary__metric" style="--portal-delay: {{ 110 + ($i * 45) }}ms">
                        <div class="flex items-center justify-between gap-2">
                            <p class="min-w-0 truncate text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $metric['label'] }}</p>
                            <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $metric['tone'] }}">
                                <i class="fa-solid {{ $metric['icon'] }} text-[11px]" aria-hidden="true"></i>
                            </span>
                        </div>
                        <p class="mt-2 text-2xl font-bold tabular-nums tracking-tight text-slate-900 dark:text-white flowdesk-ltr-num">{{ $metric['value'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @if (! empty($summary['recommendations']))
        <div class="relative mt-5 border-t border-slate-200/70 pt-4 dark:border-slate-700/70">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('nova_recommendations') }}</p>
            <ul class="mt-3 space-y-2">
                @foreach ($summary['recommendations'] as $tip)
                    <li class="flex gap-2.5 rounded-xl border border-transparent bg-white/50 px-3 py-2 text-sm text-slate-700 transition hover:border-sky-200/70 hover:bg-sky-50/60 dark:bg-slate-900/40 dark:text-slate-300 dark:hover:border-sky-800/50 dark:hover:bg-sky-950/30">
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-sky-500/15 text-[10px] text-sky-600 dark:text-sky-300">
                            <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        </span>
                        <span>{{ $tip }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
