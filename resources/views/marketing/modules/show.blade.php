@extends('layouts.marketing')

@section('title', config('app.name').' — '.$module->name)
@section('meta_description', Str::limit(strip_tags($module->description ?? $module->name), 160))

@section('content')
    <section class="border-b border-[rgba(15,23,42,0.06)]">
        <div class="relative overflow-hidden border-b border-[rgba(124,92,252,0.08)]">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(124,92,252,0.14),transparent_45%),radial-gradient(circle_at_85%_10%,rgba(38,232,212,0.1),transparent_40%)]" aria-hidden="true"></div>
            @if ($module->coverUrl())
                <div class="absolute inset-0 opacity-20">
                    <img src="{{ $module->coverUrl() }}" alt="" class="h-full w-full object-cover" />
                </div>
            @endif

            <div class="relative mx-auto max-w-7xl px-6 py-14 sm:px-8 lg:py-18" data-mkt-hero>
                <a href="{{ route('marketing.modules', $catalogQuery) }}" class="mkt-btn mkt-btn-ghost !px-4 !py-2" data-mkt-hero-item>
                    <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
                    {{ __('marketing_modules_back_catalog') }}
                </a>

                <div class="mt-8 flex flex-wrap items-end gap-6">
                    @if ($module->imageUrl())
                        <img src="{{ $module->imageUrl() }}" alt="{{ $module->name }}" class="h-20 w-20 rounded-2xl border border-[rgba(91,100,140,0.16)] object-cover shadow-lg sm:h-24 sm:w-24" data-mkt-hero-item />
                    @else
                        <div class="mkt-feature-icon !mb-0 !h-20 !w-20 !rounded-2xl sm:!h-24 sm:!w-24" data-mkt-hero-item>
                            @include('marketing.partials.feature-icon', ['name' => $module->icon ?: 'puzzle'])
                        </div>
                    @endif
                    <div class="min-w-0 flex-1" data-mkt-hero-item>
                        <span class="mkt-eyebrow">{{ $module->category->label() }}</span>
                        <h1 class="mkt-display mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{{ $module->name }}</h1>
                        @if ($module->description)
                            <p class="mt-3 max-w-3xl text-base leading-relaxed text-slate-600">{{ $module->description }}</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="mx-auto max-w-7xl px-6 py-10 sm:px-8">
            <div class="grid gap-8 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    @if ($module->detail_content)
                        <div class="mkt-feature-card" data-mkt-reveal>
                            <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('marketing_modules_detail_overview') }}</h2>
                            <div class="prose prose-slate mt-4 max-w-none text-sm leading-relaxed text-slate-700 whitespace-pre-line">{{ $module->detail_content }}</div>
                        </div>
                    @endif

                    @if ($module->featureList() !== [])
                        <div class="mkt-feature-card" data-mkt-reveal>
                            <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('marketing_modules_detail_features') }}</h2>
                            <ul class="mt-6 grid gap-3 sm:grid-cols-2" data-mkt-stagger>
                                @foreach ($module->featureList() as $bullet)
                                    <li class="flex gap-3 rounded-xl border border-[rgba(91,100,140,0.12)] bg-[rgba(246,247,251,0.8)] px-4 py-3 text-sm text-slate-700" data-mkt-stagger-item>
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[rgba(124,92,252,0.14)] text-[#5b45d6]" aria-hidden="true">
                                            <i class="fa-solid fa-check text-[10px]"></i>
                                        </span>
                                        <span>{{ $bullet }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! $module->detail_content && $module->featureList() === [])
                        <div class="mkt-cta-box text-center" data-mkt-reveal>
                            <p class="text-sm text-slate-600">{{ __('marketing_modules_detail_empty') }}</p>
                        </div>
                    @endif
                </div>

                <div class="lg:col-span-1">
                    <div class="mkt-glass sticky top-24 rounded-[20px] p-6" data-mkt-reveal>
                        <p class="mkt-trust-label">{{ __('Price') }}</p>
                        <p class="mkt-display mt-2 text-3xl font-bold tabular-nums text-slate-900">{{ $module->formattedDisplayPrice($displayCurrency) }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $module->billing_period->label() }}</p>

                        <form method="POST" action="{{ route('marketing.cart.add', $module) }}" class="mt-6">
                            @csrf
                            <input type="hidden" name="currency" value="{{ $displayCurrency }}">
                            <button type="submit" class="mkt-btn mkt-btn-primary w-full justify-center">
                                <i class="fa-solid fa-cart-plus" aria-hidden="true"></i>
                                {{ __('marketing_modules_add_to_cart') }}
                            </button>
                        </form>

                        <a href="mailto:{{ config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr' }}" class="mkt-btn mkt-btn-ghost mt-3 w-full justify-center">
                            <i class="fa-regular fa-envelope text-sm" aria-hidden="true"></i>
                            {{ __('Contact sales') }}
                        </a>

                        <dl class="mt-8 space-y-3 border-t border-[rgba(91,100,140,0.12)] pt-6 text-sm">
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ __('Category') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $module->category->label() }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-slate-500">{{ __('Display currency') }}</dt>
                                <dd class="font-medium text-slate-900">{{ $currencyLabels[$displayCurrency] ?? $displayCurrency }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
