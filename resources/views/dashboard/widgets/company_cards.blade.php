@props(['metrics' => []])
@php
    $company = auth()->user()?->company;
    $canClients = auth()->user()?->can('workspace.manage_clients');
    $canProjects = auth()->user()?->can('workspace.manage_projects');
    $initial = $company?->name ? mb_strtoupper(mb_substr($company->name, 0, 1)) : '?';
@endphp

<div class="flow-dashboard-panel overflow-hidden">
    <div class="flex items-start gap-3 border-b border-flow-border px-5 py-4">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-500/10 text-sm font-bold text-indigo-600 dark:bg-indigo-400/15 dark:text-indigo-300">
            {{ $initial }}
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="truncate text-sm font-semibold text-flow-text">{{ $company?->name ?? __('Company') }}</p>
                <x-flow.badge variant="success">{{ __('dashboard_status_session_active') }}</x-flow.badge>
            </div>
            @if ($company?->subdomain)
                <p class="mt-0.5 truncate font-mono text-xs text-flow-text-muted">{{ $company->subdomain }}</p>
            @endif
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-px bg-flow-border/60 sm:grid-cols-2">
        @if ($company)
            <div class="bg-flow-surface px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-flow-text-muted">{{ __('Default currency') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-flow-text">{{ $company->default_currency }}</dd>
            </div>
            <div class="bg-flow-surface px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-flow-text-muted">{{ __('Locale') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-flow-text">{{ app()->getLocale() }}</dd>
            </div>
            @if ($company->country)
                <div class="bg-flow-surface px-4 py-3 @if (! $canClients && ! $canProjects) col-span-2 @endif">
                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-flow-text-muted">{{ __('Country') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-flow-text">{{ $company->country }}</dd>
                </div>
            @endif
        @endif
        @if ($canClients)
            <div class="bg-flow-surface px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-flow-text-muted">{{ __('Clients') }}</dt>
                <dd class="mt-1 text-sm font-semibold tabular-nums text-flow-text">
                    <span class="flowdesk-ltr-num">{{ number_format($metrics['clients_count'] ?? 0) }}</span>
                </dd>
            </div>
        @endif
        @if ($canProjects)
            <div class="bg-flow-surface px-4 py-3">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-flow-text-muted">{{ __('Projects') }}</dt>
                <dd class="mt-1 text-sm font-semibold tabular-nums text-flow-text">
                    <span class="flowdesk-ltr-num">{{ number_format($metrics['projects_count'] ?? 0) }}</span>
                </dd>
            </div>
        @endif
    </dl>
</div>
