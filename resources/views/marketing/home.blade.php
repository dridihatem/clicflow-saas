@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('Welcome meta title'))
@section('meta_description', __('Welcome meta description'))

@php
    $brand = (string) config('flowdesk.brand_name', config('app.name'));
    $nova = config('flowdesk.ai_assistant_brand_name', 'Nova');
    $contactEmail = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
@endphp

@section('content')
    <div class="cf-landing">
        {{-- Full-bleed hero: Three.js AI neural field + brand copy --}}
        <section id="hero" class="cf-hero" data-mkt-hero>
            <div class="cf-hero__stage" data-mkt-hero-visual aria-hidden="true">
                <div class="cf-hero__three" data-cf-hero-three></div>
                <div class="cf-hero__veil"></div>
            </div>

            <div class="cf-hero__inner">
                <div class="cf-hero__copy">
                    <p class="cf-hero__brand" data-mkt-hero-item>{{ $brand }}</p>
                    <h1 class="cf-hero__title" data-mkt-hero-item>
                        {{ __('Welcome hero title') }}
                    </h1>
                    <p class="cf-hero__lead" data-mkt-hero-item>
                        {{ __('Welcome hero lead') }}
                    </p>
                    <div class="cf-hero__actions" data-mkt-hero-item>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('Create workspace') }}</a>
                        @endif
                        <a href="#how-it-works" class="mkt-btn mkt-btn-ghost mkt-btn-lg">{{ __('See how it works') }}</a>
                    </div>
                </div>
            </div>

            <a href="#how-it-works" class="cf-hero__scroll" data-mkt-hero-item aria-label="{{ __('See how it works') }}">
                <span></span>
            </a>
        </section>

        {{-- Industries strip --}}
        <section class="cf-strip" aria-label="{{ __('Built for teams that deliver') }}" data-mkt-reveal>
            <p class="cf-strip__label">{{ __('Built for teams that deliver') }}</p>
            <div class="mkt-marquee" aria-hidden="true">
                <div class="mkt-marquee__track">
                    @foreach ([
                        __('Agencies'),
                        __('Retail & DTC'),
                        __('Logistics'),
                        __('Clinics'),
                        __('Field services'),
                        __('Wholesale'),
                        __('Real estate'),
                        __('Agencies'),
                        __('Retail & DTC'),
                        __('Logistics'),
                        __('Clinics'),
                        __('Field services'),
                        __('Wholesale'),
                        __('Real estate'),
                    ] as $item)
                        <span>{{ $item }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section id="how-it-works" class="cf-section">
            <div class="cf-section__inner">
                <header class="cf-section__head" data-mkt-reveal>
                    <h2 class="cf-section__title">{{ __('Welcome how title') }}</h2>
                    <p class="cf-section__lead">{{ __('Welcome how lead') }}</p>
                </header>

                <ol class="cf-steps" data-mkt-stagger>
                    @foreach ([
                        ['n' => '01', 't' => __('Welcome how step1 title'), 'b' => __('Welcome how step1 body')],
                        ['n' => '02', 't' => __('Welcome how step2 title'), 'b' => __('Welcome how step2 body')],
                        ['n' => '03', 't' => __('Welcome how step3 title'), 'b' => __('Welcome how step3 body')],
                    ] as $step)
                        <li class="cf-step" data-mkt-stagger-item>
                            <span class="cf-step__num" aria-hidden="true">{{ $step['n'] }}</span>
                            <h3 class="cf-step__title">{{ $step['t'] }}</h3>
                            <p class="cf-step__body">{{ $step['b'] }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        {{-- Features --}}
        <section id="features" class="cf-section cf-section--band">
            <div class="cf-section__inner">
                <header class="cf-section__head" data-mkt-reveal>
                    <h2 class="cf-section__title">{{ __('Welcome section features title') }}</h2>
                    <p class="cf-section__lead">{{ __('Welcome section features lead') }}</p>
                </header>

                <div class="cf-features" data-mkt-stagger>
                    @foreach ([
                        ['icon' => 'building', 'title' => __('Welcome feature multitenant title'), 'body' => __('Welcome feature multitenant body')],
                        ['icon' => 'doc', 'title' => __('Welcome feature proposals title'), 'body' => __('Welcome feature proposals body')],
                        ['icon' => 'bolt', 'title' => __('Welcome feature automation title'), 'body' => __('Welcome feature ai body')],
                        ['icon' => 'chart', 'title' => __('Welcome feature analytics title'), 'body' => __('Welcome feature analytics body')],
                        ['icon' => 'mail', 'title' => __('Welcome feature leads title'), 'body' => __('Welcome feature leads body')],
                        ['icon' => 'users', 'title' => __('Welcome feature providers title'), 'body' => __('Welcome feature providers body')],
                    ] as $i => $card)
                        <article class="cf-feature" data-mkt-stagger-item>
                            <div @class(['mkt-feature-icon', 'mkt-feature-icon--cyan' => $i % 2 === 1])>
                                @include('marketing.partials.feature-icon', ['name' => $card['icon']])
                            </div>
                            <h3 class="cf-feature__title">{{ $card['title'] }}</h3>
                            <p class="cf-feature__body">{{ $card['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Voice / AI note --}}
        <section class="cf-section" data-mkt-reveal>
            <div class="cf-section__inner">
                <div class="cf-voice">
                    <div class="cf-voice__copy">
                        <h2 class="cf-section__title">{{ __('Welcome voice title', ['nova' => $nova]) }}</h2>
                        <p class="cf-section__lead">{{ __('Welcome voice lead', ['nova' => $nova, 'brand' => $brand]) }}</p>
                    </div>
                    <button type="button" data-mkt-open-chatbot class="mkt-btn mkt-btn-primary mkt-btn-lg">
                        {{ __('nova_chatbot_cta', ['nova' => $nova]) }}
                    </button>
                </div>
            </div>
        </section>

        {{-- Final CTA --}}
        <section id="get-started" class="cf-section cf-cta">
            <div class="cf-cta__glow" aria-hidden="true"></div>
            <div class="cf-section__inner cf-cta__inner" data-mkt-reveal>
                <h2 class="cf-section__title">{{ __('Welcome cta title') }}</h2>
                <p class="cf-section__lead">{{ __('Welcome cta body') }}</p>
                <div class="cf-cta__actions">
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('Create workspace') }}</a>
                    @endif
                    @if (is_string($contactEmail) && filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
                        <a href="mailto:{{ $contactEmail }}" class="mkt-btn mkt-btn-ghost mkt-btn-lg">{{ __('Contact sales') }}</a>
                    @endif
                </div>
            </div>
        </section>
    </div>
@endsection
