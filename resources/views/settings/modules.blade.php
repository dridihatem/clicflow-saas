<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('settings_modules_title') }}</h2>
    </x-slot>

    @php
        $moduleIconMap = [
            'building' => 'fa-building',
            'doc' => 'fa-file-lines',
            'bolt' => 'fa-bolt',
            'chart' => 'fa-chart-column',
            'mail' => 'fa-envelope',
            'users' => 'fa-users',
            'calendar' => 'fa-calendar',
            'shield' => 'fa-shield',
            'palette' => 'fa-palette',
            'puzzle' => 'fa-puzzle-piece',
            'coins' => 'fa-coins',
            'chat' => 'fa-comments',
            'portal' => 'fa-user',
            'ticket' => 'fa-ticket',
        ];
        $catalogByCategory = $catalogByCategory ?? collect();
        $catalog = $catalog ?? collect();
        $openCart = (bool) ($openCart ?? false);
        $cartCount = (int) ($cartCount ?? 0);
    @endphp

    <div class="py-10">
        <div
            class="mx-auto w-full max-w-12xl space-y-5 sm:px-6 lg:px-8"
            x-data="{
                query: '',
                cartOpen: @js($openCart),
                matches(text) {
                    const q = this.query.trim().toLowerCase();
                    if (!q) return true;
                    return String(text || '').toLowerCase().includes(q);
                },
                sectionHasVisible(refName) {
                    if (!this.query.trim()) return true;
                    const root = this.$refs[refName];
                    if (!root) return false;
                    return [...root.querySelectorAll('[data-module-search-row]')].some((el) => el.offsetParent !== null);
                },
                openCart() {
                    this.cartOpen = true;
                    const url = new URL(window.location.href);
                    url.searchParams.set('cart', '1');
                    history.replaceState({}, '', url);
                },
                closeCart() {
                    this.cartOpen = false;
                    const url = new URL(window.location.href);
                    url.searchParams.delete('cart');
                    history.replaceState({}, '', url);
                },
            }"
            x-on:keydown.escape.window="cartOpen && closeCart()"
        >
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">
                    {{ session('error') }}
                </div>
            @endif

            <div class="relative">
                <label for="module-search" class="sr-only">{{ __('settings_modules_search_label') }}</label>
                <i class="fa-solid fa-magnifying-glass pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-sm text-slate-400" aria-hidden="true"></i>
                <input
                    id="module-search"
                    type="search"
                    x-model="query"
                    placeholder="{{ __('settings_modules_search_placeholder') }}"
                    class="block w-full rounded-xl border border-slate-200 bg-white py-2.5 ps-10 pe-3 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:placeholder:text-slate-500"
                    autocomplete="off"
                />
            </div>

            <div class="grid items-start gap-5 lg:grid-cols-12">
                <div class="space-y-5 lg:col-span-4">
                    @if ($canManage)
                        <div class="flow-panel p-5">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('settings_modules_upload_heading') }}</h3>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('settings_modules_upload_help') }}</p>
                            <form method="post" action="{{ route('settings.modules.install') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="module_zip" :value="__('settings_modules_zip_label')" />
                                    <input
                                        id="module_zip"
                                        name="module_zip"
                                        type="file"
                                        accept=".zip,application/zip"
                                        required
                                        class="mt-1 block w-full text-xs text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-indigo-950/50 dark:file:text-indigo-200"
                                    />
                                    <x-input-error :messages="$errors->get('module_zip')" class="mt-2" />
                                </div>
                                <x-primary-button class="!px-3 !py-2 !text-[11px]">{{ __('settings_modules_install_button') }}</x-primary-button>
                            </form>
                            <details class="mt-3 rounded-lg border border-slate-200/80 bg-slate-50/80 p-3 dark:border-slate-600/50 dark:bg-slate-800/30">
                                <summary class="cursor-pointer text-xs font-semibold text-slate-800 dark:text-slate-100">{{ __('settings_modules_zip_structure') }}</summary>
                                <pre class="mt-2 overflow-x-auto rounded-lg bg-slate-900 p-3 text-[10px] text-slate-100"><code>my-module/
