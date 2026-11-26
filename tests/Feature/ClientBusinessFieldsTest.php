<?php

use App\Enums\ClientStatus;
use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function businessFieldsAdmin(): User
{
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);

    return $admin;
}

test('client create stores company, contact, category and inactive status', function () {
    $admin = businessFieldsAdmin();

    $this->actingAs($admin)->post(route('clients.store'), [
        'name' => 'Client Test',
        'email' => 'bf1@example.test',
        'company_name' => '  Société ABC  ',
        'contact_name' => 'Ali Ben Salah',
        'category' => 'PME',
        'status' => 'inactive',
    ])->assertRedirect();

    $client = Client::query()->withoutGlobalScopes()->where('email', 'bf1@example.test')->firstOrFail();
    expect($client->company_name)->toBe('Société ABC')
        ->and($client->contact_name)->toBe('Ali Ben Salah')
        ->and($client->category)->toBe('PME')
        ->and($client->status)->toBe(ClientStatus::Inactive);
});

test('client update saves new fields and rejects unknown status', function () {
    $admin = businessFieldsAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'email' => 'bf2@example.test']);

    $this->actingAs($admin)->put(route('clients.update', $client), [
        'name' => $client->name, 'email' => 'bf2@example.test',
        'company_name' => 'ACME', 'contact_name' => 'Sara', 'category' => 'Startup', 'status' => 'active',
    ])->assertRedirect();
    expect($client->fresh()->category)->toBe('Startup');

    $this->actingAs($admin)->put(route('clients.update', $client), [
        'name' => $client->name, 'email' => 'bf2@example.test', 'status' => 'bogus',
    ])->assertSessionHasErrors('status');
});

test('client edit form shows the new fields', function () {
    $admin = businessFieldsAdmin();
    $client = Client::factory()->create(['company_id' => $admin->company_id, 'category' => 'PME']);

    $this->actingAs($admin)->get(route('clients.edit', $client))->assertOk()
        ->assertSee('name="company_name"', false)
        ->assertSee('name="contact_name"', false)
        ->assertSee('name="category"', false)
        ->assertSee('<option value="inactive"', false);
});

test('client list filters by status and category and searches company', function () {
    $admin = businessFieldsAdmin();
    Client::factory()->create(['company_id' => $admin->company_id, 'name' => 'Alpha Visible', 'company_name' => 'Zeta Corp', 'category' => 'PME', 'status' => 'active']);
    Client::factory()->create(['company_id' => $admin->company_id, 'name' => 'Beta Hidden', 'category' => 'Association', 'status' => 'inactive']);

    $this->actingAs($admin)->get(route('clients.index', ['status' => 'inactive']))->assertOk()
        ->assertSee('Beta Hidden')->assertDontSee('Alpha Visible');
    $this->actingAs($admin)->get(route('clients.index', ['category' => 'PME']))->assertOk()
        ->assertSee('Alpha Visible')->assertDontSee('Beta Hidden');
    $this->actingAs($admin)->get(route('clients.index', ['q' => 'Zeta']))->assertOk()
        ->assertSee('Alpha Visible')->assertDontSee('Beta Hidden');
});
