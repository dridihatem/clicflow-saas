@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('marketing_cart_title'))
@section('meta_description', __('marketing_cart_meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Cart'),
        'title' => __('marketing_cart_title'),
        'lead' => __('marketing_cart_lead'),
        'maxWidth' => 'max-w-7xl',
        'centered' => true,
    ])

    <section class="border-b border-slate-200 bg-[rgb(11,16,32)]">
        <div class="mx-auto max-w-7xl px-6 py-12 pb-24 sm:px-10 lg:px-12">
            <div class="mb-8 flex flex-wrap items-center justify-end gap-4">
                <a href="{{ route('settings.modules') }}" class="mkt-btn mkt-btn-ghost !px-4 !py-2">
                    <i class="fa-solid fa-arrow-left text-xs text-[#EA580C]" aria-hidden="true"></i>
                    {{ __('marketing_cart_browse_modules') }}
                </a>
            </div>

            @if (session('status'))
                <div class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            @if ($lineItems->isEmpty())
                <div class="mkt-cta-box mx-auto max-w-xl px-8 py-16 text-center" data-mkt-reveal>
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[rgba(234,88,12,0.1)] text-[#EA580C]">
                        <i class="fa-solid fa-cart-shopping text-2xl" aria-hidden="true"></i>
                    </div>
                    <p class="mkt-display mt-5 text-base font-semibold text-slate-800">{{ __('marketing_cart_empty') }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ __('marketing_cart_empty_hint') }}</p>
                    <a href="{{ route('settings.modules') }}" class="mkt-btn mkt-btn-primary mt-6">
                        <i class="fa-solid fa-puzzle-piece text-xs" aria-hidden="true"></i>
                        {{ __('marketing_cart_browse_modules') }}
                    </a>
                </div>
            @else
                <div class="grid gap-8 lg:grid-cols-3">
                    <div class="space-y-4 lg:col-span-2">
                        <form method="POST" action="{{ route('marketing.cart.currency') }}" class="flex flex-wrap items-center justify-between gap-3 mkt-glass rounded-xl px-4 py-3">
                            @csrf
                            <p class="text-sm font-medium text-slate-700">
                                <i class="fa-solid fa-coins me-1.5 text-slate-400" aria-hidden="true"></i>
                                {{ __('Display currency') }}
                            </p>
                            <select id="currency" name="currency" class="rounded-lg border border-slate-200 bg-[rgb(11,16,32)] px-3 py-2 text-sm font-medium text-slate-800" onchange="this.form.submit()">
                                @foreach ($supportedCurrencies as $code)
                                    <option value="{{ $code }}" @selected($currency === $code)>{{ $currencyLabels[$code] ?? $code }}</option>
                                @endforeach
                            </select>
                        </form>

                        <ul class="space-y-3">
                            @foreach ($lineItems as $line)
                                @php($mod = $line['module'])
                                <li class="flex gap-4 mkt-feature-card !p-4 sm:p-5">
                                    @if ($mod->imageUrl())
                                        <img src="{{ $mod->imageUrl() }}" alt="" class="h-16 w-16 shrink-0 rounded-xl border border-slate-100 object-cover" />
                                    @else
                                        <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-[rgba(234,88,12,0.1)] text-[#5b45d6]">
                                            @include('marketing.partials.feature-icon', ['name' => $mod->icon ?: 'puzzle'])
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <span class="font-semibold text-slate-900">{{ $mod->name }}</span>
                                                <p class="mt-0.5 text-xs font-medium uppercase tracking-wide text-slate-500">{{ $mod->category->label() }} · {{ $mod->billing_period->label() }}</p>
                                            </div>
                                            <p class="text-lg font-bold tabular-nums text-slate-900">{{ flowdesk_format_minor($line['price_minor'], $currency) }} <span class="text-sm font-semibold text-slate-500">{{ $currency }}</span></p>
                                        </div>
                                        <form method="POST" action="{{ route('marketing.cart.remove') }}" class="mt-3">
                                            @csrf
                                            <input type="hidden" name="module_id" value="{{ $mod->id }}">
                                            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-600 transition hover:text-rose-800">
                                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                                                {{ __('Remove') }}
                                            </button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="lg:col-span-1">
                        <div class="sticky top-24 mkt-glass rounded-[20px] p-6">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Order summary') }}</h2>
                            <p class="mt-4 text-3xl font-bold tabular-nums text-slate-900">{{ flowdesk_format_minor($totalMinor, $currency) }} <span class="text-base font-semibold text-slate-500">{{ $currency }}</span></p>
                            <p class="mt-1 text-xs text-slate-500">{{ trans_choice('marketing_cart_items_count', $lineItems->count(), ['count' => $lineItems->count()]) }}</p>

                            <a href="{{ route('marketing.checkout') }}" class="mkt-btn mkt-btn-primary mt-6 w-full justify-center">
                                <i class="fa-solid fa-lock text-xs" aria-hidden="true"></i>
                                {{ __('marketing_cart_checkout') }}
                            </a>
                            <a href="{{ route('settings.modules') }}" class="mkt-btn mkt-btn-ghost mt-3 w-full justify-center">
                                <i class="fa-solid fa-puzzle-piece text-xs" aria-hidden="true"></i>
                                {{ __('Continue shopping') }}
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
@endsection
