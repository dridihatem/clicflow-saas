<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-3 font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100"><x-integration-logo name="quickbooks" size="sm" /> {{ __('quickbooks_settings_title') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto w-full max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->has('quickbooks'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first('quickbooks') }}</div>
            @endif

            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('quickbooks_settings_intro') }}</p>

            <form method="POST" action="{{ route('settings.quickbooks.update') }}" class="flow-panel space-y-5 p-6">
                @csrf
                @method('PUT')
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="environment" :value="__('quickbooks_environment')" />
                        <select id="environment" name="environment" class="mt-1 block w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                            <option value="production" @selected(old('environment', $integration?->credentials['environment'] ?? 'production') === 'production')>{{ __('quickbooks_production') }}</option>
                            <option value="sandbox" @selected(old('environment', $integration?->credentials['environment'] ?? 'production') === 'sandbox')>{{ __('quickbooks_sandbox') }}</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="realm_id" :value="__('quickbooks_realm_id')" />
                        <x-text-input id="realm_id" name="realm_id" class="mt-1 w-full" :value="old('realm_id', $integration?->credentials['realm_id'] ?? '')" required />
                        <x-input-error :messages="$errors->get('realm_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="client_id" :value="__('quickbooks_client_id')" />
                        <x-text-input id="client_id" name="client_id" class="mt-1 w-full" :value="old('client_id', $integration?->credentials['client_id'] ?? '')" required />
                    </div>
                    <div>
                        <x-input-label for="client_secret" :value="__('quickbooks_client_secret')" />
                        <x-text-input id="client_secret" name="client_secret" type="password" class="mt-1 w-full" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['client_secret'] ?? null) ? '********' : '' }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="refresh_token" :value="__('quickbooks_refresh_token')" />
                        <x-text-input id="refresh_token" name="refresh_token" type="password" class="mt-1 w-full" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['refresh_token'] ?? null) ? '********' : '' }}" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('quickbooks_token_help') }}</p>
                        <x-input-error :messages="$errors->get('refresh_token')" class="mt-2" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="access_token" :value="__('quickbooks_access_token')" />
                        <x-text-input id="access_token" name="access_token" type="password" class="mt-1 w-full" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['access_token'] ?? null) ? '********' : '' }}" />
                    </div>
                </div>
                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-600/50">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('integrations_import_options') }}</h3>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_clients" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_clients', $integration?->settings['sync_clients'] ?? true))> {{ __('Clients') }}</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_invoices" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_invoices', $integration?->settings['sync_invoices'] ?? true))> {{ __('Invoices') }}</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_products" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_products', $integration?->settings['sync_products'] ?? true))> {{ __('Products') }}</label>
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200"><input type="checkbox" name="sync_proposals" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_proposals', $integration?->settings['sync_proposals'] ?? true))> {{ __('Quotes') }}</label>
                    </div>
                </div>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </form>

            @if ($integration)
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.quickbooks.test') }}">@csrf<x-secondary-button>{{ __('quickbooks_test_connection') }}</x-secondary-button></form>
                    <form method="POST" action="{{ route('settings.quickbooks.sync') }}">@csrf<x-secondary-button>{{ __('integrations_import_now') }}</x-secondary-button></form>
                    <form method="POST" action="{{ route('settings.quickbooks.disconnect') }}" onsubmit="return confirm(@js(__('quickbooks_disconnect_confirm')))">@csrf<x-secondary-button>{{ __('quickbooks_disconnect') }}</x-secondary-button></form>
                </div>
            @endif

            <div class="flow-panel p-6">
                <h2 class="mb-3 font-semibold">{{ __('Recent sync logs') }}</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($logs as $log)
                        <li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>
                    @empty
                        <li class="text-slate-500">{{ __('No logs yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
