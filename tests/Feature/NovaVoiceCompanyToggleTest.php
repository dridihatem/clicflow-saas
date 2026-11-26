<?php

use App\Models\CompanySetting;
use App\Models\User;
use App\Services\NovaVoiceNavigationService;
use App\Services\PlanLimitService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Route::middleware(['web', 'auth', 'nova.voice'])->post('/_test/nova-voice', fn () => response()->json(['ok' => true]));
});

function novaGates(): array
{
    return array_fill_keys(PlanLimitService::FEATURE_KEYS, true);
}

test('nova voice is enabled by default and the topbar config is on', function () {
    $user = User::factory()->create();

    expect(NovaVoiceNavigationService::companyVoiceEnabled($user->company))->toBeTrue();
    $config = app(NovaVoiceNavigationService::class)->clientConfig($user, novaGates());
    expect($config['enabled'])->toBeTrue();

    $this->actingAs($user)->postJson('/_test/nova-voice')->assertOk();
});

test('disabling nova voice removes the topbar widget and refuses voice endpoints with 403 json', function () {
    $user = User::factory()->create();
    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $user->company_id], ['nova_voice_enabled' => false]);

    $config = app(NovaVoiceNavigationService::class)->clientConfig($user, novaGates());
    expect($config)->toBe(['enabled' => false]);

    $html = view('components.ai.nova-voice-nav', ['flowdeskNovaVoiceNav' => $config])->render();
    expect($html)->not->toContain('flow-nova-topbar')->not->toContain('novaVoiceNav');

    $this->actingAs($user)->postJson('/_test/nova-voice')
        ->assertForbidden()
        ->assertJson(['error' => 'nova_voice_disabled']);
});

test('enabled config renders the topbar widget', function () {
    $user = User::factory()->create();
    $config = app(NovaVoiceNavigationService::class)->clientConfig($user, novaGates());
    $html = view('components.ai.nova-voice-nav', ['flowdeskNovaVoiceNav' => $config])->render();
    expect($html)->toContain('flow-nova-topbar');
});

test('company admin can switch nova voice off and on from settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('settings.nova-voice'))->assertOk()->assertSee('nova-voice-toggle', false);

    $this->actingAs($user)->put(route('settings.nova-voice.toggle'), ['nova_voice_enabled' => 0])
        ->assertRedirect(route('settings.nova-voice'));
    expect(NovaVoiceNavigationService::companyVoiceEnabled($user->company->fresh()))->toBeFalse();

    $this->actingAs($user)->put(route('settings.nova-voice.toggle'), ['nova_voice_enabled' => 1]);
    expect(NovaVoiceNavigationService::companyVoiceEnabled($user->company->fresh()))->toBeTrue();
});

test('team members cannot change the nova voice switch', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create(['company_id' => $admin->company_id]);
    $member->syncRoles(['team_member']);

    $status = $this->actingAs($member)->put(route('settings.nova-voice.toggle'), ['nova_voice_enabled' => 0])->status();
    expect($status)->toBeIn([302, 403]);
    expect(NovaVoiceNavigationService::companyVoiceEnabled($admin->company->fresh()))->toBeTrue();
});
