@props(['metrics' => []])
@php
    $pc = $metrics['provider_commissions'] ?? null;
@endphp

@if (($flowdeskPlanGates['providers'] ?? true) && is_array($pc) && ($pc['provider_count'] ?? 0) > 0)
    @php $currency = $pc['currency'] ?? flowdesk_default_currency(); @endphp
    <section class="flow-dashboard-panel overflow-hidden" aria-labelledby="dashboard-providers-heading">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-flow-border bg-flow-surface-muted/40 px-5 py-4">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-violet-500/10 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400">
                    <i class="fa-solid fa-handshake text-sm" aria-hidden="true"></i>
                </div>
                <div>
                    <h3 id="dashboard-providers-heading" class="text-sm font-semibold tracking-tight text-flow-text">{{ __('dashboard_provider_commissions_title') }}</h3>
                    <p class="mt-0.5 text-xs text-flow-text-muted">{{ __('dashboard_provider_commissions_lead') }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 text-xs font-semibold">
                <a href="{{ route('providers.remittance-requests.index') }}" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('provider_remittance_inbox_title') }} →</a>
                <a href="{{ route('providers.index') }}" class="text-flow-text-muted hover:text-flow-text">{{ __('view_providers') }} →</a>
            </div>
        </div>

        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-flow.stat-card :label="__('provider_stat_commission_total')" variant="indigo">
                {{ flowdesk_format_minor((int) ($pc['commission_total_minor'] ?? 0), $currency) }} {{ $currency }}
            </x-flow.stat-card>
            <x-flow.stat-card :label="__('provider_stat_remitted')" variant="emerald">
                {{ flowdesk_format_minor((int) ($pc['remitted_minor'] ?? 0), $currency) }} {{ $currency }}
            </x-flow.stat-card>
            <x-flow.stat-card :label="__('provider_stat_pending_remittance')" variant="amber">
                {{ flowdesk_format_minor((int) ($pc['pending_remittance_minor'] ?? 0), $currency) }} {{ $currency }}
            </x-flow.stat-card>
            <x-flow.stat-card :label="__('provider_stat_balance_due')" variant="cyan">
                {{ flowdesk_format_minor((int) ($pc['balance_due_minor'] ?? 0), $currency) }} {{ $currency }}
            </x-flow.stat-card>
        </div>

        @php $pendingRequests = collect($pc['pending_requests'] ?? []); @endphp
        @if ($pendingRequests->isNotEmpty())
            <div class="border-t border-flow-border px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h4 class="text-sm font-semibold text-flow-text">{{ __('provider_pending_payment_requests') }}</h4>
                    <a href="{{ route('providers.remittance-requests.index', ['status' => 'pending']) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('view_all') }} →</a>
                </div>
                <ul class="mt-3 divide-y divide-flow-border">
                    @foreach ($pendingRequests as $request)
                        <li class="flex flex-col gap-2 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-flow-text">
                                    {{ $request->provider?->name ?? '—' }}
                                    <span class="ms-2 font-mono text-sm tabular-nums text-indigo-700 dark:text-indigo-300">{{ flowdesk_format_minor((int) $request->amount_minor, $currency) }} {{ $currency }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-flow-text-muted">{{ $request->created_at?->format('Y-m-d H:i') }} · {{ $request->payment_method?->label() }}</p>
                            </div>
                            @if ($request->provider)
                                <a href="{{ route('providers.edit', $request->provider) }}" class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                                    {{ __('Review') }}
                                    <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                                </a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>
@endif
