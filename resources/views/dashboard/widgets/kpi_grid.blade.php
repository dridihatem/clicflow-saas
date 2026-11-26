@props(['metrics' => [], 'nova' => null])
@php
    $byCurrency = $metrics['outstanding_by_currency'] ?? [];
    if ($byCurrency === [] && array_key_exists('outstanding_amount_minor', $metrics)) {
        $byCurrency = [($metrics['currency'] ?? flowdesk_default_currency()) => (int) ($metrics['outstanding_amount_minor'] ?? 0)];
    }
    $hasCalendar = ! empty($flowdeskCalendarNav);
    $canClients = auth()->user()?->can('workspace.manage_clients');
    $canProjects = auth()->user()?->can('workspace.manage_projects');
    $canInvoices = auth()->user()?->can('workspace.manage_invoices');
    $kpiCount = (int) $canClients + (int) $canProjects + (int) $canInvoices + (int) $canInvoices;
    $showTools = $nova || $hasCalendar;
@endphp

{{-- KPIs first — only modules the user can access --}}
@if ($canClients || $canProjects || $canInvoices)
    <div @class([
        'grid gap-3',
        'sm:grid-cols-2' => $kpiCount > 1,
        'xl:grid-cols-4' => $kpiCount >= 4,
        'xl:grid-cols-3' => $kpiCount === 3,
        'xl:grid-cols-2' => $kpiCount === 2,
    ])>
        @if ($canClients)
            <x-flow.stat-card icon="clients" :label="__('Clients')" variant="indigo">
                <span class="flowdesk-ltr-num" x-count-up="{{ (int) ($metrics['clients_count'] ?? 0) }}">{{ number_format($metrics['clients_count'] ?? 0) }}</span>
            </x-flow.stat-card>
        @endif
        @if ($canProjects)
            <x-flow.stat-card icon="projects" :label="__('Projects')" variant="cyan">
                <span class="flowdesk-ltr-num" x-count-up="{{ (int) ($metrics['projects_count'] ?? 0) }}">{{ number_format($metrics['projects_count'] ?? 0) }}</span>
            </x-flow.stat-card>
        @endif
        @if ($canInvoices)
            <x-flow.stat-card icon="invoices" :label="__('Open invoices')" variant="emerald">
                <span class="flowdesk-ltr-num" x-count-up="{{ (int) ($metrics['open_invoices_count'] ?? 0) }}">{{ number_format($metrics['open_invoices_count'] ?? 0) }}</span>
            </x-flow.stat-card>
            <x-flow.stat-card icon="wallet" :label="__('Outstanding')" variant="amber">
                @forelse ($byCurrency as $cur => $amt)
                    <span @class(['flowdesk-ltr-num tabular-nums', 'block text-lg sm:text-xl' => $loop->first, 'mt-1 block text-base font-semibold sm:text-lg' => ! $loop->first])>
                        {{ flowdesk_format_minor((int) $amt, $cur) }} {{ $cur }}
                    </span>
                @empty
                    @php $fallbackCur = flowdesk_normalize_currency_code($metrics['currency'] ?? flowdesk_default_currency()); @endphp
                    <span class="flowdesk-ltr-num tabular-nums">{{ flowdesk_format_minor(0, $fallbackCur) }} {{ $fallbackCur }}</span>
                @endforelse
                <p class="mt-2 text-xs font-normal text-slate-600 dark:text-slate-400">
                    {{ __('Paid invoices') }}: <span class="flowdesk-ltr-num">{{ number_format($metrics['paid_invoices_count'] ?? 0) }}</span>
                </p>
            </x-flow.stat-card>
        @endif
    </div>
@endif

{{-- Assistant + calendar tools --}}
@if ($showTools)
    <div @class([
        'grid gap-4',
        'mt-4' => $canClients || $canProjects || $canInvoices,
        ($nova && $hasCalendar) ? 'lg:grid-cols-2' : 'grid-cols-1',
    ])>
        @if ($nova)
            <x-ai.nova-shell :nova="$nova" compact enable-wake-word class="min-w-0">
                <x-ai.assistant-card
                    :assistant-name="$nova['assistant_name']"
                    :assistant-url="$nova['assistant_url'] ?? null"
                    compact
                />
            </x-ai.nova-shell>
        @endif

        @if ($hasCalendar)
            <x-calendar-preview-panel :preview="$flowdeskCalendarNav" :compact="true" :show-upcoming="false" />
        @endif
    </div>
@endif
