@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('marketing_checkout_success_title'))

@section('content')
    <section class="mx-auto max-w-7xl px-6 py-20 sm:px-8" data-mkt-hero>
        <div class="mx-auto max-w-2xl text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600" data-mkt-hero-item>
                <i class="fa-solid fa-check text-xl" aria-hidden="true"></i>
            </div>
            <p class="mkt-eyebrow mt-6" data-mkt-hero-item>{{ __('Checkout') }}</p>
            <h1 class="mkt-display mt-3 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl" data-mkt-hero-item>
                {{ __('marketing_checkout_success_title') }}
            </h1>
            <p class="mt-3 text-sm text-slate-600" data-mkt-hero-item>
                {{ __('marketing_checkout_success_lead', ['number' => $order->order_number]) }}
            </p>

            <div class="mkt-glass mt-10 rounded-[18px] p-6 text-left" data-mkt-reveal>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('marketing_checkout_downloads') }}</h2>
                <ul class="mt-4 space-y-3">
                    @foreach ($order->items as $item)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-[12px] border border-slate-200/80 bg-white/70 px-4 py-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $item->module_name }}</p>
                                <p class="text-xs text-slate-500">{{ $item->formattedPrice() }}</p>
                            </div>
                            @if ($item->module?->zip_path)
                                <a
                                    href="{{ URL::signedRoute('marketing.order.download', ['order' => $order->id, 'module' => $item->marketplace_module_id]) }}"
                                    class="mkt-btn mkt-btn-primary !px-4 !py-2 !text-xs"
                                >
                                    <i class="fa-solid fa-download" aria-hidden="true"></i>
                                    {{ __('Download') }}
                                </a>
                            @else
                                <span class="text-xs text-slate-500">{{ __('marketing_checkout_no_zip') }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="mt-8 flex flex-wrap items-center justify-center gap-3" data-mkt-reveal>
                @if (Route::has('settings.modules') && auth()->user()?->hasRole('company_admin'))
                    <a href="{{ route('settings.modules') }}" class="mkt-btn mkt-btn-ghost">{{ __('marketing_checkout_install_workspace') }}</a>
                @endif
                <a href="{{ route('settings.modules') }}" class="mkt-btn mkt-btn-primary">{{ __('marketing_cart_browse_modules') }}</a>
            </div>
        </div>
    </section>
@endsection
