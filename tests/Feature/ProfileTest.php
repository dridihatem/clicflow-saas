<?php

use App\Models\Company;
use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('profile can set personal default currency', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'default_currency' => 'EUR',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    expect($user->fresh()->default_currency)->toBe('EUR');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', 'password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});

test('workspace manager profile shows marketing and embed section', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $company = Company::factory()->create();
    $plan = \App\Models\Plan::factory()->create();
    \App\Models\PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'marketing_hub',
        'limit_value' => 1,
    ]);
    \App\Models\Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);

    $response = $this->actingAs($user)->get('/profile');

    $response->assertOk();
    $response->assertSee(__('Profile group marketing'));
    $response->assertSee(__('Company API token (embed)'));
});

test('workspace manager can regenerate embed token from profile', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $company = Company::factory()->create(['api_token_hash' => null]);
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->syncRoles(['company_admin']);

    $response = $this->actingAs($user)->post(route('profile.embed-token.regenerate'));

    $response->assertRedirect(route('profile.edit'));
    $company->refresh();
    expect($company->api_token_hash)->not->toBeNull();
});

test('client profile shows profile and password only without currency or security links', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $user = User::factory()->create();
    $user->syncRoles(['client']);
    \App\Models\Client::factory()->create([
        'company_id' => $user->company_id,
        'user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee(__('Profile Information'))
        ->assertSee(__('Update Password'))
        ->assertDontSee(__('Default currency (personal)'))
        ->assertDontSee(__('Settings hub two factor'))
        ->assertDontSee(__('Settings hub security'))
        ->assertSee(__('Profile group security description client'))
        ->assertSee(__('Delete Account'));
});

test('client cannot update personal default currency via profile', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $user = User::factory()->create(['default_currency' => null]);
    $user->syncRoles(['client']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'default_currency' => 'EUR',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->default_currency)->toBeNull();
});
