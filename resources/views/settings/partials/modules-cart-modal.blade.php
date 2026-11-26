@php
    $cartLineItems = $cartLineItems ?? collect();
    $cartTotalMinor = $cartTotalMinor ?? 0;
    $cartCurrency = $cartCurrency ?? flowdesk_default_currency();
    $supportedCurrencies = $supportedCurrencies ?? ['USD'];
    $currencyLabels = $currencyLabels ?? [];
@endphp

<div
    x-show="cartOpen"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-0 z-[210] bg-slate-900/40 dark:bg-black/50"
    style="display: none;"
    x-on:click="closeCart()"
    aria-hidden="true"
></div>

<div
    x-show="cartOpen"
    x-transition:enter="ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
    x-transition:leave="ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
    x-transition:leave-end="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-95"
    class="fixed inset-0 z-[220] flex items-end justify-center p-4 sm:items-center"
    style="display: none;"
    role="dialog"
    aria-modal="true"
    aria-labelledby="module-cart-title"
    data-module-cart-modal
>
    <div class="flex max-h-[88vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10" x-on:click.stop>
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-200/80 bg-slate-50/90 px-5 py-3.5 dark:border-slate-700/80 dark:bg-slate-800/50">
            <div class="min-w-0">
                <h3 id="module-cart-title" class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('marketing_cart_title') }}</h3>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">{{ __('marketing_cart_lead') }}</p>
            </div>
            <button
                type="button"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/90 text-slate-600 transition hover:bg-white hover:text-slate-900 dark:border-slate-600 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                x-on:click="closeCart()"
                aria-label="{{ __('Close') }}"
            >
                <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            @if ($cartLineItems->isEmpty())
                <div class="py-8 text-center">
                    <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full bg-violet-50 text-violet-600 dark:bg-violet-950/40 dark:text-violet-200">
                        <i class="fa-solid fa-cart-shopping" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('marketing_cart_empty') }}</p>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('marketing_cart_empty_hint') }}</p>
                    <button
                        type="button"
                        class="mt-4 inline-flex items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        x-on:click="closeCart()"
                    >
                        {{ __('marketing_cart_browse_modules') }}
                    </button>
                </div>
            @else
                <form method="POST" action="{{ route('marketing.cart.currency') }}" class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-slate-200/80 bg-slate-50/80 px-3 py-2 dark:border-slate-700 dark:bg-slate-800/40">
                    @csrf
                    <label for="module-cart-currency" class="text-xs font-medium text-slate-600 dark:text-slate-300">{{ __('Display currency') }}</label>
                    <select
                        id="module-cart-currency"
                        name="currency"
                        class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        onchange="this.form.submit()"
                    >
                        @foreach ($supportedCurrencies as $code)
                            <option value="{{ $code }}" @selected($cartCurrency === $code)>{{ $currencyLabels[$code] ?? $code }}</option>
                        @endforeach
                    </select>
                </form>

                <ul class="divide-y divide-slate-200 dark:divide-slate-700">
                    @foreach ($cartLineItems as $line)
                        @php($mod = $line['module'])
                        <li class="flex items-start gap-3 py-3">
                            <span class="mt-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-200">
                                <i class="fa-solid fa-puzzle-piece text-sm" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $mod->name }}</p>
                                        <p class="mt-0.5 text-[11px] uppercase tracking-wide text-slate-500">{{ $mod->category->label() }} · {{ $mod->billing_period->label() }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ flowdesk_format_minor($line['price_minor'], $cartCurrency) }} <span class="text-[11px] font-medium text-slate-500">{{ $cartCurrency }}</span></p>
                                </div>
                                <form method="POST" action="{{ route('marketing.cart.remove') }}" class="mt-2">
                                    @csrf
                                    <input type="hidden" name="module_id" value="{{ $mod->id }}">
                                    <button type="submit" class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-800 dark:text-rose-300">
                                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                        {{ __('Remove') }}
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if ($cartLineItems->isNotEmpty())
            <div class="shrink-0 border-t border-slate-200/80 bg-slate-50/90 px-5 py-4 dark:border-slate-700/80 dark:bg-slate-800/50">
                <div class="flex items-end justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ __('Order summary') }}</p>
                        <p class="mt-1 text-xl font-bold tabular-nums text-slate-900 dark:text-white">{{ flowdesk_format_minor($cartTotalMinor, $cartCurrency) }} <span class="text-sm font-semibold text-slate-500">{{ $cartCurrency }}</span></p>
                        <p class="text-[11px] text-slate-500">{{ trans_choice('marketing_cart_items_count', $cartLineItems->count(), ['count' => $cartLineItems->count()]) }}</p>
                    </div>
                </div>
                <a href="{{ route('marketing.checkout') }}" class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700">
                    <i class="fa-solid fa-lock text-[10px]" aria-hidden="true"></i>
                    {{ __('marketing_cart_checkout') }}
                </a>
            </div>
        @endif
    </div>
</div>
