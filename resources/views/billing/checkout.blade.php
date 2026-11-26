<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('billing_checkout_title') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ __('billing_checkout_title') }}</h1>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('billing_checkout_lead') }}</p>
                </div>
                <a href="{{ route('billing.index') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-500">{{ __('Back to billing') }}</a>
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-5">
                <div class="flow-panel space-y-4 p-6 lg:col-span-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('Order summary') }}</h2>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('Order') }}</dt>
                            <dd class="font-mono font-semibold text-slate-900 dark:text-white">{{ $order->order_number }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('Plan') }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $order->plan?->name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">{{ __('Billing period') }}</dt>
                            <dd class="font-semibold text-slate-900 dark:text-white">{{ $order->period_months }} {{ __('months') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4 border-t border-slate-200 pt-3 dark:border-slate-700">
                            <dt class="text-slate-500">{{ __('Total') }}</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900 dark:text-white">
                                {{ flowdesk_format_minor($order->amount_minor, $order->currency) }} {{ $order->currency }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="flow-panel p-6 lg:col-span-3">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('billing_checkout_payment_method') }}</h2>

                    @if ($paymentMethods === [])
                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-950/30 dark:text-amber-50">
                            <p class="font-medium">{{ __('billing_checkout_no_payment_methods') }}</p>
                            <p class="mt-2 opacity-90">{{ __('billing_checkout_admin_notify_intro') }}</p>
                        </div>
                        <form method="POST" action="{{ route('billing.checkout.notify', $order) }}" class="mt-5 space-y-5">
                            @csrf
                            <div>
                                <x-input-label for="message" :value="__('billing_checkout_admin_notify_message_label')" />
                                <textarea
                                    id="message"
                                    name="message"
                                    rows="4"
                                    required
                                    maxlength="2000"
                                    class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white"
                                >{{ old('message') }}</textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i>
                                {{ __('billing_checkout_admin_notify_submit') }}
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('billing.checkout.pay', $order) }}" class="mt-5 space-y-5" id="billing-checkout-form">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($paymentMethods as $method)
                                    <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-4 transition hover:border-indigo-200 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/60 dark:border-slate-600 dark:has-[:checked]:bg-indigo-950/30">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="{{ $method['value'] }}"
                                            class="mt-1 text-indigo-600 focus:ring-indigo-500"
                                            data-method="{{ $method['value'] }}"
                                            @checked(old('payment_method', $defaultPaymentMethod) === $method['value'])
                                            required
                                        >
                                        <span class="min-w-0 flex-1">
                                            <span class="flex items-center gap-2 text-sm font-semibold text-slate-900 dark:text-white">
                                                <i class="{{ $method['icon'] }} text-indigo-500" aria-hidden="true"></i>
                                                {{ $method['label'] }}
                                            </span>
                                            <span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $method['description'] }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>

                            <div id="billing-bank-panel" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-950/30">
                                <h3 class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                                    {{ __('billing_checkout_bank_instructions') }}
                                </h3>
                                @if (collect($bankDetails)->filter()->isNotEmpty() || filled($bankInstructions))
                                    <dl class="mt-4 space-y-3 text-sm text-amber-950 dark:text-amber-50">
                                        @if (($bankDetails['holder'] ?? '') !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_account_holder') }}</dt>
                                                <dd class="mt-0.5 font-medium">{{ $bankDetails['holder'] }}</dd>
                                            </div>
                                        @endif
                                        @if (($bankDetails['bank_name'] ?? '') !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_name') }}</dt>
                                                <dd class="mt-0.5 font-medium">{{ $bankDetails['bank_name'] }}</dd>
                                            </div>
                                        @endif
                                        @if (($bankDetails['rib'] ?? '') !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_rib') }}</dt>
                                                <dd class="mt-0.5 font-mono text-base font-semibold tracking-wide">{{ $bankDetails['rib'] }}</dd>
                                            </div>
                                        @endif
                                        @if (($bankDetails['bic'] ?? '') !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_bic') }}</dt>
                                                <dd class="mt-0.5 font-mono font-medium">{{ $bankDetails['bic'] }}</dd>
                                            </div>
                                        @endif
                                        @if (($bankDetails['extra'] ?? '') !== '')
                                            <div>
                                                <dt class="text-xs font-semibold uppercase tracking-wide text-amber-800/80">{{ __('admin_payment_bank_extra_instructions') }}</dt>
                                                <dd class="mt-0.5 whitespace-pre-line">{{ $bankDetails['extra'] }}</dd>
                                            </div>
                                        @endif
                                    </dl>
                                @elseif (filled($bankInstructions))
                                    <p class="mt-3 whitespace-pre-line text-sm text-amber-900 dark:text-amber-100">{{ $bankInstructions }}</p>
                                @endif
                                <p class="mt-4 text-xs text-amber-800 dark:text-amber-200">{{ __('billing_checkout_bank_help') }}</p>
                            </div>

                            <button type="submit" class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-500">
                                {{ __('billing_checkout_pay') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('billing-checkout-form');
                if (!form) return;
                const panel = document.getElementById('billing-bank-panel');
                const sync = () => {
                    const selected = form.querySelector('input[name="payment_method"]:checked');
                    if (!panel) return;
                    panel.classList.toggle('hidden', !(selected && selected.value === 'bank'));
                };
                form.querySelectorAll('input[name="payment_method"]').forEach((el) => el.addEventListener('change', sync));
                sync();
            })();
        </script>
    @endpush
</x-app-layout>
