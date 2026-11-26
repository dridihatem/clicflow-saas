@php
    $integration = $integration ?? null;
    $logs = $logs ?? collect();
@endphp

<div class="space-y-5">
    @if ($errors->has('perfex'))
        <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first('perfex') }}</div>
    @endif

    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('perfex_settings_intro') }}</p>

    <form method="POST" action="{{ route('settings.perfex.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <x-input-label for="perfex_base_url" :value="__('perfex_base_url')" />
            <x-text-input id="perfex_base_url" name="base_url" type="url" class="mt-1 w-full" :value="old('base_url', $integration?->credentials['base_url'] ?? '')" placeholder="https://crm.example.com" required />
            <x-input-error :messages="$errors->get('base_url')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="perfex_api_token" :value="__('perfex_api_token')" />
            <x-text-input id="perfex_api_token" name="api_token" type="password" class="mt-1 w-full" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['api_token'] ?? null) ? '********' : '' }}" />
            <p class="mt-1 text-xs text-slate-500">{{ __('perfex_api_token_help') }}</p>
            <x-input-error :messages="$errors->get('api_token')" class="mt-2" />
        </div>
        <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-600/50">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('integrations_import_options') }}</h3>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_prospects" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_prospects', $integration?->settings['sync_prospects'] ?? true))> {{ __('Prospects') }}</label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_clients" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_clients', $integration?->settings['sync_clients'] ?? true))> {{ __('Clients') }}</label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_invoices" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_invoices', $integration?->settings['sync_invoices'] ?? true))> {{ __('Invoices') }}</label>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_products" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_products', $integration?->settings['sync_products'] ?? true))> {{ __('Products') }}</label>
            </div>
        </div>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </form>

    @if ($integration)
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('settings.perfex.test') }}">@csrf<x-secondary-button>{{ __('perfex_test_connection') }}</x-secondary-button></form>
            <form method="POST" action="{{ route('settings.perfex.sync') }}">@csrf<x-secondary-button>{{ __('integrations_import_now') }}</x-secondary-button></form>
            <form method="POST" action="{{ route('settings.perfex.disconnect') }}" onsubmit="return confirm(@js(__('perfex_disconnect_confirm')))">@csrf<x-secondary-button>{{ __('perfex_disconnect') }}</x-secondary-button></form>
        </div>
    @endif

    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Recent sync logs') }}</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($logs as $log)
                <li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>
            @empty
                <li class="text-slate-500">{{ __('No logs yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
