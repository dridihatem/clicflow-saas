<?php

use App\Models\Prospect;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('data import index includes prospects', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('import.index'))
        ->assertOk()
        ->assertSee(__('Prospects'))
        ->assertSee(route('import.example', 'prospects'), false);
});

test('data import can store prospects from csv', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $csv = "name,email,phone,company,source\nAlex Rivera,alex@example.com,+15550200,Rivera Studio,import\n";

    $this->actingAs($user)
        ->post(route('import.store', 'prospects'), [
            'raw' => $csv,
            'filename' => 'leads.csv',
        ])
        ->assertRedirect(route('import.index'));

    expect(Prospect::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('email', 'alex@example.com')
        ->exists())->toBeTrue();
});
