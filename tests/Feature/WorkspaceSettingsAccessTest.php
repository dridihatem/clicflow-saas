<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('company admin can view workspace smtp settings', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);

    $this->actingAs($user)->get(route('settings.smtp'))->assertOk();
});

test('team member cannot view workspace smtp settings', function () {
    $user = User::factory()->create();
    $user->syncRoles(['team_member']);

    $this->actingAs($user)->get(route('settings.smtp'))->assertForbidden();
});
