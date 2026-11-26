<?php

use App\Models\User;

test('company admin can view team settings', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $response = $this->actingAs($user)->get(route('settings.team'));

    $response->assertOk();
});

test('team member cannot access team settings', function () {
    $user = User::factory()->create();
    $user->syncRoles(['team_member']);

    $response = $this->actingAs($user)->get(route('settings.team'));

    $response->assertForbidden();
});

test('team settings list excludes client portal users', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);
    $clientUser = User::factory()->create(['company_id' => $admin->company_id]);
    $clientUser->syncRoles(['client']);

    $response = $this->actingAs($admin)->get(route('settings.team'));

    $response->assertOk();
    $response->assertDontSee($clientUser->email);
});

test('company admin can set team member skills for task assignment', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);
    $member = User::factory()->create(['company_id' => $admin->company_id]);
    $member->syncRoles(['team_member']);

    $this->actingAs($admin)
        ->put(route('settings.team.update', $member), [
            'role' => 'team_member',
            'skills' => 'Design, Frontend; SEO, Design',
        ])
        ->assertRedirect(route('settings.team'));

    $member->refresh();
    expect($member->skills())->toBe(['Design', 'Frontend', 'SEO'])
        ->and($member->assigneeOptionLabel())->toBe($member->name.' · Design, Frontend, SEO');
});

test('company admin can invite teammate with skills', function () {
    $admin = User::factory()->create();
    $admin->syncRoles(['company_admin']);

    $this->actingAs($admin)
        ->post(route('settings.team.store'), [
            'name' => 'Alex Designer',
            'email' => 'alex.skills@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'team_member',
            'skills' => 'UI, Branding',
        ])
        ->assertRedirect(route('settings.team'));

    $created = User::query()->where('email', 'alex.skills@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->skills())->toBe(['UI', 'Branding']);
});
