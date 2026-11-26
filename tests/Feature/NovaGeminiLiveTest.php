<?php

use App\Models\Company;
use App\Models\User;
use App\Services\NovaLiveToolsService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('nova live session requires google api key', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($user->company);

    $this->actingAs($user)
        ->postJson(route('assistant.live.session'))
        ->assertStatus(503)
        ->assertJsonPath('code', 'gemini_unconfigured');
});

test('nova live tools can navigate to dashboard', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($company);

    $this->actingAs($user)
        ->postJson(route('assistant.live.tools'), [
            'calls' => [
                ['id' => '1', 'name' => 'navigate', 'args' => ['command_id' => 'dashboard']],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('results.0.result.ok', true)
        ->assertJsonPath('results.0.result.action', 'navigate');
});

test('nova live tools who are you returns identity', function () {
    $company = Company::factory()->create(['name' => 'Acme Live']);
    $user = User::factory()->for($company)->create(['name' => 'Alex']);
    $user->assignRole('company_admin');

    $result = app(NovaLiveToolsService::class)->execute($user, 'who_are_you', []);

    expect($result['ok'])->toBeTrue();
    expect($result['action'])->toBe('speak');
    expect($result['reply'])->not->toBeEmpty();
});

test('nova voice nav config disables live session url', function () {
    $user = User::factory()->create();
    $gates = array_fill_keys(\App\Services\PlanLimitService::FEATURE_KEYS, true);

    $config = app(\App\Services\NovaVoiceNavigationService::class)->clientConfig($user, $gates);

    expect($config['liveSessionUrl'])->toBeNull();
    expect($config['livePhrases'])->toBe([]);
});
