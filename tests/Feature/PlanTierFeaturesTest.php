<?php

use App\Models\Plan;
use App\Services\ModuleEntitlementService;
use App\Services\PlanLimitService;
use Database\Seeders\PlanSeeder;

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

function planFeatureEnabled(string $slug, string $key): bool
{
    $plan = Plan::query()->where('slug', $slug)->with('limits')->firstOrFail();
    $rows = app(PlanLimitService::class)->summarizePlanFeatures(
        $plan,
        includeCoreBusinessFeatures: true,
    );

    $row = collect($rows)->firstWhere('key', $key);

    return $row !== null && $row['enabled'] === true;
}

function planFeatureStatus(string $slug, string $key): string
{
    $plan = Plan::query()->where('slug', $slug)->with('limits')->firstOrFail();
    $rows = app(PlanLimitService::class)->summarizePlanFeatures(
        $plan,
        includeCoreBusinessFeatures: true,
    );

    $row = collect($rows)->firstWhere('key', $key);

    return $row['status'] ?? '';
}

test('starter plan seeds all catalog features with expected active and disabled flags', function () {
    $plan = Plan::query()->where('slug', 'starter')->with('limits')->firstOrFail();

    expect($plan->limits)->toHaveCount(count(PlanLimitService::FEATURE_KEYS));

    $enabled = [
        'projects', 'users', 'forms', 'submissions', 'widgets', 'ai_credits',
    ];
    $disabled = [
        'analytics', 'marketing_hub', 'email_marketing', 'reports',
        'providers', 'calendar', 'modules', 'premium_tts', 'workspace_ai_agent',
        'hr', 'odoo',
        ...ModuleEntitlementService::KEYS,
    ];

    foreach ($enabled as $key) {
        expect(planFeatureEnabled('starter', $key))->toBeTrue("starter: {$key} should be enabled");
    }
    foreach ($disabled as $key) {
        expect(planFeatureEnabled('starter', $key))->toBeFalse("starter: {$key} should be disabled");
        expect(planFeatureStatus('starter', $key))->toBe(__('Not included'));
    }
});

test('pro plan enables growth and workspace features with quotas', function () {
    $enabled = array_values(array_filter(
        PlanLimitService::FEATURE_KEYS,
        fn (string $key): bool => $key !== 'odoo' && ! in_array($key, ModuleEntitlementService::KEYS, true),
    ));
    foreach ($enabled as $key) {
        expect(planFeatureEnabled('pro', $key))->toBeTrue("pro: {$key} should be enabled");
    }

    foreach (ModuleEntitlementService::KEYS as $key) {
        expect(planFeatureEnabled('pro', $key))->toBeFalse("pro: {$key} remains marketplace entitlement");
    }

    expect(planFeatureEnabled('pro', 'odoo'))->toBeFalse('pro: odoo should be enterprise-only');
    expect(planFeatureStatus('pro', 'projects'))->toContain('100');
    expect(planFeatureStatus('pro', 'providers'))->toContain('25');
});

test('enterprise plan enables all non-marketplace-core features with unlimited quotas where applicable', function () {
    foreach (PlanLimitService::FEATURE_KEYS as $key) {
        if (in_array($key, ModuleEntitlementService::KEYS, true)) {
            expect(planFeatureEnabled('enterprise', $key))->toBeFalse("enterprise: {$key} remains marketplace entitlement");

            continue;
        }

        expect(planFeatureEnabled('enterprise', $key))->toBeTrue("enterprise: {$key} should be enabled");
    }

    expect(planFeatureStatus('enterprise', 'projects'))->toBe(__('Unlimited'));
    expect(planFeatureStatus('enterprise', 'analytics'))->toBe(__('Included'));
    // Odoo stays a marketplace entitlement (not bundled on the enterprise plan row).
    expect(planFeatureStatus('enterprise', 'odoo'))->toBe(__('Not included'));
});

test('each seeded plan defines every feature key explicitly', function () {
    foreach (['starter', 'pro', 'enterprise'] as $slug) {
        $plan = Plan::query()->where('slug', $slug)->with('limits')->firstOrFail();
        expect($plan->limits->pluck('feature_key')->sort()->values()->all())
            ->toEqual(collect(PlanLimitService::FEATURE_KEYS)->sort()->values()->all());
    }
});

test('visible plan summaries hide core business verticals', function () {
    $plan = Plan::query()->where('slug', 'enterprise')->with('limits')->firstOrFail();
    $visibleKeys = collect(app(PlanLimitService::class)->summarizePlanFeatures($plan))->pluck('key')->all();

    expect($visibleKeys)->not->toContain(...ModuleEntitlementService::CORE_FEATURE_KEYS)
        ->and($visibleKeys)->toContain('hr', 'modules', 'calendar');
});
