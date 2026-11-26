@extends('layouts.marketing')

@section('title', __('Cookie policy').' — '.config('app.name'))
@section('meta_description', __('Marketing cookies meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Legal'),
        'title' => __('Cookie policy'),
        'meta' => __('Legal last updated', ['date' => now()->translatedFormat('j F Y')]),
        'lead' => __('Marketing cookies lead'),
    ])

    <section class="mx-auto max-w-7xl px-6 py-16 sm:px-8">
        <div class="space-y-5" data-mkt-stagger>
            <div class="mkt-feature-card" data-mkt-stagger-item>
                <h2 class="mkt-display text-xl font-semibold text-slate-900">{{ __('Marketing cookies s1 title') }}</h2>
                <p class="mt-3 leading-relaxed text-slate-600">{{ __('Marketing cookies s1 body') }}</p>
            </div>
            <div class="mkt-feature-card" data-mkt-stagger-item>
                <h2 class="mkt-display text-xl font-semibold text-slate-900">{{ __('Marketing cookies s2 title') }}</h2>
                <p class="mt-3 leading-relaxed text-slate-600">{{ __('Marketing cookies s2 body') }}</p>
            </div>
            <div class="mkt-feature-card" data-mkt-stagger-item>
                <h2 class="mkt-display text-xl font-semibold text-slate-900">{{ __('Marketing cookies s3 title') }}</h2>
                <p class="mt-3 leading-relaxed text-slate-600">{{ __('Marketing cookies s3 body') }}</p>
            </div>
            <div class="rounded-[14px] border border-amber-200/80 bg-amber-50/70 p-8" data-mkt-stagger-item>
                <p class="text-sm leading-relaxed text-amber-950/90">{{ __('Marketing legal disclaimer') }}</p>
            </div>
        </div>

        <p class="mt-10" data-mkt-reveal>
            <a href="{{ route('home') }}" class="mkt-btn mkt-btn-ghost">{{ __('Legal back home') }}</a>
        </p>
    </section>
@endsection
