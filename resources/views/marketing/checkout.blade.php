@extends('layouts.marketing')

@section('title', config('app.name').' — '.__('marketing_checkout_title'))
@section('meta_description', __('marketing_checkout_meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Checkout'),
        'title' => __('marketing_checkout_title'),
        'lead' => __('marketing_checkout_lead'),
        'maxWidth' => 'max-w-7xl',
        'centered' => true,
    ])

    <section class="border-b border-slate-200 bg-[rgb(11,16,32)]">
        <div class="mx-auto max-w-7xl px-6 py-12 pb-24 sm:px-10 lg:px-12">
            <div class="mb-8 flex flex-wrap items-center justify-end gap-4">
                <a href="{{ route('settings.modules', ['cart' => 1]) }}" class="mkt-btn mkt-btn-ghost !px-4 !py-2">
                    <i class="fa-solid fa-arrow-left text-xs text-[#EA580C]" aria-hidden="true"></i>
                    {{ __('marketing_checkout_back_cart') }}
                </a>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <form method="POST" action="{{ route('marketing.checkout.store') }}" id="checkout-form" class="space-y-6">
                        @csrf

                        <div class="mkt-glass rounded-[20px] p-6">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('marketing_checkout_customer_heading') }}</h2>
                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <x-input-label for="name" :value="__('Name')" />
                                    <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name', auth()->user()?->name)" required />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="email" :value="__('Email')" />
                                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', auth()->user()?->email)" required />
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                                <div>
                                    <x-input-label for="company" :value="__('Company')" />
                                    <x-text-input id="company" name="company" class="mt-1 block w-full" :value="old('company', auth()->user()?->company?->name)" />
                                    <x-input-error :messages="$errors->get('company')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div class="mkt-glass rounded-[20px] p-6">
                            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('marketing_checkout_payment_method') }}</h2>

                            @if ($paymentMethods === [])
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                                    <p class="font-medium">{{ __('marketing_checkout_no_payment_methods') }}</p>
                                    <p class="mt-2 text-amber-900/90">{{ __('marketing_checkout_admin_notify_intro') }}</p>
                                </div>
                                <div class="mt-5">
                                    <x-input-label for="message" :value="__('marketing_checkout_admin_notify_message_label')" />
                                    <textarea
                                        id="message"
                                        name="message"
                                        rows="4"
                                        required
                                        maxlength="2000"
                                        class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-[#EA580C] focus:ring-[#EA580C]"
                                    >{{ old('message') }}</textarea>
                                    <x-input-error :messages="$errors->get('message')" class="mt-2" />
                                </div>
                            @else
                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    @foreach ($paymentMethods as $method)
                                        <label class="checkout-payment-option relative flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-[rgba(234,88,12,0.35)] has-[:checked]:border-[#EA580C] has-[:checked]:bg-[rgba(234,88,12,0.06)] has-[:checked]:shadow-sm">
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value="{{ $method['value'] }}"
                                                class="checkout-payment-radio mt-1 text-[#EA580C] focus:ring-[#EA580C]"
                                                data-method="{{ $method['value'] }}"
                                                @checked(old('payment_method', $defaultPaymentMethod) === $method['value'])
                                                required
                                            >
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                                    <i class="{{ $method['icon'] }} text-[#EA580C]" aria-hidden="true"></i>
                                                    {{ $method['label'] }}
                                                </span>
                                                <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $method['description'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif

                            <div id="checkout-bank-panel" class="mt-5 hidden rounded-xl border border-amber-200 bg-amber-50 p-5">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-900">
                                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                    {{ __('marketing_checkout_bank_instructions') }}
                                </h3>
                                @if (collect($bankDetails)->filter()->isNotEmpty() || filled($bankInstructions))
                                    <dl class="mt-4 space-y-3 text-sm text-amber-950">
                                        @if ($bankDetails['holder'] !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_account_holder') }}</dt>
                                                <dd class="mt-0.5 font-medium">{{ $bankDetails['holder'] }}</dd>
                                            </div>
                                        @endif
                                        @if ($bankDetails['bank_name'] !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_name') }}</dt>
                                                <dd class="mt-0.5 font-medium">{{ $bankDetails['bank_name'] }}</dd>
                                            </div>
                                        @endif
                                        @if ($bankDetails['rib'] !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_rib') }}</dt>
                                                <dd class="mt-0.5 font-mono text-base font-semibold tracking-wide">{{ $bankDetails['rib'] }}</dd>
                                            </div>
                                        @endif
                                        @if ($bankDetails['bic'] !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_bic') }}</dt>
                                                <dd class="mt-0.5 font-mono font-medium">{{ $bankDetails['bic'] }}</dd>
                                            </div>
                                        @endif
                                        @if ($bankDetails['extra'] !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_extra_instructions') }}</dt>
                                                <dd class="mt-0.5 whitespace-pre-line">{{ $bankDetails['extra'] }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @else
                                    <p class="mt-3 text-sm text-amber-900">{{ __('marketing_checkout_bank_details_pending') }}</p>
                                @endif
                                <div class="mt-4 rounded-lg border border-dashed border-amber-300/70 bg-amber-50/80 px-4 py-3 text-sm text-amber-900">
                                    <i class="fa-solid fa-hashtag me-1.5 text-amber-700" aria-hidden="true"></i>
                                    {{ __('marketing_checkout_payment_reference_checkout_hint') }}
                                </div>
                                <p class="mt-4 text-xs text-amber-800">{{ __('marketing_checkout_pay_bank_help') }}</p>
                            </div>

                            <x-input-error :messages="$errors->get('payment_method')" class="mt-3" />
                            <x-input-error :messages="$errors->get('checkout')" class="mt-3" />
                        </div>

                        <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg w-full justify-center">
                            <i class="fa-solid {{ $paymentMethods === [] ? 'fa-paper-plane' : 'fa-lock' }} text-xs" aria-hidden="true"></i>
                            {{ $paymentMethods === [] ? __('marketing_checkout_admin_notify_submit') : __('marketing_checkout_place_order') }}
                        </button>
                    </form>
                </div>

                <div class="lg:col-span-1">
                    <div class="sticky top-24 mkt-glass rounded-[20px] p-6">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Order summary') }}</h2>
                        <ul class="mt-4 space-y-3">
                            @foreach ($lineItems as $line)
                                @php($mod = $line['module'])
                                <li class="flex gap-3">
                                    @if ($mod->imageUrl())
                                        <img src="{{ $mod->imageUrl() }}" alt="" class="h-12 w-12 shrink-0 rounded-lg border border-slate-100 object-cover" />
                                    @else
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-[rgba(234,88,12,0.1)] text-[#5b45d6]">
                                            @include('marketing.partials.feature-icon', ['name' => $mod->icon ?: 'puzzle', 'class' => 'h-5 w-5'])
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium text-slate-900">{{ $mod->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $mod->billing_period->label() }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold tabular-nums text-slate-900">{{ flowdesk_format_minor($line['price_minor'], $currency) }}</p>
                                </li>
                            @endforeach
                        </ul>
                        <div class="mt-5 flex justify-between border-t border-slate-100 pt-4">
                            <span class="font-semibold text-slate-900">{{ __('Total') }}</span>
                            <span class="text-xl font-bold tabular-nums text-slate-900">{{ flowdesk_format_minor($totalMinor, $currency) }} <span class="text-sm font-semibold text-slate-500">{{ $currency }}</span></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @push('head')
        <script>
            function syncCheckoutBankPanel() {
                const selected = document.querySelector('.checkout-payment-radio:checked');
                const panel = document.getElementById('checkout-bank-panel');
                if (!panel || !selected) {
                    return;
                }
                panel.classList.toggle('hidden', selected.value !== 'bank');
            }

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.checkout-payment-radio').forEach((input) => {
                    input.addEventListener('change', syncCheckoutBankPanel);
                });
                syncCheckoutBankPanel();
            });
        </script>
    @endpush
@endsection
