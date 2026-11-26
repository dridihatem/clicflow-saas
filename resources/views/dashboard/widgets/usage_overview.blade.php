@props(['metrics' => []])
@php
    $usage = is_array($metrics['usage_overview'] ?? null) ? $metrics['usage_overview'] : [];
    $currency = flowdesk_normalize_currency_code($usage['currency'] ?? ($metrics['currency'] ?? flowdesk_default_currency()));
    $chart = is_array($usage['chart'] ?? null) ? $usage['chart'] : ['labels' => [], 'values' => []];
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <x-flow.stat-card :label="__('Paid revenue (30d)')" variant="emerald" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ flowdesk_format_minor((int) ($usage['invoice_revenue_minor'] ?? 0), $currency) }}</span>
        </x-flow.stat-card>
        <x-flow.stat-card :label="__('Commission (12mo)')" variant="amber" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ flowdesk_format_minor((int) ($usage['commission_total_minor'] ?? 0), $currency) }}</span>
        </x-flow.stat-card>
        <x-flow.stat-card :label="__('Open proposals')" variant="cyan" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ number_format((int) ($usage['proposal_pipeline'] ?? 0)) }}</span>
        </x-flow.stat-card>
        <x-flow.stat-card :label="__('AI credits (this month)')" variant="indigo" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ number_format((int) ($usage['ai_credits_used'] ?? 0)) }}</span>
        </x-flow.stat-card>
        <x-flow.stat-card :label="__('Form submissions (this month)')" variant="cyan" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ number_format((int) ($usage['form_submissions_month'] ?? 0)) }}</span>
        </x-flow.stat-card>
        <x-flow.stat-card :label="__('Estimated pay-per-use (AI)')" variant="indigo" class="!p-4">
            <span class="flowdesk-ltr-num text-lg sm:text-xl">{{ flowdesk_format_minor((int) ($usage['pay_per_use_estimate_minor'] ?? 0), $currency) }}</span>
            <p class="mt-2 text-xs font-normal text-slate-600 dark:text-slate-400">{{ __('ai_pay_per_use_hint', ['rate' => (int) ($usage['ai_credit_price_minor'] ?? 1)]) }}</p>
        </x-flow.stat-card>
    </div>

    <div
        class="flow-dashboard-panel p-5"
        id="flowdesk-usage-overview-chart-root"
        data-usage-chart='@json($chart)'
        data-label-usage="{{ __('billing_usage_overview') }}"
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-flow-text">{{ __('Activity mix') }}</h4>
            @if (Route::has('billing.index') && auth()->user()?->can('workspace.manage_subscription'))
                <a href="{{ route('billing.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Plan subscription') }} →</a>
            @endif
        </div>
        <p class="mt-1 text-xs text-flow-text-muted">{{ __('Proposals, AI credits, and form submissions at a glance.') }}</p>
        <div class="mt-4 h-64 w-full">
            <canvas id="chart-dashboard-usage"></canvas>
        </div>
    </div>
</div>
