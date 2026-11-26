<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ __('integrations_hub_title') }}</h2>
    </x-slot>

    <div
        class="py-10"
        x-data="{
            open: false,
            current: @js($configureKey),
            titles: @js($sheetTitles),
            init() {
                if (this.current) {
                    this.open = true;
                }
            },
            show(key) {
                this.current = key;
                this.open = true;
                const url = new URL(window.location.href);
                url.searchParams.set('configure', key);
                history.replaceState({}, '', url);
            },
            close() {
                this.open = false;
                const url = new URL(window.location.href);
                url.searchParams.delete('configure');
                history.replaceState({}, '', url);
            },
            titleFor(key) {
                return this.titles[key] || '';
            },
        }"
        @keydown.escape.window="open && close()"
    >
        <div class="mx-auto w-full max-w-12xl space-y-6 sm:px-6 lg:px-8">
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('integrations_hub_intro') }}</p>

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($cards as $card)
                    <div class="flow-panel flex flex-col p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-start gap-3">
                                <x-integration-logo :name="$card['key']" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $card['title'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $card['summary'] }}</p>
                                </div>
                            </div>
                            @if ($card['connected'] && $card['available'])
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-200">{{ __('integrations_connected') }}</span>
                            @elseif (! $card['available'])
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">{{ __('integrations_requires_module') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ __('integrations_not_connected') }}</span>
                            @endif
                        </div>

                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($card['imports'] as $import)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $import }}</span>
                            @endforeach
                        </div>

                        @if ($card['last_synced_at'])
                            <p class="mt-3 text-[11px] text-slate-500 dark:text-slate-400">{{ __('integrations_last_sync', ['when' => $card['last_synced_at']]) }}</p>
                        @endif

                        <div class="mt-5 flex flex-wrap gap-2">
                            @if ($card['available'])
                                <x-primary-button type="button" class="!normal-case" x-on:click="show({{ \Illuminate\Support\Js::from($card['key']) }})">{{ __('integrations_configure') }}</x-primary-button>
                            @else
                                <a href="{{ route('settings.modules') }}">
                                    <x-secondary-button type="button" class="!normal-case">{{ __('settings_modules_title') }}</x-secondary-button>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div
            x-show="open"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[210] bg-slate-900/40 dark:bg-black/50"
            style="display: none;"
            @click="close()"
            aria-hidden="true"
        ></div>

        <div
            x-show="open"
            x-transition:enter="transform transition ease-out duration-300"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transform transition ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            class="fixed inset-y-0 end-0 z-[220] flex w-full max-w-xl flex-col bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10"
            style="display: none;"
            role="dialog"
            aria-modal="true"
            data-integration-sheet
            @click.stop
        >
            <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 bg-slate-50/90 px-4 py-3 dark:border-slate-700/80 dark:bg-slate-800/50">
                <div class="flex min-w-0 items-center gap-3">
                    <span x-show="current === 'perfex'" x-cloak><x-integration-logo name="perfex" size="sm" /></span>
                    <span x-show="current === 'shopify'" x-cloak><x-integration-logo name="shopify" size="sm" /></span>
                    <span x-show="current === 'woocommerce'" x-cloak><x-integration-logo name="woocommerce" size="sm" /></span>
                    <span x-show="current === 'odoo'" x-cloak><x-integration-logo name="odoo" size="sm" /></span>
                    <span x-show="current === 'quickbooks'" x-cloak><x-integration-logo name="quickbooks" size="sm" /></span>
                    <span x-show="current === 'trello'" x-cloak><x-integration-logo name="trello" size="sm" /></span>
                    <span x-show="current === 'notion'" x-cloak><x-integration-logo name="notion" size="sm" /></span>
                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100" x-text="titleFor(current)"></p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/90 text-slate-600 transition hover:bg-white hover:text-slate-900 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                    @click="close()"
                    aria-label="{{ __('Close panel') }}"
                >
                    <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-5">
                @if (session('status'))
                    <div class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
                @endif

                <div x-show="current === 'perfex'" x-cloak>
                    @include('settings.partials.integrations.perfex', [
                        'integration' => $integrations->get('perfex'),
                        'logs' => $logs->get('perfex', collect()),
                    ])
                </div>
                <div x-show="current === 'shopify'" x-cloak>
                    @include('settings.partials.integrations.shopify', [
                        'integration' => $integrations->get('shopify'),
                        'logs' => $logs->get('shopify', collect()),
                    ])
                </div>
                <div x-show="current === 'woocommerce'" x-cloak>
                    @include('settings.partials.integrations.woocommerce', [
                        'integration' => $integrations->get('woocommerce'),
                        'logs' => $logs->get('woocommerce', collect()),
                    ])
                </div>
                <div x-show="current === 'odoo'" x-cloak>
                    @include('settings.partials.integrations.odoo', [
                        'form' => $odooForm,
                        'mapCounts' => $odooMapCounts,
                    ])
                </div>
                <div x-show="current === 'quickbooks'" x-cloak>
                    @include('settings.partials.integrations.quickbooks', [
                        'integration' => $integrations->get('quickbooks'),
                        'logs' => $logs->get('quickbooks', collect()),
                    ])
                </div>
                <div x-show="current === 'trello'" x-cloak>
                    @include('settings.partials.integrations.trello', [
                        'integration' => $integrations->get('trello'),
                        'logs' => $logs->get('trello', collect()),
                    ])
                </div>
                <div x-show="current === 'notion'" x-cloak>
                    @include('settings.partials.integrations.notion', [
                        'integration' => $integrations->get('notion'),
                        'logs' => $logs->get('notion', collect()),
                    ])
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
