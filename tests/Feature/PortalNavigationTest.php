<?php

use App\Models\User;
use App\Services\PortalNavigationService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('portal navigation exposes work billing and support parent groups with children', function () {
    $user = User::factory()->create();
    $user->syncRoles(['client']);

    $gates = array_fill_keys(\App\Services\PlanLimitService::FEATURE_KEYS, true);
    $sections = app(PortalNavigationService::class)->sectionsFor($user, $gates);

    $labels = collect($sections)->pluck('label')->all();
    expect($labels)->toContain(__('Overview'))
        ->and($labels)->toContain(__('Nav section work'))
        ->and($labels)->toContain(__('Nav section sales_billing'))
        ->and($labels)->toContain(__('Nav section support'))
        ->and($labels)->toContain(__('Activity'))
        ->and($labels)->toContain(__('Account'));

    $work = collect($sections)->firstWhere('label', __('Nav section work'));
    expect($work['type'])->toBe('group')
        ->and(collect($work['children'])->pluck('label')->all())->toContain(__('My projects'), __('Calendar'));

    $billing = collect($sections)->firstWhere('label', __('Nav section sales_billing'));
    expect($billing['type'])->toBe('group')
        ->and(collect($billing['children'])->pluck('label')->all())->toContain(__('Quotes'), __('Invoices'));

    $support = collect($sections)->firstWhere('label', __('Nav section support'));
    expect($support['type'])->toBe('group')
        ->and(collect($support['children'])->pluck('label')->all())->toContain(__('Messages'), __('Tickets'));
});

test('portal dashboard renders sidebar parent section labels for client', function () {
    $user = User::factory()->create();
    $user->syncRoles(['client']);
    \App\Models\Client::factory()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('portal.dashboard'))
        ->assertOk()
        ->assertSee(__('Nav section work'))
        ->assertSee(__('Nav section sales_billing'))
        ->assertSee(__('Nav section support'));
});
