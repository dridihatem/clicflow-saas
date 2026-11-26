<?php

use App\Models\Company;
use App\Models\Plan;
use App\Models\PlanLimit;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('workspace manager can regenerate widget api token', function () {
    $company = Company::factory()->create(['api_token_hash' => null, 'api_token_hint' => null]);
    $plan = Plan::factory()->create();
    PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'widgets',
        'limit_value' => 1,
    ]);
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);

    $response = $this->actingAs($user)->post(route('settings.widget-embed.regenerate-token'));

    $response->assertRedirect(route('settings.widget-embed'));
    $company->refresh();
    expect($company->api_token_hash)->not->toBeNull();
    expect($company->api_token_hint)->not->toBeNull();
});
