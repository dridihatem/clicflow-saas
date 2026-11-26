<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageTracking;
use App\Services\PlanLimitService;
use App\Services\PlanPricingService;
use App\Services\SubscriptionTrialService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;

class BillingController extends Controller
{
    public function __invoke(
        Request $request,
        PlanPricingService $planPricing,
        PlanLimitService $planLimits,
        SubscriptionTrialService $trials,
    ): Response {
        $user = $request->user();
        abort_if(! $user->hasRole('company_admin'), 403);
        $company = $user->company;
        abort_if(! $company, 403);

        $supported = config('flowdesk.supported_currencies', ['EUR']);
        $supported = is_array($supported) ? $supported : ['EUR'];
        $displayCurrency = strtoupper((string) $request->query('currency', $company->default_currency ?? flowdesk_default_currency()));
        if (! in_array($displayCurrency, $supported, true)) {
            $displayCurrency = in_array($company->default_currency ?? '', $supported, true)
                ? (string) $company->default_currency
                : flowdesk_default_currency();
        }

        $allPlans = Plan::query()->with(['limits', 'periodPrices'])->orderBy('name')->get();
        $planPricingRows = array_reverse($planPricing->buildPlanRows($allPlans, $displayCurrency));
        $currencyLabels = config('flowdesk.currency_labels', []);
        $currencyLabels = is_array($currencyLabels) ? $currencyLabels : [];

        // Fresh DB read so admin renewals show immediately on /billing.
        $subscription = Subscription::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('status', ['active', 'trialing'])
            ->with('plan.limits')
            ->latest('id')
            ->first();
        $company->loadMissing(['plan.limits']);

        $trialExpired = $trials->trialExpired($company);
        $onTrial = $trials->isOnTrial($company);
        $effectivePlan = $trials->effectivePlan($company);
        $billingPlan = $subscription?->plan;
        $plan = $effectivePlan ?? $company->plan ?? $billingPlan;
        if ($plan && ! $plan->relationLoaded('limits')) {
            $plan->load('limits');
        }

        $aiCreditsUsed = UsageTracking::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('metric', 'ai_credits')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('value');

        $planAddons = is_array($plan?->addons) ? $plan->addons : [];
        $stripePortalAvailable = Config::get('services.stripe.secret') !== null && Config::get('services.stripe.secret') !== '';

        $subscriptionFeatureRows = $planLimits->summarizePlanFeatures($plan, $company);
        $currentPlanSlug = $plan?->slug;
        $planSelectionEnabled = true;
        $currentPeriodMonths = (int) ($subscription?->period_months ?? 0);
        if (! in_array($currentPeriodMonths, [1, 3, 6, 12], true)) {
            if ($subscription?->current_period_end && $subscription->current_period_end->isFuture()) {
                $monthsLeft = (int) max(1, round(now()->diffInMonths($subscription->current_period_end, false)));
                foreach ([12, 6, 3, 1] as $candidate) {
                    if ($monthsLeft >= $candidate) {
                        $currentPeriodMonths = $candidate;
                        break;
                    }
                }
            }
            if (! in_array($currentPeriodMonths, [1, 3, 6, 12], true)) {
                $currentPeriodMonths = 0;
            }
        }
        $pricingPeriodMonths = in_array($currentPeriodMonths, [3, 6, 12], true)
            ? $currentPeriodMonths
            : 3;

        $view = view('billing.index', compact(
            'subscription',
            'plan',
            'billingPlan',
            'trialExpired',
            'onTrial',
            'aiCreditsUsed',
            'planAddons',
            'stripePortalAvailable',
            'planPricingRows',
            'displayCurrency',
            'supported',
            'currencyLabels',
            'subscriptionFeatureRows',
            'currentPlanSlug',
            'planSelectionEnabled',
            'currentPeriodMonths',
            'pricingPeriodMonths',
        ));

        return response($view)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
