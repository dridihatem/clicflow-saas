@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('Pricing'))
@section('meta_description', __('marketing_pricing_meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Pricing'),
        'title' => __('Plans for every workspace'),
        'lead' => __('marketing_pricing_hero_lead', [
            'days' => $trialDays,
            'plan' => $trialPlanName,
        ]),
        'maxWidth' => 'max-w-7xl',
        'centered' => true,
    ])

    <section class="mkt-band border-b border-[rgba(15,23,42,0.06)]">
        <div class="mx-auto max-w-7xl px-6 pb-24 pt-12 sm:px-8">
            <div class="mkt-cta-box mx-auto mb-10 max-w-3xl text-center" data-mkt-reveal>
                <p class="mkt-display text-sm font-semibold text-slate-900">{{ __('marketing_pricing_trial_title', ['days' => $trialDays, 'plan' => $trialPlanName]) }}</p>
                <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ __('marketing_pricing_trial_body') }}</p>
            </div>

            <div class="mx-auto max-w-7xl" data-mkt-stagger>
                @include('partials.plan-pricing', [
                    'planRows' => $planRows,
                    'displayCurrency' => $displayCurrency,
                    'supportedCurrencies' => $supportedCurrencies,
                    'currencyLabels' => $currencyLabels,
                    'formAction' => route('marketing.pricing'),
                    'corporate' => true,
                    'labeledFeatures' => true,
                ])
            </div>

            <div class="mx-auto mt-20 max-w-3xl text-center" data-mkt-reveal>
                <span class="mkt-eyebrow">{{ __('Platform') }}</span>
                <h2 class="mkt-display mt-3 text-2xl font-semibold tracking-tight text-slate-900">{{ __('marketing_pricing_platform_title') }}</h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('marketing_pricing_platform_lead') }}</p>
            </div>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-mkt-stagger>
                @foreach ([
                    ['icon' => 'users', 't' => __('Clients & projects'), 'b' => __('Marketing features clients')],
                    ['icon' => 'doc', 't' => __('Proposals & e-sign'), 'b' => __('Marketing features proposals')],
                    ['icon' => 'coins', 't' => __('Invoicing & payments'), 'b' => __('Marketing features invoices')],
                    ['icon' => 'mail', 't' => __('Forms & lead inbox'), 'b' => __('Marketing features forms')],
                    ['icon' => 'building', 't' => __('Providers & commissions'), 'b' => __('Marketing features providers')],
                    ['icon' => 'chart', 't' => __('Analytics & reporting'), 'b' => __('Marketing features analytics')],
                    ['icon' => 'mail', 't' => __('marketing_feature_email_title'), 'b' => __('marketing_feature_email_body')],
                    ['icon' => 'bolt', 't' => __('marketing_feature_ai_title'), 'b' => __('marketing_feature_ai_body')],
                    ['icon' => 'calendar', 't' => __('marketing_feature_calendar_title'), 'b' => __('marketing_feature_calendar_body')],
                    ['icon' => 'portal', 't' => __('marketing_feature_portal_title'), 'b' => __('marketing_feature_portal_body')],
                    ['icon' => 'chat', 't' => __('marketing_feature_support_title'), 'b' => __('marketing_feature_support_body')],
                    ['icon' => 'puzzle', 't' => __('marketing_feature_modules_title'), 'b' => __('marketing_feature_modules_body')],
                ] as $i => $item)
                    <div class="mkt-feature-card !p-5" data-mkt-stagger-item data-mkt-hover>
                        <div @class(['mkt-feature-icon !mb-3 !h-9 !w-9', 'mkt-feature-icon--cyan' => $i % 2 === 1])>
                            @include('marketing.partials.feature-icon', ['name' => $item['icon']])
                        </div>
                        <h3 class="mkt-display text-sm font-semibold text-slate-900">{{ $item['t'] }}</h3>
                        <p class="mt-1.5 text-xs leading-relaxed text-slate-600">{{ $item['b'] }}</p>
                    </div>
                @endforeach
            </div>

            <p class="mx-auto mt-8 max-w-2xl text-center text-xs text-slate-500">{{ __('marketing_pricing_limits_note') }}</p>

            <div class="mkt-cta-box mx-auto mt-16 max-w-2xl text-center" data-mkt-reveal>
                <p class="text-sm text-slate-600">{{ __('Need a custom bundle or invoice billing?') }}</p>
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