├── module.json
├── views/
└── database/migrations/</code></pre>
                            </details>
                        </div>
                    @endif

                    <div class="flow-panel p-5">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('settings_modules_installed_heading') }}</h3>
                        @if ($modules->isEmpty())
                            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('settings_modules_empty') }}</p>
                        @else
                            <ul x-ref="installedModules" class="mt-3 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($modules as $mod)
                                    @php
                                        $integrations = collect($mod->manifest['integrations'] ?? [])->filter()->keys()->take(6);
                                        $aiModes = collect($mod->manifest['ai']['modes'] ?? [])->filter()->take(4);
                                        $moduleSearch = strtolower(implode(' ', array_filter([$mod->name, $mod->slug, $mod->version, $mod->author, $mod->description, $integrations->implode(' '), $aiModes->implode(' ')])));
                                    @endphp
                                    <li
                                        data-module-search-row
                                        x-show="matches(@js($moduleSearch))"
                                        class="flex items-start gap-2 py-3"
                                    >
                                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-300">
                                            <i class="fa-solid fa-puzzle-piece text-xs" aria-hidden="true"></i>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $mod->name }}</p>
                                            <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ $mod->slug }} · v{{ $mod->version }}</p>
                                            @if ($integrations->isNotEmpty())
                                                <p class="mt-1 flex flex-wrap gap-1">
                                                    @foreach ($integrations as $key)
                                                        <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $key }}</span>
                                                    @endforeach
                                                </p>
                                            @endif
                                            @if ($aiModes->isNotEmpty())
                                                <p class="mt-1 text-[11px] text-indigo-600 dark:text-indigo-400">Nova: {{ $aiModes->implode(', ') }}</p>
                                            @endif
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @if ($mod->is_enabled)
                                                    <a href="{{ route('modules.show', $mod->slug) }}" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('settings_modules_open') }}</a>
                                                @endif
                                                <form method="post" action="{{ route('settings.modules.toggle', $mod) }}">
                                                    @csrf
                                                    @method('patch')
                                                    <input type="hidden" name="enabled" value="{{ $mod->is_enabled ? '0' : '1' }}">
                                                    <button type="submit" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[10px] font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                                                        {{ $mod->is_enabled ? __('settings_modules_disable') : __('settings_modules_enable') }}
                                                    </button>
                                                </form>
                                                @if ($canManage)
                                                    <form method="post" action="{{ route('settings.modules.destroy', $mod) }}" onsubmit="return confirm(@json(__('settings_modules_uninstall_confirm')))">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-semibold text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">{{ __('settings_modules_uninstall') }}</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <p x-cloak x-show="query.trim() && !sectionHasVisible('installedModules')" class="mt-3 text-xs text-slate-500">{{ __('settings_modules_search_empty') }}</p>
                        @endif
                    </div>

                    @if ($purchasedModules->isNotEmpty())
                        <div class="flow-panel p-5">
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('settings_modules_purchased_heading') }}</h3>
                            <ul x-ref="purchasedModules" class="mt-3 divide-y divide-slate-200 dark:divide-slate-700">
                                @foreach ($purchasedModules as $purchase)
                                    @php
                                        $purchaseSearch = strtolower(implode(' ', array_filter([
                                            $purchase['item']->module_name ?? '',
                                            $purchase['module']?->slug ?? '',
                                            $purchase['module']?->name ?? '',
                                        ])));
                                    @endphp
                                    <li data-module-search-row x-show="matches(@js($purchaseSearch))" class="flex items-start justify-between gap-2 py-3">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $purchase['item']->module_name }}</p>
                                            <p class="text-[11px] text-slate-500">{{ __('Order') }} {{ $purchase['order']->order_number }}</p>
                                            @if (! empty($purchase['is_core']))
                                                <span class="mt-1 inline-flex rounded-full bg-sky-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800 dark:bg-sky-900/40 dark:text-sky-200">{{ __('settings_modules_purchased_core_badge') }}</span>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-wrap justify-end gap-1">
                                            @if (! empty($purchase['settings_route']) && \Illuminate\Support\Facades\Route::has($purchase['settings_route']))
                                                <a href="{{ route($purchase['settings_route']) }}" class="rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white">{{ __('settings_modules_open') }}</a>
                                            @elseif ($canManage && ! $purchase['is_installed'] && $purchase['module']?->zip_path)
                                                <form method="post" action="{{ route('settings.modules.purchased.install', $purchase['module']) }}">
                                                    @csrf
                                                    <button type="submit" class="rounded-md bg-indigo-600 px-2 py-1 text-[10px] font-semibold text-white">{{ __('settings_modules_marketplace_install') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="min-w-0 space-y-5 lg:col-span-8">
                    <div class="flow-panel p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('settings_modules_marketplace_heading') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('settings_modules_marketplace_help') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-violet-700"
                                x-on:click="openCart()"
                                data-module-cart-open
                            >
                                <i class="fa-solid fa-cart-shopping text-[10px]" aria-hidden="true"></i>
                                {{ __('settings_modules_marketplace_cart', ['count' => $cartCount ?? 0]) }}
                            </button>
                        </div>

                    </div>

                    @if ($catalogByCategory->isEmpty())
                        <div class="flow-panel p-6">
                            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('settings_modules_marketplace_empty') }}</p>
                        </div>
                    @else
                        <div x-ref="marketplaceModules" class="space-y-5">
                            @foreach ($catalogByCategory as $categoryKey => $items)
                                @php
                                    $category = \App\Enums\MarketplaceModuleCategory::tryFrom((string) $categoryKey)
                                        ?? \App\Enums\MarketplaceModuleCategory::General;
                                @endphp
                                <section class="flow-panel p-5">
                                    <div class="mb-3 flex items-center gap-2">
                                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-200">
                                            <i class="fa-solid {{ $category->icon() }}" aria-hidden="true"></i>
                                        </span>
                                        <div>
                                            <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ $category->label() }}</h4>
                                            <p class="text-[11px] text-slate-500">{{ trans_choice('marketing_modules_category_count', $items->count(), ['count' => $items->count()]) }}</p>
                                        </div>
                                    </div>

                                    <div
                                        class="relative"
                                        x-data="{
                                            canPrev: false,
                                            canNext: true,
                                            sync() {
                                                const el = this.$refs.track;
                                                if (!el) return;
                                                this.canPrev = el.scrollLeft > 6;
                                                this.canNext = el.scrollLeft + el.clientWidth < el.scrollWidth - 6;
                                            },
                                            move(dir) {
                                                this.$refs.track?.scrollBy({ left: dir * 240, behavior: 'smooth' });
                                            },
                                        }"
                                        x-init="$nextTick(() => sync())"
                                    >
                                        <button
                                            type="button"
                                            class="absolute start-0 top-1/2 z-10 hidden h-8 w-8 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm disabled:opacity-30 sm:inline-flex dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                            @click="move(-1)"
                                            :disabled="!canPrev"
                                            aria-label="{{ __('settings_modules_carousel_prev') }}"
                                        >
                                            <i class="fa-solid fa-chevron-left text-[10px]" aria-hidden="true"></i>
                                        </button>
                                        <div
                                            x-ref="track"
                                            @scroll.debounce.50ms="sync()"
                                            class="flex gap-3 overflow-x-auto scroll-smooth pb-1 [-ms-overflow-style:none] [scrollbar-width:none] snap-x snap-mandatory [&::-webkit-scrollbar]:hidden"
                                        >
                                            @foreach ($items as $item)
                                                @php
                                                    $mod = $item['module'];
                                                    $catalogSearch = strtolower(implode(' ', array_filter([
                                                        $mod->name,
                                                        $mod->slug,
                                                        $mod->description,
                                                        $category->label(),
                                                    ])));
                                                    $cardIcon = $moduleIconMap[$mod->icon ?? ''] ?? $category->icon();
                                                @endphp
                                                <article
                                                    data-module-search-row
                                                    x-show="matches(@js($catalogSearch))"
                                                    class="flex w-56 shrink-0 snap-start flex-col rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/70"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-200">
                                                            <i class="fa-solid {{ $cardIcon }} text-sm" aria-hidden="true"></i>
                                                        </span>
                                                        @if ($item['is_installed'] || ($item['is_core'] && $item['is_owned']))
                                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ __('settings_modules_purchased_installed_badge') }}</span>
                                                        @elseif ($item['is_free'])
                                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200">{{ __('marketing_modules_price_free') }}</span>
                                                        @else
                                                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $mod->formattedDisplayPrice($mod->currency ?: flowdesk_default_currency()) }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $mod->name }}</p>
                                                    @if ($mod->description)
                                                        <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $mod->description }}</p>
                                                    @endif
                                                    <div class="mt-auto pt-3">
                                                        @if (! empty($item['settings_route']))
                                                            <a href="{{ route($item['settings_route']) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-700">{{ __('settings_modules_open_settings') }}</a>
                                                        @elseif ($item['is_installed'] && ! empty($item['open_route']) && \Illuminate\Support\Facades\Route::has($item['open_route'][0]))
                                                            <a href="{{ route($item['open_route'][0], $item['open_route'][1]) }}" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-700">{{ __('settings_modules_open') }}</a>
                                                        @elseif ($canManage && $item['is_owned'] && ! $item['is_installed'] && ! $item['is_core'] && $mod->zip_path)
                                                            <form method="post" action="{{ route('settings.modules.purchased.install', $mod) }}">
                                                                @csrf
                                                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-indigo-700">{{ __('settings_modules_purchased_install') }}</button>
                                                            </form>
                                                        @elseif ($canManage && $item['is_free'] && ! $item['is_owned'])
                                                            <form method="post" action="{{ route('settings.modules.free.activate', $mod) }}">
                                                                @csrf
                                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-emerald-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-emerald-700">
                                                                    <i class="fa-solid fa-download text-[10px]" aria-hidden="true"></i>
                                                                    {{ __('settings_modules_marketplace_install') }}
                                                                </button>
                                                            </form>
                                                        @elseif ($canManage && ! $item['is_owned'])
                                                            <form method="post" action="{{ route('marketing.cart.add', $mod) }}">
                                                                @csrf
                                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 rounded-lg bg-violet-600 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-violet-700">
                                                                    <i class="fa-solid fa-cart-plus text-[10px]" aria-hidden="true"></i>
                                                                    {{ __('settings_modules_marketplace_buy') }}
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </article>
                                            @endforeach
                                        </div>
                                        <button
                                            type="button"
                                            class="absolute end-0 top-1/2 z-10 hidden h-8 w-8 translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 shadow-sm disabled:opacity-30 sm:inline-flex dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                            @click="move(1)"
                                            :disabled="!canNext"
                                            aria-label="{{ __('settings_modules_carousel_next') }}"
                                        >
                                            <i class="fa-solid fa-chevron-right text-[10px]" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                </section>
                            @endforeach
                            <p x-cloak x-show="query.trim() && !sectionHasVisible('marketplaceModules')" class="text-sm text-slate-500">{{ __('settings_modules_search_empty') }}</p>
                        </div>
                    @endif
                </div>
            </div>

            @include('settings.partials.modules-cart-modal')
        </div>
    </div>
</x-app-layout>
