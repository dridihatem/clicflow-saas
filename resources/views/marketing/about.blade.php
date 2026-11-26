@extends('layouts.marketing')

@section('title', __('About us').' — '.config('app.name'))
@section('meta_description', __('Marketing about meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Company'),
        'title' => __('About us'),
        'lead' => __('Marketing about lead'),
        'sub' => __('Marketing about growth lead'),
        'maxWidth' => 'max-w-7xl',
    ])

    <section class="mkt-band border-b border-[rgba(15,23,42,0.06)]">
        <div class="mx-auto max-w-7xl px-6 py-16 sm:px-8">
            <div class="mx-auto max-w-3xl text-center" data-mkt-reveal>
                <span class="mkt-eyebrow">{{ __('Marketing about growth eyebrow') }}</span>
                <h2 class="mkt-display mt-3 text-3xl font-semibold tracking-tight text-slate-900">{{ __('Marketing about growth title') }}</h2>
            </div>

            <div class="mt-12 grid gap-5 lg:grid-cols-2" data-mkt-stagger>
                <div class="mkt-feature-card" data-mkt-stagger-item data-mkt-hover>
                    <div class="mkt-feature-icon">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>
                    </div>
                    <h3 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Marketing about small title') }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing about small body') }}</p>
                </div>
                <div class="mkt-feature-card" data-mkt-stagger-item data-mkt-hover>
                    <div class="mkt-feature-icon mkt-feature-icon--cyan">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                    </div>
                    <h3 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Marketing about large title') }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing about large body') }}</p>
                </div>
            </div>

            <div class="mkt-stats mkt-glass mt-10" data-mkt-stagger>
                @foreach ([
                    __('Marketing about growth stat1'),
                    __('Marketing about growth stat2'),
                    __('Marketing about growth stat3'),
                ] as $stat)
                    <div class="mkt-stat" data-mkt-stagger-item>
                        <div class="mkt-stat__label !mt-0 text-sm font-medium text-slate-700">{{ $stat }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="border-b border-[rgba(15,23,42,0.06)] py-16">
        <div class="mx-auto max-w-7xl px-6 sm:px-8">
            <div class="space-y-5" data-mkt-stagger>
                <div class="mkt-cta-box" data-mkt-stagger-item>
                    <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Our mission') }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing about mission') }}</p>
                </div>
                <div class="mkt-cta-box" data-mkt-stagger-item>
                    <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Our vision') }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing about vision') }}</p>
                </div>
                <div class="mkt-cta-box" data-mkt-stagger-item>
                    <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Our clients') }}</h2>
                    <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing about clients') }}</p>
                </div>
            </div>

            <div class="mt-12 flex flex-wrap gap-3" data-mkt-reveal>
                <a href="{{ route('marketing.contact') }}" class="mkt-btn mkt-btn-primary mkt-btn-lg">{{ __('Talk to us') }}</a>
                <a href="{{ route('register') }}" class="mkt-btn mkt-btn-ghost mkt-btn-lg">{{ __('Start a workspace') }}</a>
            </div>
        </div>
    </section>
@endsection
