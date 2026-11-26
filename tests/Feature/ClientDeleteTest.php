<?php

use App\Models\Client;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company admin can delete a client without invoices or projects', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $client = Client::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'name' => 'Deletable Client',
        'email' => 'del@example.com',
    ]);

    $this->actingAs($user)
        ->delete(route('clients.destroy', $client))
        ->assertRedirect(route('clients.index'));

    expect(Client::query()->withoutGlobalScopes()->whereKey($client->id)->whereNull('deleted_at')->exists())->toBeFalse();
    expect(Client::query()->withoutGlobalScopes()->withTrashed()->whereKey($client->id)->exists())->toBeTrue();
});

test('clients index does not list soft deleted clients', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $active = Client::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'name' => 'Active Client',
    ]);
    $deleted = Client::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'name' => 'Deleted Client',
    ]);
    $deleted->delete();

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertSee('Active Client')
        ->assertDontSee('Deleted Client');

    expect($active->fresh())->not->toBeNull();
});

test('show returns 404 for soft deleted client', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $client = Client::query()->withoutGlobalScopes()->create([
        'company_id' => $user->company_id,
        'name' => 'Gone Client',
    ]);
    $id = $client->id;
    $client->delete();

    $this->actingAs($user)
        ->get(route('clients.show', $id))
        ->assertNotFound();
});
