@props(['metrics' => [], 'dashboardChart' => []])
@php
    $chart = is_array($dashboardChart) ? $dashboardChart : [];
    $chartPayload = [
        'labels' => $chart['labels'] ?? [],
        'counts' => $chart['counts'] ?? [],
        'paidMinor' => $chart['paidMinor'] ?? [],
        'paymentsByChannel' => $chart['paymentsByChannel'] ?? ['labels' => [], 'amounts_minor' => []],
        'minorScale' => (int) ($chart['minorScale'] ?? 100),
    ];
@endphp
<div
    id="flowdesk-dashboard-charts-root"
    class="grid gap-4 lg:grid-cols-3"
    data-chart='@json($chartPayload)'
    data-label-invoices="{{ __('Completed payments') }}"
    data-label-paid="{{ __('Payment volume') }}"
    data-label-channel="{{ __('By channel') }}"
>
    <div class="flow-dashboard-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-flow-text">{{ __('Payments per month') }}</h4>
            <a href="{{ route('analytics.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Analytics') }} →</a>
        </div>
        <p class="mt-1 text-xs text-flow-text-muted">{{ __('Last six months — completed payments only.') }}</p>
        <div class="mt-4 h-56 w-full">
            <canvas id="chart-dashboard-invoices"></canvas>
        </div>
    </div>
    <div class="flow-dashboard-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-flow-text">{{ __('Payment volume') }}</h4>
            <a href="{{ route('analytics.index', ['report' => 'revenue']) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Revenue report') }} →</a>
        </div>
        <p class="mt-1 text-xs text-flow-text-muted">{{ __('Totals from completed payment records.') }}</p>
        <div class="mt-4 h-56 w-full">
            <canvas id="chart-dashboard-revenue"></canvas>
        </div>
    </div>
    <div class="flow-dashboard-panel p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-semibold text-flow-text">{{ __('Payments by channel') }}</h4>
            <a href="{{ route('reports.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Reports') }} →</a>
        </div>
        <p class="mt-1 text-xs text-flow-text-muted">{{ __('All-time share by gateway (payments only).') }}</p>
        <div class="mt-4 h-56 w-full max-w-sm">
            <canvas id="chart-dashboard-payments-channel"></canvas>
        </div>
    </div>
</div>
