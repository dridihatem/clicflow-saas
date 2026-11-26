<?php

use App\Models\Client;
use App\Models\Company;
use App\Models\User;
use App\Services\PortalNavigationService;
use App\Services\PortalVisibilityService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function pvClientUser(): array
{
    $user = User::factory()->create();
    $user->syncRoles(['client']);
    $client = Client::factory()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
    ]);

    return [$user, $client];
}

function pvNavLinks(User $user): array
{
    $gates = array_fill_keys(\App\Services\PlanLimitService::FEATURE_KEYS, true);

    return collect(app(PortalNavigationService::class)->sectionsFor($user, $gates))
        ->where('type', 'link')->pluck('section')->filter()->values()->all();
}

test('everything is visible by default', function () {
    [$user] = pvClientUser();
    $map = app(PortalVisibilityService::class)->mapForUser($user);

    expect(array_unique(array_values($map)))->toBe([true])
        ->and(pvNavLinks($user))->toContain('messages', 'support', 'contracts', 'prospects');
});

test('hidden section disappears from nav and dashboard and direct access is blocked', function () {
    [$user] = pvClientUser();
    app(PortalVisibilityService::class)->saveCompanyHidden(Company::find($user->company_id), ['contracts', 'messages', 'campaigns']);

    $links = pvNavLinks($user);
    expect($links)->not->toContain('contracts')
        ->and($links)->not->toContain('messages')
        ->and($links)->not->toContain('campaigns')
        ->and($links)->toContain('support');

    $this->actingAs($user)->get(route('portal.dashboard'))
        ->assertOk()
        ->assertDontSee(route('portal.campaigns.index'), false)
        ->assertDontSee(route('chat.index'), false)
        ->assertSee(route('tickets.create'), false);

    $this->actingAs($user)->get(route('portal.contracts.index'))
        ->assertRedirect(route('portal.dashboard'))
        ->assertSessionHas('error', __('portal_vis_blocked'));

    $this->actingAs($user)->get(route('chat.index'))
        ->assertRedirect(route('portal.dashboard'));

    $this->actingAs($user)->getJson(route('portal.campaigns.index'))
        ->assertForbidden();
});

test('client override can show a section hidden by the company and hide a visible one', function () {
    [$user, $client] = pvClientUser();
    $service = app(PortalVisibilityService::class);
    $service->saveCompanyHidden(Company::find($user->company_id), ['contracts']);
    $service->saveClientOverrides($client->fresh(), ['contracts' => 'show', 'messages' => 'hide', 'support' => 'inherit']);

    $fresh = app(PortalVisibilityService::class);
    $map = $fresh->mapForUser($user->fresh());
    expect($map['contracts'])->toBeTrue()
        ->and($map['messages'])->toBeFalse()
        ->and($map['support'])->toBeTrue();
});

test('staff are never blocked by portal visibility', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);
    app(PortalVisibilityService::class)->saveCompanyHidden(Company::find($admin->company_id), ['messages']);

    expect(app(PortalVisibilityService::class)->visible($admin, 'messages'))->toBeTrue();
});

test('company admin can open and save the settings page', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);

    $this->actingAs($admin)->get(route('settings.portal-visibility'))
        ->assertOk()
        ->assertSee(__('portal_vis_title'));

    $keys = app(PortalVisibilityService::class)->keys();
    $visible = array_values(array_diff($keys, ['invoices']));

    $this->actingAs($admin)->put(route('settings.portal-visibility.update'), ['visible' => $visible])
        ->assertRedirect(route('settings.portal-visibility'));

    expect(app(PortalVisibilityService::class)->companyHidden(Company::find($admin->company_id)))->toBe(['invoices']);
});

test('clients cannot open the settings page', function () {
    [$user] = pvClientUser();

    $status = $this->actingAs($user)->get(route('settings.portal-visibility'))->getStatusCode();
    expect($status)->toBeIn([302, 403]);
});
