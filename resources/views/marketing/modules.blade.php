@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('marketing_modules_title'))
@section('meta_description', __('marketing_modules_meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Modules'),
        'title' => __('marketing_modules_title'),
        'lead' => __('marketing_modules_lead'),
        'maxWidth' => 'max-w-7xl',
        'centered' => true,
    ])

    <section class="mkt-band border-b border-[rgba(15,23,42,0.06)]">
        <div class="mx-auto max-w-7xl px-6 pb-24 pt-8 sm:px-8">
            <form method="GET" id="modules-market-filter" class="mkt-glass mb-10 flex flex-wrap items-end justify-center gap-4 rounded-[18px] p-5" data-mkt-reveal>
                <div>
                    <label for="region" class="mkt-trust-label">{{ __('marketing_modules_filter_region') }}</label>
                    <select id="region" name="region" class="mkt-select mt-1.5 min-w-[12rem]" onchange="onModulesRegionChange(this)">
                        @foreach ($marketingRegions as $code => $meta)
                            <option value="{{ $code }}" @selected($selectedRegion === $code)>{{ $regionService->regionLabel($code) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="country" class="mkt-trust-label">{{ __('marketing_modules_filter_country') }}</label>
                    <select id="country" name="country" class="mkt-select mt-1.5 min-w-[12rem]" onchange="this.form.submit()">
                        <option value="">{{ __('marketing_modules_filter_country_all') }}</option>
                        @foreach ($countryOptions as $iso => $label)
                            <option value="{{ $iso }}" @selected($selectedCountry === $iso)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="category" class="mkt-trust-label">{{ __('marketing_modules_filter_category') }}</label>
                    <select id="category" name="category" class="mkt-select mt-1.5 min-w-[12rem]" onchange="this.form.submit()">
                        <option value="">{{ __('marketing_modules_filter_category_all') }}</option>
                        @foreach ($categoryOptions as $category)
                            <option value="{{ $category->value }}" @selected($selectedCategory === $category->value)>{{ $category->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="currency" class="mkt-trust-label">{{ __('Display currency') }}</label>
                    @if ($selectedRegion === 'global')
                        <select id="currency" name="currency" class="mkt-select mt-1.5" onchange="this.form.submit()">
                            @foreach ($supportedCurrencies as $code)
                                <option value="{{ $code }}" @selected($displayCurrency === $code)>{{ $currencyLabels[$code] ?? $code }}</option>
                            @endforeach
                        </select>
                    @else
                        <div id="currency" class="mt-1.5 rounded-xl border border-[rgba(91,100,140,0.14)] bg-[rgb(11,16,32)] px-3 py-2.5 text-sm font-semibold text-slate-800">
                            {{ $currencyLabels[$displayCurrency] ?? $displayCurrency }}
                        </div>
                        <input type="hidden" name="currency" value="{{ $displayCurrency }}" />
                    @endif
                </div>
            </form>

            @if ($selectedCountry || $selectedRegion !== 'global')
                <p class="mb-8 text-center text-sm text-slate-600" data-mkt-reveal>
                    {{ __('marketing_modules_filter_active', [
                        'region' => $regionService->regionLabel($selectedRegion),
                        'country' => $selectedCountry ? ($countryOptions[$selectedCountry] ?? $selectedCountry) : __('marketing_modules_filter_country_all'),
                        'currency' => $displayCurrency,
                    ]) }}
                </p>
            @endif

            @push('head')
                <script>
                    const modulesRegionCurrencies = @json(collect($marketingRegions)->mapWithKeys(fn ($meta, $code) => [$code => $meta['currency'] ?? flowdesk_default_currency()]));
                    function onModulesRegionChange(select) {
                        const form = document.getElementById('modules-market-filter');
                        const country = form.querySelector('#country');
                        if (country) {
                            country.value = '';
                        }
                        const region = select.value;
                        const currencyInput = form.querySelector('input[name="currency"]');
                        const currencySelect = form.querySelector('select#currency');
                        const code = modulesRegionCurrencies[region] || 'USD';
                        if (currencyInput) {
                            currencyInput.value = code;
                        }
                        if (currencySelect && region !== 'global') {
                            currencySelect.value = code;
                        }
                        form.submit();
                    }
                </script>
            @endpush

            @if ($grouped->isEmpty())
                <div class="mkt-cta-box mx-auto max-w-xl text-center" data-mkt-reveal>
                    <p class="text-sm font-medium text-slate-700">{{ __('marketing_modules_empty') }}</p>
                </div>
            @else
                @if ($selectedCategory === '' && $grouped->count() > 1)
                    <nav class="mb-10 flex flex-wrap justify-center gap-2" aria-label="{{ __('marketing_modules_filter_category') }}" data-mkt-reveal>
                        @foreach ($grouped as $categoryKey => $items)
                            <a
                                href="#module-category-{{ $categoryKey }}"
                                class="inline-flex items-center gap-2 rounded-full border border-[rgba(91,100,140,0.16)] bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 transition hover:border-[rgba(124,92,252,0.35)] hover:bg-[rgba(124,92,252,0.06)] hover:text-[#5b45d6]"
                            >
                                {{ $items->first()->category->label() }}
                                <span class="rounded-full bg-[rgba(124,92,252,0.1)] px-2 py-0.5 text-xs font-semibold text-[#5b45d6]">{{ $items->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif

                <div class="space-y-14">
                    @foreach ($grouped as $categoryKey => $items)
                        <section id="module-category-{{ $categoryKey }}" class="scroll-mt-24">
                            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[rgba(91,100,140,0.12)] pb-4" data-mkt-reveal>
                                <div>
                                    <span class="mkt-eyebrow">{{ __('Category') }}</span>
                                    <h2 class="mkt-display mt-2 text-2xl font-semibold text-slate-900">{{ $items->first()->category->label() }}</h2>
                                </div>
                                <p class="text-sm font-medium text-slate-500">
                                    {{ trans_choice('marketing_modules_category_count', $items->count(), ['count' => $items->count()]) }}
                                </p>
                            </div>
                            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-mkt-stagger>
                                @foreach ($items as $mod)
                                    @include('marketing.partials.module-card', [
                                        'mod' => $mod,
                                        'displayCurrency' => $displayCurrency,
                                        'catalogQuery' => array_filter([
                                            'region' => $selectedRegion,
                                            'country' => $selectedCountry,
                                            'currency' => $displayCurrency,
                                        ]),
                                    ])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif

            <div class="mkt-cta-box mx-auto mt-16 max-w-2xl text-center" data-mkt-reveal>
                <p class="text-sm text-slate-600">{{ __('marketing_modules_cta_lead') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary">{{ __('Create workspace') }}</a>
                    @endif
                    <a href="{{ route('marketing.contact') }}" class="mkt-btn mkt-btn-ghost">{{ __('Contact sales') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
