<?php

use App\Models\Admin;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\NovaVoiceNavigationService;
use App\Services\PlanLimitService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Route::middleware(['web', 'auth', 'nova.voice'])->post('/_test/nova-voice-admin', fn () => response()->json(['ok' => true]));
});

test('platform admin disables nova voice: no widget, 403 on voice endpoints, status off, company preference kept', function () {
    $user = User::factory()->create();
    $company = $user->company;
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')->get(route('admin.companies.show', $company))
        ->assertOk()->assertSee(__('admin_nova_voice_disable'));

    $this->actingAs($admin, 'admin')
        ->put(route('admin.companies.nova-voice', $company), ['admin_nova_voice_enabled' => 0])
        ->assertRedirect(route('admin.companies.show', $company));

    $company->refresh();
    expect((bool) $company->admin_nova_voice_enabled)->toBeFalse()
        ->and(NovaVoiceNavigationService::companyVoiceEnabled($company))->toBeFalse()
        ->and(NovaVoiceNavigationService::companyPreferenceEnabled($company))->toBeTrue();

    $config = app(NovaVoiceNavigationService::class)->clientConfig($user, array_fill_keys(PlanLimitService::FEATURE_KEYS, true));
    expect($config)->toBe(['enabled' => false]);

    $this->actingAs($user)->postJson('/_test/nova-voice-admin')->assertStatus(403)->assertJson(['error' => 'nova_voice_disabled']);
    $this->actingAs($user)->getJson(route('assistant.voice-status'))->assertOk()->assertJson(['enabled' => false, 'admin_enabled' => false]);

    // Pages tell the browser voice is off and clear the stored mic pause.
    $this->actingAs($user)->get(route('dashboard'))->assertOk()
        ->assertSee('window.flowdeskNovaVoice', false)
        ->assertSee('flowdesk.nova.listeningPaused.', false);
});

test('admin re-enable restores voice, but a company that switched it off stays off', function () {
    $user = User::factory()->create();
    $company = $user->company;
    $admin = Admin::factory()->create();
    $company->forceFill(['admin_nova_voice_enabled' => false])->save();

    $this->actingAs($admin, 'admin')->put(route('admin.companies.nova-voice', $company), ['admin_nova_voice_enabled' => 1])->assertRedirect();
    expect(NovaVoiceNavigationService::companyVoiceEnabled($company->fresh()))->toBeTrue();
    $this->actingAs($user)->postJson('/_test/nova-voice-admin')->assertOk();
    $this->actingAs($user)->getJson(route('assistant.voice-status'))->assertJson(['enabled' => true]);

    CompanySetting::query()->withoutGlobalScopes()->updateOrCreate(['company_id' => $company->id], ['nova_voice_enabled' => false]);
    expect(NovaVoiceNavigationService::companyVoiceEnabled($company->fresh()))->toBeFalse();
});

test('company settings page explains an admin disable', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    $user->company->forceFill(['admin_nova_voice_enabled' => false])->save();

    $this->actingAs($user)->get(route('settings.nova-voice'))->assertOk()->assertSee(__('nova_voice_admin_disabled_notice'));
});

test('non admin users cannot hit the admin nova switch', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->put(route('admin.companies.nova-voice', $user->company), ['admin_nova_voice_enabled' => 0]);
    expect(NovaVoiceNavigationService::adminVoiceEnabled($user->company->fresh()))->toBeTrue();
});
