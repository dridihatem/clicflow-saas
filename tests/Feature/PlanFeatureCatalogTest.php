<?php

use App\Services\ModuleEntitlementService;
use App\Services\PlanLimitService;

test('visible plan feature catalog excludes core business verticals', function () {
    $catalogKeys = app(PlanLimitService::class)->featureCatalogKeys();
    $hidden = ModuleEntitlementService::CORE_FEATURE_KEYS;

    expect($catalogKeys)->not->toContain(...$hidden);
    expect($catalogKeys)->toContain('calendar', 'modules', 'workspace_ai_agent', 'hr');
});

test('full feature catalog keys match FEATURE_KEYS constant', function () {
    $catalogKeys = app(PlanLimitService::class)->featureCatalogKeys(includeCoreBusinessFeatures: true);

    expect($catalogKeys)->toEqual(PlanLimitService::FEATURE_KEYS);
});

test('admin plan edit uses the same visible feature catalog as billing subscription', function () {
    $catalog = app(PlanLimitService::class)->featureCatalog();

    expect($catalog)->not->toBeEmpty();
    expect(collect($catalog)->pluck('key')->all())->toContain('calendar', 'modules', 'workspace_ai_agent');
    expect(collect($catalog)->pluck('key')->all())->not->toContain('ecommerce', 'odoo', 'shopify');
});
