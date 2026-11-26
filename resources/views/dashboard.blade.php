@php
    $widgetMap = collect($widgets ?? [])->keyBy('key');
    $show = static fn (string $key): bool => (bool) ($widgetMap->get($key)['enabled'] ?? false);
    $company = auth()->user()?->company;
    $userName = auth()->user()?->name;
    $hour = (int) now()->format('G');
    $greeting = $hour < 12
        ? __('Good morning')
        : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
    $widgetViewData = [
        'metrics' => $metrics ?? [],
        'dashboardChart' => $dashboardChart ?? [],
        'nova' => $nova ?? null,
    ];
    $showCompanySnapshot = $show('company_cards') || $show('metrics_table');
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="flow-font-display text-lg font-semibold leading-tight text-flow-text">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="flow-page-shell flow-dashboard">
        @if ($show('registration_notice'))
            <div class="mb-6 flow-dashboard-enter" style="--fd-enter-delay: 0ms">
                @include('dashboard.widgets.registration_notice', $widgetViewData)
            </div>
        @endif

        {{-- Simple greeting + actions --}}
        <header class="flow-dashboard-header mb-6 flow-dashboard-enter" style="--fd-enter-delay: 40ms">
            <div class="min-w-0">
                <p class="cf-eyebrow">
                    {{ $company?->name ?? __('Workspace') }}
                    @if ($company?->subdomain)
                        <span class="font-mono font-normal normal-case tracking-normal opacity-80"> · {{ $company->subdomain }}</span>
                    @endif
                </p>
                <h1 class="cf-page-title mt-1 text-2xl sm:text-3xl">
                    {{ $greeting }}{{ $userName ? ', '.$userName : '' }}
                </h1>
            </div>

            <nav class="flow-dashboard-header__actions" aria-label="{{ __('Dashboard') }}">
                <button
                    type="button"
                    class="flow-dashboard-chip"
                    onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'product-tour' }))"
                >
                    <x-cf-icon name="fa-compass" size="sm" />
                    {{ __('product_tour_open_short') }}
                </button>
                @can('workspace.manage_clients')
                    @if (Route::has('clients.index'))
                        <a href="{{ route('clients.index') }}" class="flow-dashboard-chip">
                            <x-cf-icon name="fa-users" size="sm" />
                            {{ __('Clients') }}
                        </a>
                    @endif
                @endcan
                @can('workspace.manage_projects')
                    @if (Route::has('projects.index'))
                        <a href="{{ route('projects.index') }}" class="flow-dashboard-chip">
                            <x-cf-icon name="fa-folder-open" size="sm" />
                            {{ __('Projects') }}
                        </a>
                    @endif
                @endcan
                @can('workspace.manage_invoices')
                    @if (Route::has('invoices.index'))
                        <a href="{{ route('invoices.index') }}" class="flow-dashboard-chip">
                            <x-cf-icon name="fa-file-invoice-dollar" size="sm" />
                            {{ __('Invoices') }}
                        </a>
                    @endif
                @endcan
                @can('workspace.hr_self_service')
                    @if (($flowdeskPlanGates['hr'] ?? false) && Route::has('hr.me.leave'))
                        <a href="{{ route('hr.me.leave') }}" class="flow-dashboard-chip">
                            <x-cf-icon name="fa-plane-departure" size="sm" />
                            {{ __('hr_my_leave') }}
                        </a>
                    @endif
                @elsecan('workspace.manage_hr')
                    @if (($flowdeskPlanGates['hr'] ?? false) && Route::has('hr.index'))
                        <a href="{{ route('hr.index') }}" class="flow-dashboard-chip">
                            <x-cf-icon name="fa-people-group" size="sm" />
                            {{ __('HR & Payroll') }}
                        </a>
                    @endif
                @endcan
                @can('workspace.manage_projects')
                    @if (Route::has('projects.create'))
                        <a href="{{ route('projects.create') }}" class="flow-dashboard-chip flow-dashboard-chip--accent">
                            <x-cf-icon name="fa-plus" size="sm" />
                            {{ __('New project') }}
                        </a>
                    @endif
                @endcan
            </nav>
        </header>

        @if ($show('kpi_grid'))
            <div class="mb-6 flow-dashboard-enter" style="--fd-enter-delay: 80ms">
                @include('dashboard.widgets.kpi_grid', $widgetViewData)
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-12 xl:items-start">
            <div class="space-y-6 xl:col-span-8">
                @if ($show('projects_pipeline'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 120ms">
                        @include('dashboard.widgets.projects_pipeline', $widgetViewData)
                    </div>
                @endif

                @if ($show('provider_commissions'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 150ms">
                        @include('dashboard.widgets.provider_commissions', $widgetViewData)
                    </div>
                @endif

                @if ($show('dashboard_charts'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 180ms">
                        <div class="mb-3">
                            <h2 class="flow-dashboard-section__title">{{ __('Insights') }}</h2>
                            <p class="flow-dashboard-section__lead">{{ __('Payment trends and channel mix.') }}</p>
                        </div>
                        @include('dashboard.widgets.dashboard_charts', $widgetViewData)
                    </div>
                @endif

                @if ($show('usage_overview'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 210ms">
                        <div class="mb-3">
                            <h2 class="flow-dashboard-section__title">{{ __('billing_usage_overview') }}</h2>
                            <p class="flow-dashboard-section__lead">{{ __('Snapshot of workspace activity and plan usage.') }}</p>
                        </div>
                        @include('dashboard.widgets.usage_overview', $widgetViewData)
                    </div>
                @endif
            </div>

            <aside class="space-y-6 xl:col-span-4">
                @if ($showCompanySnapshot)
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 130ms">
                        @include('dashboard.widgets.company_cards', $widgetViewData)
                    </div>
                @endif

                @if ($show('ai_assistant'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 190ms">
                        @include('dashboard.widgets.ai_assistant', $widgetViewData)
                    </div>
                @endif

                @if ($show('vue_pulse'))
                    <div class="flow-dashboard-enter" style="--fd-enter-delay: 220ms">
                        @include('dashboard.widgets.vue_pulse', $widgetViewData)
                    </div>
                @endif
            </aside>
        </div>
    </div>

    @push('scripts')
        @vite('resources/js/dashboard-charts.js')
    @endpush
</x-app-layout>
