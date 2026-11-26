<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageTracking;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkspacePlanActivationService
{
    public function __construct(
        private SubscriptionTrialService $trials,
    ) {}

    /**
     * Activate a workspace plan after successful payment (or free plan selection).
     * Clears trial state so Pro/Enterprise limits apply immediately.
     */
    public function activate(Company $company, Plan $plan, int $periodMonths = 1, bool $force = false): Subscription
    {
        if ($periodMonths < 1) {
            $periodMonths = 1;
        }
        if ($periodMonths > 24) {
            $periodMonths = 24;
        }

        if (! $force && $company->plan_locked) {
            throw new InvalidArgumentException(__('billing_plan_locked'));
        }

        return DB::transaction(function () use ($company, $plan, $periodMonths, $force) {
            $existing = Subscription::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->whereIn('status', ['active', 'trialing'])
                ->latest('id')
                ->first();

            // Admin renewals (force): also revive the latest row if nothing is currently active
            // (e.g. ended/canceled leftover) so period changes always land on one subscription.
            if (! $existing && $force) {
                $existing = Subscription::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->latest('id')
                    ->first();
            }

            if ($existing) {
                Subscription::query()
                    ->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->whereIn('status', ['active', 'trialing'])
                    ->whereKeyNot($existing->id)
                    ->update(['status' => 'canceled']);

                $existing->forceFill([
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonths($periodMonths),
                    'period_months' => $periodMonths,
                ])->save();

                $subscription = $existing->refresh();
            } else {
                $subscription = Subscription::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'trial_ends_at' => null,
                    'current_period_end' => now()->addMonths($periodMonths),
                    'period_months' => $periodMonths,
                ]);
            }

            $company->forceFill(['plan_id' => $plan->id])->saveQuietly();
            $this->resetAiCreditsForCurrentPeriod($company);

            return $subscription;
        });
    }

    public function currentPlanSlug(Company $company): ?string
    {
        return $this->trials->effectivePlan($company)?->slug;
    }

    /**
     * Fresh credit allotment on renew/activate — previous usage this month no longer counts.
     */
    private function resetAiCreditsForCurrentPeriod(Company $company): void
    {
        UsageTracking::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('metric', 'ai_credits')
            ->whereDate('period_start', now()->startOfMonth()->toDateString())
            ->delete();
    }
}
