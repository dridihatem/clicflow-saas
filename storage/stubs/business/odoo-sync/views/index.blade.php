@php
    $gates = $flowdeskPlanGates ?? [];
@endphp
<div class="space-y-6">
    <div class="flow-panel p-6 space-y-3">
        <h2 class="text-lg font-semibold">{{ __('Odoo sync companion') }}</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Connect your Odoo database (JSON-RPC) to keep clients, quotes, invoices, and projects in sync.') }}</p>
        <ol class="list-decimal ps-5 text-sm space-y-1 text-slate-600 dark:text-slate-300">
            <li>{{ __('Activate Odoo sync for free from the modules catalog or Settings → Modules') }}</li>
            <li>{{ __('Open Settings → Odoo and enter your URL, database, user, and API key') }}</li>
            <li>{{ __('Test the connection, then sync clients, quotes, invoices, and projects') }}</li>
        </ol>
        @if ($gates['odoo'] ?? false)
            <a href="{{ route('settings.odoo') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('Open Odoo settings') }}</a>
        @else
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Activate the free Odoo sync module to unlock Settings → Odoo.') }}</p>
        @endif
    </div>
</div>
