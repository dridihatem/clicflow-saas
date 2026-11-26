<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageTracking;
use App\Models\User;
use App\Services\AiCreditUsageService;
use App\Services\PlanLimitService;
use App\Services\SubscriptionTrialService;
use App\Services\WorkspacePlanActivationService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('expired trial sets ai credits remaining to zero until renew', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    $company->forceFill(['plan_id' => $pro->id])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    UsageTracking::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'metric' => 'ai_credits',
        'value' => 25,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $limits = app(PlanLimitService::class);
    $trials = app(SubscriptionTrialService::class);

    expect($trials->trialExpired($company))->toBeTrue()
        ->and($trials->hasAiCreditEntitlement($company))->toBeFalse()
        ->and($limits->isFeatureEnabled($company, 'ai_credits'))->toBeFalse()
        ->and($limits->allows($company, 'ai_credits'))->toBeFalse()
        ->and($limits->aiCreditsForNav($company)['remaining'])->toBe(0)
        ->and($limits->aiCreditsForNav($company)['limit'])->toBe(0);
});

test('renewing plan restores ai credits and clears period usage', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    $company->forceFill(['plan_id' => $pro->id])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    UsageTracking::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'metric' => 'ai_credits',
        'value' => 40,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    app(WorkspacePlanActivationService::class)->activate($company, $pro, 3);

    $company = $company->fresh();
    $limits = app(PlanLimitService::class);

    expect(app(SubscriptionTrialService::class)->hasAiCreditEntitlement($company))->toBeTrue()
        ->and($limits->isFeatureEnabled($company, 'ai_credits'))->toBeTrue()
        ->and($limits->allows($company, 'ai_credits', 100))->toBeTrue()
        ->and(app(AiCreditUsageService::class)->usedThisMonth($company))->toBe(0)
        ->and($limits->aiCreditsForNav($company)['remaining'])->toBe((int) $limits->planLimitValue($company, 'ai_credits'));
});

test('ended subscription period zeroes ai credits until renew', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    $company->forceFill(['plan_id' => $pro->id, 'stripe_customer_id' => 'cus_test'])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'active',
        'trial_ends_at' => null,
        'current_period_end' => now()->subDay(),
    ]);

    $limits = app(PlanLimitService::class);

    expect(app(SubscriptionTrialService::class)->hasAiCreditEntitlement($company))->toBeFalse()
        ->and($limits->allows($company, 'ai_credits'))->toBeFalse()
        ->and($limits->aiCreditsForNav($company)['remaining'])->toBe(0);
});
