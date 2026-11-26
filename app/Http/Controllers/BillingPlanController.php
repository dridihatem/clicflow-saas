<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\WorkspaceSubscriptionOrder;
use App\Services\WorkspacePlanActivationService;
use App\Services\WorkspaceSubscriptionCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class BillingPlanController extends Controller
{
    public function store(
        Request $request,
        WorkspacePlanActivationService $activation,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $user = $request->user();
        abort_if(! $user?->hasRole('company_admin'), 403);
        $company = $user->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'period_months' => ['nullable', 'integer', 'in:1,3,6,12'],
            'currency' => ['nullable', 'string', 'size:3'],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        $period = (int) ($data['period_months'] ?? 3);
        if (! in_array($period, [3, 6, 12], true)) {
            $period = 3;
        }

        $supported = config('flowdesk.supported_currencies', ['EUR']);
        $supported = is_array($supported) ? $supported : ['EUR'];
        $currency = strtoupper((string) ($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
        if (! in_array($currency, $supported, true)) {
            $currency = in_array($company->default_currency ?? '', $supported, true)
                ? (string) $company->default_currency
                : flowdesk_default_currency();
        }

        if ($activation->currentPlanSlug($company) === $plan->slug
            && ! app(\App\Services\SubscriptionTrialService::class)->trialExpired($company)) {
            return redirect()
                ->route('billing.index')
                ->with('status', __('billing_already_on_plan', ['plan' => $plan->name]));
        }

        if (! $checkout->planRequiresPayment($plan, $period, $currency)) {
            try {
                $activation->activate($company, $plan, $period);
            } catch (InvalidArgumentException $e) {
                return redirect()
                    ->route('billing.index')
                    ->withErrors(['plan' => $e->getMessage()]);
            }

            return redirect()
                ->route('billing.index')
                ->with('status', __('billing_plan_activated', ['plan' => $plan->name]));
        }

        try {
            $order = $checkout->createOrder($company, $user, $plan, $period, $currency);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('billing.index')
                ->withErrors(['plan' => $e->getMessage()]);
        }

        return redirect()->route('billing.checkout.show', $order);
    }
}
