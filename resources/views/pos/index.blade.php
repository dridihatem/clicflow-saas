@php
    $currency = $currency ?? flowdesk_default_currency();
    $hasEcommerce = (bool) ($hasEcommerce ?? false);
    $productCount = (int) ($productCount ?? 0);
    $catalogJson = \Illuminate\Support\Js::from($catalog ?? []);
    $cashSales = $session ? flowdesk_format_minor($session->cash_sales_minor, $currency) : null;
    $cardSales = $session ? flowdesk_format_minor($session->card_sales_minor, $currency) : null;
@endphp

<x-app-layout>
    <div
        id="pos-register"
        class="pos-register min-h-[calc(100vh-4rem)] bg-slate-950 text-slate-100"
        x-data="posRegister({{ $catalogJson }})"
        x-ref="shell"
        @fullscreenchange.window="onFullscreenChange()"
    >
        <div class="border-b border-white/10 bg-slate-900/90 px-4 py-3 backdrop-blur sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-amber-400/90">{{ __('Retail POS') }}</p>
                    <h1 class="truncate text-xl font-semibold tracking-tight text-white sm:text-2xl">{{ __('Register') }}</h1>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if($session)
                        <div class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs text-slate-300">
                            <span class="text-slate-400">{{ __('Cash') }}</span>
                            <span class="ms-1 font-semibold tabular-nums text-emerald-300">{{ $cashSales }}</span>
                            <span class="mx-2 text-white/20">|</span>
                            <span class="text-slate-400">{{ __('Card') }}</span>
                            <span class="ms-1 font-semibold tabular-nums text-sky-300">{{ $cardSales }}</span>
                        </div>
                    @endif
                    @if($productsUrl)
                        <a href="{{ $productsUrl }}" class="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-white/10">
                            <i class="fa-solid fa-boxes-stacked me-1.5" aria-hidden="true"></i>{{ __('Products') }}
                        </a>
                    @endif
                    <button
                        type="button"
                        @click="toggleFullscreen()"
                        class="rounded-xl border border-amber-400/30 bg-amber-400/10 px-3 py-2 text-xs font-semibold text-amber-200 hover:bg-amber-400/20"
                    >
                        <i class="fa-solid fa-expand me-1.5" aria-hidden="true"></i>
                        <span x-text="fullscreen ? @js(__('Exit fullscreen')) : @js(__('Fullscreen'))"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="px-4 py-4 sm:px-6">
            @if(session('status'))
                <div class="mb-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100">{{ session('status') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-xl border border-rose-400/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100">{{ $errors->first() }}</div>
            @endif

            @unless($hasEcommerce)
                <div class="mx-auto max-w-2xl rounded-3xl border border-amber-400/25 bg-gradient-to-br from-amber-500/10 via-slate-900 to-slate-950 p-8 shadow-2xl shadow-black/40">
                    <div class="mb-4 inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-400/15 text-amber-300">
                        <i class="fa-solid fa-box-open text-lg" aria-hidden="true"></i>
                    </div>
                    <h2 class="text-2xl font-semibold text-white">{{ __('Products module required') }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-300">
                        {{ __('POS sells from your product catalog (variants + inventory). Add the E-commerce pack to unlock Products, inventory, and this register.') }}
                    </p>
                    <ul class="mt-5 space-y-2 text-sm text-slate-300">
                        <li class="flex gap-2"><i class="fa-solid fa-check mt-0.5 text-amber-400"></i>{{ __('Products & color/size variants') }}</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check mt-0.5 text-amber-400"></i>{{ __('Stock tracking on each sale') }}</li>
                        <li class="flex gap-2"><i class="fa-solid fa-check mt-0.5 text-amber-400"></i>{{ __('Fullscreen professional POS') }}</li>
                    </ul>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ $ecommerceModuleUrl }}" class="inline-flex items-center rounded-xl bg-amber-400 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-amber-300">
                            {{ __('Get E-commerce pack') }}
                        </a>
                        <a href="{{ route('settings.modules') }}" class="inline-flex items-center rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/5">
                            {{ __('Browse modules') }}
                        </a>
                    </div>
                </div>
            @else
                @if($productCount === 0 || count($catalog) === 0)
                    <div class="mb-4 rounded-2xl border border-sky-400/25 bg-sky-500/10 px-4 py-4 text-sm text-sky-50">
                        <p class="font-semibold">{{ __('No products in catalog yet') }}</p>
                        <p class="mt-1 text-sky-100/80">{{ __('Create products and variants (with stock) before selling in POS.') }}</p>
                        @if($createProductUrl)
                            <a href="{{ $createProductUrl }}" class="mt-3 inline-flex rounded-lg bg-sky-400 px-3 py-1.5 text-xs font-bold text-slate-950 hover:bg-sky-300">{{ __('Add product') }}</a>
                        @endif
                    </div>
                @endif

                @unless($session)
                    <div class="mx-auto max-w-lg rounded-3xl border border-white/10 bg-white/5 p-6 shadow-xl shadow-black/30">
                        <h2 class="text-lg font-semibold text-white">{{ __('Open register') }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ __('Open a register session to start selling.') }}</p>
                        <form method="POST" action="{{ route('pos.open') }}" class="mt-5 space-y-4">
                            @csrf
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">
                                {{ __('Opening cash') }}
                                <input name="opening_cash" type="number" step="0.01" value="0" min="0"
                                       class="mt-1.5 w-full rounded-xl border-white/10 bg-slate-950/60 text-white focus:border-amber-400 focus:ring-amber-400/30" />
                            </label>
                            <button type="submit" class="w-full rounded-xl bg-amber-400 px-4 py-3 text-sm font-bold text-slate-950 hover:bg-amber-300">
                                {{ __('Open session') }}
                            </button>
                        </form>
                    </div>
                @else
                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.9fr)]">
                        <section class="rounded-3xl border border-white/10 bg-white/[0.03] p-4 sm:p-5">
                            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Catalog') }}</p>
                                    <p class="text-sm text-slate-300">{{ __('Tap a variant to add it to the cart') }}</p>
                                </div>
                                <label class="relative block w-full max-w-sm">
                                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute start-3 top-1/2 -translate-y-1/2 text-slate-500"></i>
                                    <input
                                        type="search"
                                        x-model="query"
                                        placeholder="{{ __('Search name, SKU, color, size…') }}"
                                        class="w-full rounded-xl border-white/10 bg-slate-950/70 py-2.5 ps-9 pe-3 text-sm text-white placeholder:text-slate-500 focus:border-amber-400 focus:ring-amber-400/30"
                                    />
                                </label>
                            </div>

                            <div class="grid max-h-[65vh] gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                                <template x-for="item in filtered" :key="item.id">
                                    <button
                                        type="button"
                                        @click="addItem(item)"
                                        class="rounded-2xl border border-white/10 bg-slate-900/70 p-3 text-start transition hover:border-amber-400/40 hover:bg-slate-900"
                                        :disabled="item.track_stock && item.stock < 1"
                                        :class="item.track_stock && item.stock < 1 ? 'opacity-40 cursor-not-allowed' : ''"
                                    >
                                        <p class="line-clamp-2 text-sm font-semibold text-white" x-text="item.product || item.label"></p>
                                        <p class="mt-1 text-xs text-slate-400" x-text="[item.color, item.size, item.sku].filter(Boolean).join(' · ') || item.label"></p>
                                        <div class="mt-3 flex items-center justify-between gap-2">
                                            <span class="text-sm font-bold tabular-nums text-amber-300" x-text="item.price_label + ' ' + @js($currency)"></span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                                  :class="item.track_stock ? (item.stock > 0 ? 'bg-emerald-500/15 text-emerald-300' : 'bg-rose-500/15 text-rose-300') : 'bg-slate-500/20 text-slate-300'"
                                                  x-text="item.track_stock ? (@js(__('Stock')) + ' ' + item.stock) : @js(__('No stock track'))"></span>
                                        </div>
                                    </button>
                                </template>
                                <p x-show="filtered.length === 0" class="col-span-full py-10 text-center text-sm text-slate-500">{{ __('No matching products.') }}</p>
                            </div>
                        </section>

                        <aside class="rounded-3xl border border-white/10 bg-gradient-to-b from-slate-900 to-slate-950 p-4 sm:p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h2 class="text-lg font-semibold text-white">{{ __('Cart') }}</h2>
                                <button type="button" @click="clearCart()" class="text-xs font-semibold text-slate-400 hover:text-rose-300" x-show="cart.length">{{ __('Clear') }}</button>
                            </div>

                            <div class="max-h-[40vh] space-y-2 overflow-y-auto">
                                <template x-for="(line, index) in cart" :key="line.id">
                                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-white" x-text="line.label"></p>
                                                <p class="text-xs text-slate-400" x-text="line.price_label + ' ' + @js($currency)"></p>
                                            </div>
                                            <button type="button" @click="removeLine(index)" class="text-slate-500 hover:text-rose-300"><i class="fa-solid fa-xmark"></i></button>
                                        </div>
                                        <div class="mt-2 flex items-center gap-2">
                                            <button type="button" @click="changeQty(index, -1)" class="h-8 w-8 rounded-lg border border-white/10 text-sm font-bold hover:bg-white/10">−</button>
                                            <span class="min-w-[2rem] text-center text-sm font-semibold tabular-nums" x-text="line.qty"></span>
                                            <button type="button" @click="changeQty(index, 1)" class="h-8 w-8 rounded-lg border border-white/10 text-sm font-bold hover:bg-white/10">+</button>
                                            <span class="ms-auto text-sm font-bold tabular-nums text-amber-300" x-text="formatMinor(line.price_minor * line.qty)"></span>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="cart.length === 0" class="py-8 text-center text-sm text-slate-500">{{ __('Cart is empty') }}</p>
                            </div>

                            <div class="mt-4 border-t border-white/10 pt-4">
                                <div class="mb-3 flex items-center justify-between text-sm">
                                    <span class="text-slate-400">{{ __('Total') }}</span>
                                    <span class="text-xl font-bold tabular-nums text-white" x-text="formatMinor(cartTotalMinor()) + ' ' + @js($currency)"></span>
                                </div>

                                <form method="POST" action="{{ route('pos.sell') }}" @submit="prepareSellForm($event)">
                                    @csrf
                                    <template x-for="(line, index) in cart" :key="'h-'+line.id">
                                        <div>
                                            <input type="hidden" :name="'lines['+index+'][variant_id]'" :value="line.id">
                                            <input type="hidden" :name="'lines['+index+'][quantity]'" :value="line.qty">
                                        </div>
                                    </template>

                                    <div class="mb-3 grid grid-cols-2 gap-2">
                                        <label class="cursor-pointer rounded-xl border px-3 py-2.5 text-center text-sm font-semibold"
                                               :class="payment === 'cash' ? 'border-amber-400 bg-amber-400/15 text-amber-200' : 'border-white/10 text-slate-300'">
                                            <input type="radio" name="payment_method" value="cash" class="sr-only" x-model="payment">
                                            {{ __('Cash') }}
                                        </label>
                                        <label class="cursor-pointer rounded-xl border px-3 py-2.5 text-center text-sm font-semibold"
                                               :class="payment === 'card' ? 'border-sky-400 bg-sky-400/15 text-sky-200' : 'border-white/10 text-slate-300'">
                                            <input type="radio" name="payment_method" value="card" class="sr-only" x-model="payment">
                                            {{ __('Card') }}
                                        </label>
                                    </div>

                                    <label class="mb-4 flex items-center gap-2 text-sm text-slate-300">
                                        <input type="checkbox" name="create_invoice" value="1" checked class="rounded border-white/20 bg-slate-900 text-amber-400 focus:ring-amber-400/40">
                                        {{ __('Create invoice') }}
                                    </label>

                                    <button
                                        type="submit"
                                        class="w-full rounded-xl bg-emerald-400 px-4 py-3 text-sm font-bold text-slate-950 hover:bg-emerald-300 disabled:cursor-not-allowed disabled:opacity-40"
                                        :disabled="cart.length === 0"
                                    >
                                        {{ __('Complete sale') }}
                                    </button>
                                </form>

                                <form method="POST" action="{{ route('pos.close', $session) }}" class="mt-4 border-t border-white/10 pt-4">
                                    @csrf
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        {{ __('Closing cash') }}
                                        <input name="closing_cash" type="number" step="0.01" value="0" min="0"
                                               class="mt-1.5 w-full rounded-xl border-white/10 bg-slate-950/60 text-white focus:border-amber-400 focus:ring-amber-400/30" />
                                    </label>
                                    <button type="submit" class="mt-3 w-full rounded-xl border border-white/15 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-white/5">
                                        {{ __('Close session') }}
                                    </button>
                                </form>

                                <p class="mt-3 text-[11px] leading-relaxed text-slate-500">
                                    {{ __('Session open since') }} {{ $session->opened_at?->format('Y-m-d H:i') }}
                                </p>
                            </div>
                        </aside>
                    </div>
                @endunless
            @endunless
        </div>
    </div>

    @push('styles')
        <style>
            #pos-register:fullscreen,
            #pos-register:-webkit-full-screen {
                overflow: auto;
                background: #020617;
                min-height: 100vh;
            }
        </style>
    @endpush

    @push('scripts')
        <script>
            window.posRegister = function posRegister(catalog) {
                return {
                    catalog: Array.isArray(catalog) ? catalog : [],
                    query: '',
                    cart: [],
                    payment: 'cash',
                    fullscreen: false,
                    get filtered() {
                        const q = this.query.trim().toLowerCase();
                        if (!q) return this.catalog;
                        return this.catalog.filter((item) => {
                            const hay = [item.label, item.product, item.sku, item.color, item.size]
                                .filter(Boolean)
                                .join(' ')
                                .toLowerCase();
                            return hay.includes(q);
                        });
                    },
                    addItem(item) {
                        if (item.track_stock && item.stock < 1) return;
                        const existing = this.cart.find((line) => line.id === item.id);
                        if (existing) {
                            if (item.track_stock && existing.qty >= item.stock) return;
                            existing.qty += 1;
                            return;
                        }
                        this.cart.push({
                            id: item.id,
                            label: item.label,
                            price_minor: item.price_minor,
                            price_label: item.price_label,
                            stock: item.stock,
                            track_stock: item.track_stock,
                            qty: 1,
                        });
                    },
                    changeQty(index, delta) {
                        const line = this.cart[index];
                        if (!line) return;
                        const next = line.qty + delta;
                        if (next < 1) {
                            this.cart.splice(index, 1);
                            return;
                        }
                        if (line.track_stock && next > line.stock) return;
                        line.qty = next;
                    },
                    removeLine(index) {
                        this.cart.splice(index, 1);
                    },
                    clearCart() {
                        this.cart = [];
                    },
                    cartTotalMinor() {
                        return this.cart.reduce((sum, line) => sum + (line.price_minor * line.qty), 0);
                    },
                    formatMinor(minor) {
                        const value = (Number(minor) || 0) / 100;
                        try {
                            return new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value);
                        } catch (e) {
                            return value.toFixed(2);
                        }
                    },
                    prepareSellForm(event) {
                        if (this.cart.length === 0) {
                            event.preventDefault();
                        }
                    },
                    async toggleFullscreen() {
                        const el = this.$refs.shell;
                        if (!el) return;
                        try {
                            if (!document.fullscreenElement) {
                                await el.requestFullscreen();
                            } else {
                                await document.exitFullscreen();
                            }
                        } catch (e) {
                            // Browser blocked fullscreen — keep register usable.
                        }
                        this.onFullscreenChange();
                    },
                    onFullscreenChange() {
                        this.fullscreen = Boolean(document.fullscreenElement);
                    },
                };
            };
        </script>
    @endpush
</x-app-layout>
