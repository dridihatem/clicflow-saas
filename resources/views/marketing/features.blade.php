@extends('layouts.marketing')

@section('title', __('Features').' — '.config('app.name'))
@section('meta_description', __('Marketing features meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Product'),
        'title' => __('Everything in'),
        'titleAccent' => __('one workspace'),
        'lead' => __('Marketing features lead'),
        'sub' => __('Marketing features ai note'),
    ])

    <section class="mkt-band border-b border-[rgba(15,23,42,0.06)]">
        <div class="mx-auto max-w-7xl px-6 py-16 sm:px-8">
            <div class="mx-auto max-w-3xl text-center" data-mkt-reveal>
                <span class="mkt-eyebrow">{{ __('Platform') }}</span>
                <h2 class="mkt-display mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ __('marketing_features_all_title') }}</h2>
                <p class="mx-auto mt-4 max-w-2xl text-slate-600">{{ __('marketing_features_all_lead') }}</p>
            </div>

            <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3" data-mkt-stagger>
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
                    ['icon' => 'users', 't' => __('marketing_feature_team_title'), 'b' => __('marketing_feature_team_body')],
                    ['icon' => 'palette', 't' => __('marketing_feature_branding_title'), 'b' => __('marketing_feature_branding_body')],
                    ['icon' => 'shield', 't' => __('marketing_feature_security_title'), 'b' => __('marketing_feature_security_body')],
                    ['icon' => 'puzzle', 't' => __('marketing_feature_modules_title'), 'b' => __('marketing_feature_modules_body')],
                ] as $i => $item)
                    <div class="mkt-feature-card" data-mkt-stagger-item data-mkt-hover>
                        <div @class(['mkt-feature-icon', 'mkt-feature-icon--cyan' => $i % 2 === 1])>
                            @include('marketing.partials.feature-icon', ['name' => $item['icon']])
                        </div>
                        <h3 class="mkt-display text-base font-semibold text-slate-900">{{ $item['t'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $item['b'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-12 flex flex-wrap justify-center gap-3" data-mkt-reveal>
                <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('Create workspace') }}</a>
                <a href="{{ route('marketing.about') }}" class="mkt-btn mkt-btn-ghost mkt-btn-lg">{{ __('About us') }}</a>
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(15,23,42,0.06)] py-16">
        <div class="mx-auto max-w-3xl px-6 sm:px-8">
            <div class="text-center" data-mkt-reveal>
                <span class="mkt-eyebrow">{{ __('marketing_faq_eyebrow') }}</span>
                <h2 class="mkt-display mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ __('marketing_faq_title') }}</h2>
                <p class="mx-auto mt-4 max-w-2xl text-slate-600">{{ __('marketing_faq_lead') }}</p>
            </div>

            <div class="mt-10 space-y-3" data-mkt-stagger>
                @foreach ([
                    ['q' => __('marketing_faq_q1'), 'a' => __('marketing_faq_a1')],
                    ['q' => __('marketing_faq_q2'), 'a' => __('marketing_faq_a2')],
                    ['q' => __('marketing_faq_q3'), 'a' => __('marketing_faq_a3')],
                    ['q' => __('marketing_faq_q4'), 'a' => __('marketing_faq_a4')],
                    ['q' => __('marketing_faq_q5'), 'a' => __('marketing_faq_a5')],
                    ['q' => __('marketing_faq_q6'), 'a' => __('marketing_faq_a6')],
                    ['q' => __('marketing_faq_q7'), 'a' => __('marketing_faq_a7')],
                ] as $faq)
                    <details class="mkt-feature-card group !p-0 open:border-[rgba(124,92,252,0.28)]" data-mkt-stagger-item>
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-6 py-4 text-start text-sm font-semibold text-slate-900 [&::-webkit-details-marker]:hidden">
                            {{ $faq['q'] }}
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[rgba(124,92,252,0.1)] text-[#5b45d6] transition group-open:rotate-180">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </span>
                        </summary>
                        <p class="border-t border-[rgba(91,100,140,0.12)] px-6 py-4 text-sm leading-relaxed text-slate-600">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>

            <div class="mkt-cta-box mt-10 text-center" data-mkt-reveal>
                <p class="text-sm font-medium text-slate-700">{{ __('marketing_faq_more_help') }}</p>
                <a href="{{ route('marketing.contact') }}" class="mkt-btn mkt-btn-primary mt-4">{{ __('Contact sales') }}</a>
            </div>
        </div>
    </section>
@endsection
