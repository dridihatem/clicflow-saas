<?php

use App\Models\Admin;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('platform admin can view admin dashboard', function () {
    actingAsAdmin();

    $this->get(route('admin.dashboard'))->assertOk();
});

test('platform admin can view platform reports', function () {
    actingAsAdmin();

    $this->get(route('admin.reports.index'))->assertOk();
});

test('platform admin can view workspace theme defaults', function () {
    actingAsAdmin();

    $this->get(route('admin.platform-appearance.edit'))->assertOk();
});

test('platform admin can view payment gateway settings', function () {
    actingAsAdmin();

    $this->get(route('admin.payment-gateways.edit'))->assertOk();
});

test('platform admin can view and update profile in admin layout', function () {
    $admin = Admin::factory()->create([
        'name' => 'Admin User',
        'email' => 'admin-profile@example.com',
    ]);

    $this->actingAs($admin, 'admin')
        ->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertSee('Admin User')
        ->assertSee('admin-profile@example.com');

    $this->actingAs($admin, 'admin')
        ->patch(route('admin.profile.update'), [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
            'locale' => 'en',
        ])
        ->assertRedirect(route('admin.profile.edit'));

    expect($admin->fresh()->name)->toBe('Updated Admin');
    expect($admin->fresh()->email)->toBe('updated-admin@example.com');
});

test('company admin cannot view admin profile page', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);

    $this->actingAs($user)
        ->get(route('admin.profile.edit'))
        ->assertRedirect(route('admin.login'));
});

test('company admin cannot view admin dashboard', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});
