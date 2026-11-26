<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('post auth redirect uses tenant subdomain when base domain is configured', function () {
    config([
        'app.url' => 'https://clicflow.fr',
        'flowdesk.tenant_base_domain' => 'clicflow.fr',
    ]);

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $user->company->forceFill(['subdomain' => 'acme'])->saveQuietly();

    $url = flowdesk_post_auth_redirect_url($user->fresh(['company']));

    expect($url)->toStartWith('https://acme.clicflow.fr/');
});

test('socialite-style auth redirect leaves central host for tenant host', function () {
    config([
        'app.url' => 'https://clicflow.fr',
        'flowdesk.tenant_base_domain' => 'clicflow.fr',
    ]);

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $user->company->forceFill(['subdomain' => 'acme'])->saveQuietly();

    $request = Request::create('https://clicflow.fr/auth/google/callback', 'GET');
    $request->setLaravelSession(app('session.store'));

    $response = flowdesk_redirect_after_auth($user->fresh(['company']), $request);

    expect($response->isRedirect())->toBeTrue();
    expect($response->headers->get('Location'))->toStartWith('https://acme.clicflow.fr/');
});
