@php
    $aiCreditRow = collect($subscriptionFeatureRows ?? [])->firstWhere('key', 'ai_credits');
    $aiCreditLimit = $aiCreditRow['limit'] ?? null;
    $aiCreditPct = ($aiCreditLimit && (int) $aiCreditLimit > 0)
        ? min(100, (int) round(((int) $aiCreditsUsed / (int) $aiCreditLimit) * 100))
        : null;
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Billing & monetization') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8 space-y-8">
            <x-flow.page-header
                :title="__('Billing & monetization')"
                :description="__('billing_page_intro')"
            />

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">
                    @foreach ($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            @endif

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            {{-- Current subscription --}}
            <div class="flow-panel overflow-hidden p-0">
                <div class="border-b border-slate-200/80 bg-slate-50/80 px-8 py-5 dark:border-slate-700 dark:bg-slate-800/40">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Subscription') }}</h3>
                            @if ($plan)
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">
                                    {{ __('Plan') }}:
                                    <strong class="text-slate-900 dark:text-white">{{ $plan->name }}</strong>
                                    @if ($trialExpired && $billingPlan && $billingPlan->slug !== $plan->slug)
                                        <span class="ms-1 text-xs font-normal text-slate-500">({{ __('billing_trial_limits_note', ['plan' => $billingPlan->name]) }})</span>
                                    @endif
                                </p>
                            @endif
                        </div>
                        @if ($trialExpired)
                            <x-flow.badge variant="warning">{{ __('billing_trial_expired_badge') }}</x-flow.badge>
                        @elseif ($onTrial)
                            <x-flow.badge variant="neutral">{{ __('billing_on_trial_badge') }}</x-flow.badge>
                        @elseif ($subscription?->status)
                            <x-flow.badge :variant="$subscription->status === 'active' ? 'success' : 'neutral'">
                                {{ \Illuminate\Support\Str::headline($subscription->status) }}
                            </x-flow.badge>
                        @endif
                    </div>
                </div>

                <div class="p-8">
                    @if ($trialExpired)
                        <div class="mb-6 rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-950 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-100">
                            <p class="font-semibold">{{ __('trial_expired_title') }}</p>
                            <p class="mt-1 text-xs opacity-90">{{ __('billing_choose_plan_below') }}</p>
                        </div>
                    @endif
                    @if ($plan)
                        <div class="grid gap-6 lg:grid-cols-3">
                            <div class="lg:col-span-1">
                                <p class="text-3xl font-bold tabular-nums text-slate-900 dark:text-white">
                                    {{ number_format((float) $plan->price_monthly, 0) }}
                                    <span class="text-base font-semibold text-slate-500">{{ $plan->currency }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Monthly reference') }}</p>
                                <p class="mt-2 font-mono text-xs text-slate-400">{{ $plan->slug }}</p>
                            </div>
                            <div class="lg:col-span-2 grid gap-3 sm:grid-cols-2">
                                @if ($subscription?->trial_ends_at)
                                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Trial ends') }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $subscription->trial_ends_at->format('Y-m-d') }}</p>
                                    </div>
                                @endif
                                @if ($currentPeriodMonths)
                                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Billing period') }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ $currentPeriodMonths }} {{ $currentPeriodMonths === 1 ? __('month') : __('months') }}
                                        </p>
                                    </div>
                                @endif
                                @if ($subscription?->current_period_end)
                                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900/50">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Current period ends') }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $subscription->current_period_end->format('Y-m-d') }}</p>
                                        @if ($subscription->current_period_end->isPast())
                                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">{{ __('Period ended') }}</p>
                                        @endif
                                    </div>
                                @endif
                                @if ($aiCreditPct !== null)
                                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 sm:col-span-2 dark:border-slate-700 dark:bg-slate-900/50">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('AI credits (this month)') }}</p>
                                            <p class="text-xs font-medium text-slate-600 dark:text-slate-300">
                                                {{ __('billing_ai_credits_used', ['used' => number_format((int) $aiCreditsUsed), 'limit' => number_format((int) $aiCreditLimit)]) }}
                                            </p>
                                        </div>
                                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                            <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $aiCreditPct }}%"></div>
                                        </div>
                                    </div>
                                @elseif ($aiCreditsUsed > 0)
                                    <div class="rounded-xl border border-slate-200/80 bg-white px-4 py-3 sm:col-span-2 dark:border-slate-700 dark:bg-slate-900/50">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('AI credits (this month)') }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                            {{ __('billing_ai_credits_unlimited', ['used' => number_format((int) $aiCreditsUsed)]) }}
                                        </p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if (! empty($subscriptionFeatureRows))
                            <div class="mt-8 rounded-xl border border-slate-200/80 bg-slate-50/50 p-5 dark:border-slate-700 dark:bg-slate-800/30">
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Plan features & limits') }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Included modules and usage against your current plan.') }}</p>
                                <div class="mt-4">
                                    @include('partials.plan-feature-list', ['featureRows' => $subscriptionFeatureRows])
                                </div>
                            </div>
                        @endif

                        @if ($stripePortalAvailable && $subscription)
                            <form method="POST" action="{{ route('billing.stripe-portal') }}" class="mt-6 flex flex-wrap items-center gap-4">
                                @csrf
                                <x-secondary-button type="submit" class="inline-flex items-center gap-2">
                                    <i class="fa-brands fa-stripe text-sm" aria-hidden="true"></i>
                                    {{ __('Stripe Customer Portal') }}
                                </x-secondary-button>
                                <p class="text-xs text-slate-500">{{ __('Manage payment methods and subscription in Stripe (requires STRIPE_SECRET).') }}</p>
                            </form>
                        @endif

                        @if (count($planAddons) > 0)
                            <div class="mt-6 rounded-xl border border-dashed border-slate-300 bg-white/60 p-5 dark:border-slate-600 dark:bg-slate-900/30">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Plan add-ons') }}</p>
                                <ul class="mt-3 space-y-2 text-sm text-slate-600 dark:text-slate-400">
                                    @foreach ($planAddons as $addon)
                                        <li class="flex justify-between gap-4 border-b border-slate-100 pb-2 last:border-0 dark:border-slate-800">
                                            <span>{{ $addon['name'] ?? '—' }}</span>
                                            @if (isset($addon['price_monthly_minor']))
                                                <span class="tabular-nums font-medium text-slate-800 dark:text-slate-200">{{ flowdesk_format_minor((int) ($addon['price_monthly_minor'] ?? 0), $addon['currency'] ?? $plan->currency) }} {{ $addon['currency'] ?? $plan->currency }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                                <p class="mt-3 text-xs text-slate-500">{{ __('Contact sales to activate add-ons; prices are indicative.') }}</p>
                            </div>
                        @endif
                    @else
                        <p class="text-slate-600 dark:text-slate-400">{{ __('No active subscription on file.') }}</p>
                    @endif
                </div>
            </div>

            @if (! empty($planPricingRows))
                <div class="flow-panel p-8">
                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Plans & periods') }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('billing_plans_select_help') }}</p>
                    <div class="mt-6">
                        @include('partials.plan-pricing', [
                            'planRows' => $planPricingRows,
                            'displayCurrency' => $displayCurrency,
                            'supportedCurrencies' => $supported,
                            'currencyLabels' => $currencyLabels,
                            'formAction' => route('billing.index'),
                            'labeledFeatures' => true,
                            'planSelectionEnabled' => $planSelectionEnabled ?? false,
                            'currentPlanSlug' => $currentPlanSlug ?? null,
                            'activateRoute' => route('billing.plans.store'),
                            'periodInitial' => $pricingPeriodMonths ?? 3,
                        ])
                    </div>
                </div>
            @endif

            <div>
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('billing_quick_settings') }}</h3>
                <div class="grid gap-3 sm:grid-cols-3">
                    <a href="{{ route('settings.smtp') }}" class="flow-panel flex items-center gap-3 p-4 transition hover:border-indigo-300 dark:hover:border-indigo-600/50">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        </span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('SMTP settings') }}</span>
                    </a>
                    <a href="{{ route('settings.invoice-documents') }}" class="flow-panel flex items-center gap-3 p-4 transition hover:border-indigo-300 dark:hover:border-indigo-600/50">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <i class="fa-solid fa-file-invoice" aria-hidden="true"></i>
                        </span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Invoice documents') }}</span>
                    </a>
                    <a href="{{ route('settings.security') }}" class="flow-panel flex items-center gap-3 p-4 transition hover:border-indigo-300 dark:hover:border-indigo-600/50">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        </span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Security') }}</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
