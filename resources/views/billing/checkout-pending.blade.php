<x-app-layout>
    @php
        $isAdminRequest = ($order->payment_method === 'admin_request')
            || (($order->metadata['payment_method'] ?? null) === 'admin_request');
    @endphp

    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">
            {{ $isAdminRequest ? __('billing_checkout_admin_notify_pending_title') : __('billing_checkout_pending_title') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">
                    {{ $isAdminRequest ? __('billing_checkout_admin_notify_pending_title') : __('billing_checkout_pending_title') }}
                </h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                    {{ $isAdminRequest ? __('billing_checkout_admin_notify_pending_lead') : __('billing_checkout_pending_lead') }}
                </p>
            </div>

            @if (session('status'))
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            <div class="flow-panel space-y-4 p-6">
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('Order') }}</dt>
                        <dd class="font-mono font-semibold">{{ $order->order_number }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('Plan') }}</dt>
                        <dd class="font-semibold">{{ $order->plan?->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">{{ __('Total') }}</dt>
                        <dd class="font-bold tabular-nums">{{ flowdesk_format_minor($order->amount_minor, $order->currency) }} {{ $order->currency }}</dd>
                    </div>
                </dl>

                @if ($isAdminRequest)
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50 p-5 dark:border-indigo-500/30 dark:bg-indigo-950/30">
                        <h2 class="text-sm font-semibold text-indigo-900 dark:text-indigo-100">{{ __('billing_checkout_admin_notify_pending_title') }}</h2>
                        <p class="mt-2 text-sm text-indigo-950 dark:text-indigo-50">{{ __('billing_checkout_admin_notify_pending_lead') }}</p>
                        @if (! empty($order->metadata['customer_message']))
                            <div class="mt-4 rounded-lg border border-indigo-200/70 bg-white/70 px-4 py-3 text-sm dark:border-indigo-500/20 dark:bg-slate-900/40">
                                <p class="text-xs font-semibold uppercase tracking-wide text-indigo-800/80 dark:text-indigo-200/80">{{ __('Customer message') }}</p>
                                <p class="mt-1 whitespace-pre-wrap text-slate-900 dark:text-slate-100">{{ $order->metadata['customer_message'] }}</p>
                            </div>
                        @endif
                    </div>
                @elseif (collect($bankDetails)->filter()->isNotEmpty() || filled($bankInstructions))
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-5 dark:border-amber-500/30 dark:bg-amber-950/30">
                        <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100">{{ __('billing_checkout_bank_instructions') }}</h2>
                        <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">{{ __('billing_checkout_bank_help') }}</p>
                        <dl class="mt-4 space-y-3 text-sm text-amber-950 dark:text-amber-50">
                            @if (($bankDetails['holder'] ?? '') !== '')
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-amber-800/80">{{ __('admin_payment_bank_account_holder') }}</dt>
                                    <dd class="mt-0.5 font-medium">{{ $bankDetails['holder'] }}</dd>
                                </div>
                            @endif
                            @if (($bankDetails['rib'] ?? '') !== '')
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-amber-800/80">{{ __('admin_payment_bank_rib') }}</dt>
                                    <dd class="mt-0.5 font-mono text-base font-semibold">{{ $bankDetails['rib'] }}</dd>
                                </div>
                            @endif
                            @if (($bankDetails['extra'] ?? '') !== '')
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-amber-800/80">{{ __('admin_payment_bank_extra_instructions') }}</dt>
                                    <dd class="mt-0.5 whitespace-pre-line">{{ $bankDetails['extra'] }}</dd>
                                </div>
                            @endif
                        </dl>
                        @if (filled($bankInstructions) && ($bankDetails['extra'] ?? '') === '')
                            <p class="mt-3 whitespace-pre-line text-sm">{{ $bankInstructions }}</p>
                        @endif
                    </div>
                @endif

                <a href="{{ route('billing.index') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                    {{ __('Back to billing') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
