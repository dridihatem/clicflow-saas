<?php

use App\Mail\ClientPortalAccessLinkMail;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function accessTabAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);

    return $admin;
}

test('client edit form no longer shows the portal password card', function () {
    $admin = accessTabAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'c1@example.test']);

    $this->actingAs($admin)->get(route('clients.edit', $client))
        ->assertOk()
        ->assertDontSee('name="portal_password"', false)
        ->assertSee(__('client_portal_tab_label'));
});

test('client page has a portal access tab holding the access card', function () {
    $admin = accessTabAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'c2@example.test']);

    $this->actingAs($admin)->get(route('clients.show', ['client' => $client, 'tab' => 'portal']))
        ->assertOk()
        ->assertSee("tab === 'portal'", false)
        ->assertSee(__('client_portal_panel_heading'))
        ->assertSee('name="send_mode"', false);
});

test('creating access with link mode emails a set-password link without plaintext password', function () {
    Mail::fake();
    $admin = accessTabAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'c3@example.test']);

    $this->actingAs($admin)->post(route('clients.portal.store', $client), [
        'email' => 'c3@example.test',
        'send_mode' => 'link',
    ])->assertRedirect(route('clients.show', ['client' => $client, 'tab' => 'portal']));

    expect($client->fresh()->user)->not->toBeNull();
    Mail::assertSent(ClientPortalAccessLinkMail::class, function ($m) {
        return str_contains($m->setPasswordUrl, 'reset-password') && $m->email === 'c3@example.test';
    });
});

test('send access button emails the link for an existing account', function () {
    Mail::fake();
    $admin = accessTabAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'c4@example.test']);
    $this->actingAs($admin)->post(route('clients.portal.store', $client), ['email' => 'c4@example.test', 'send_mode' => 'none']);

    $this->actingAs($admin)->post(route('clients.portal.send-access', $client))
        ->assertRedirect(route('clients.show', ['client' => $client, 'tab' => 'portal']))
        ->assertSessionHas('status');

    Mail::assertSent(ClientPortalAccessLinkMail::class, 1);
});
