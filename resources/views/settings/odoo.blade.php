<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-3 font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100"><x-integration-logo name="odoo" size="sm" /> {{ __('odoo_settings_title') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto w-full max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->has('odoo'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first('odoo') }}</div>
            @endif

            <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-8 shadow-xl shadow-slate-900/5 ring-1 ring-slate-900/5 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-900/50 dark:ring-white/10">
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('odoo_settings_intro') }}</p>

                <form method="POST" action="{{ route('settings.odoo.update') }}" class="mt-8 space-y-6">
                    @csrf
                    @method('PUT')

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200/80 bg-slate-50/70 px-4 py-3 dark:border-slate-600/50 dark:bg-slate-800/40">
                        <input type="checkbox" name="enabled" value="1" class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('enabled', $form['enabled']))>
                        <span>
                            <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('odoo_enable') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ __('odoo_enable_help') }}</span>
                        </span>
                    </label>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-input-label for="url" :value="__('odoo_url')" />
                            <x-text-input id="url" name="url" type="url" class="mt-1 block w-full" :value="old('url', $form['url'])" placeholder="https://mycompany.odoo.com" />
                            <x-input-error :messages="$errors->get('url')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="database" :value="__('odoo_database')" />
                            <x-text-input id="database" name="database" type="text" class="mt-1 block w-full" :value="old('database', $form['database'])" autocomplete="off" />
                            <x-input-error :messages="$errors->get('database')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="username" :value="__('odoo_username')" />
                            <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $form['username'])" autocomplete="username" />
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="api_key" :value="__('odoo_api_key')" />
                            <x-text-input id="api_key" name="api_key" type="password" class="mt-1 block w-full" value="" autocomplete="new-password" placeholder="{{ $form['has_api_key'] ? '********' : '' }}" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('odoo_api_key_help') }}</p>
                            @if ($form['has_api_key'])
                                <label class="mt-2 inline-flex items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                    <input type="checkbox" name="clear_api_key" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                    {{ __('odoo_clear_api_key') }}
                                </label>
                            @endif
                            <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                        </div>
                    </div>

                    <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-600/50">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('odoo_sync_options') }}</h3>
                        <div class="mt-3 grid gap-3 sm:grid-cols-2">
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="auto_push" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('auto_push', $form['auto_push']))>
                                {{ __('odoo_auto_push') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="sync_clients" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('sync_clients', $form['sync_clients']))>
                                {{ __('Clients') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="sync_projects" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('sync_projects', $form['sync_projects']))>
                                {{ __('Projects') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="sync_proposals" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('sync_proposals', $form['sync_proposals']))>
                                {{ __('Quotes') }}
                            </label>
                            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                <input type="checkbox" name="sync_invoices" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('sync_invoices', $form['sync_invoices']))>
                                {{ __('Invoices') }}
                            </label>
                        </div>
                    </div>

                    <x-primary-button>{{ __('odoo_save') }}</x-primary-button>
                </form>
            </div>

            @if ($form['connected'])
                <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/50">
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('odoo_connection_actions') }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('odoo_connection_actions_help') }}</p>

                    @if ($form['last_sync_at'])
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('odoo_last_sync', ['when' => $form['last_sync_at'], 'status' => $form['last_sync_status'] ?? '—']) }}
                        </p>
                        @if ($form['last_sync_message'])
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $form['last_sync_message'] }}</p>
                        @endif
                    @endif

                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('settings.odoo.test') }}">
                            @csrf
                            <x-secondary-button type="submit">{{ __('odoo_test_connection') }}</x-secondary-button>
                        </form>
                        <form method="POST" action="{{ route('settings.odoo.sync') }}">
                            @csrf
                            <input type="hidden" name="now" value="1">
                            <x-primary-button type="submit">{{ __('odoo_sync_now') }}</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('settings.odoo.disconnect') }}" onsubmit="return confirm(@js(__('odoo_disconnect_confirm')))">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-md border border-rose-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-rose-700 shadow-sm transition hover:bg-rose-50 dark:border-rose-900/50 dark:bg-slate-800 dark:text-rose-300">
                                {{ __('odoo_disconnect') }}
                            </button>
                        </form>
                    </div>

                    <div class="mt-5 grid gap-2 sm:grid-cols-4">
                        @foreach ([
                            'client' => __('Clients'),
                            'project' => __('Projects'),
                            'proposal' => __('Quotes'),
                            'invoice' => __('Invoices'),
                        ] as $key => $label)
                            <div class="rounded-xl border border-slate-200/80 px-3 py-2 text-center dark:border-slate-600/50">
                                <p class="text-lg font-semibold tabular-nums text-slate-900 dark:text-white">{{ (int) ($mapCounts[$key] ?? 0) }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
