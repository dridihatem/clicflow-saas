<?php

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\NovaVoiceIntentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('voice intent endpoint maps cached duplicate actions without recalling gemini', function () {
    Http::fake();

    PlatformSetting::query()->create([
        'google_api_key_encrypted' => 'google-test-key',
        'gemini_model' => 'gemini-3.5-flash',
        'ai_provider' => 'google',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $service = Mockery::mock(NovaVoiceIntentService::class);
    $service->shouldReceive('resolve')
        ->once()
        ->withArgs(fn ($u, $text) => $u->is($user) && $text === 'ajouter un client')
        ->andReturn([
            'type' => 'command',
            'id' => 'clients.create',
            'label' => 'New client',
            'url' => route('clients.create'),
            'action' => null,
            'cached' => true,
            'confidence' => 0.95,
        ]);
    app()->instance(NovaVoiceIntentService::class, $service);

    $this->actingAs($user)
        ->postJson(route('assistant.voice-intent'), ['text' => 'ajouter un client'])
        ->assertOk()
        ->assertJsonPath('type', 'command')
        ->assertJsonPath('id', 'clients.create')
        ->assertJsonPath('cached', true)
        ->assertJsonPath('ai_credits_charged', 0);

    Http::assertNothingSent();
});

test('voice intent cache returns the same SaaS action for duplicate utterances', function () {
    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);
    $user->load('company');

    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim('new client')) ?? '');
    $cacheKey = 'nova_voice_intent:'.(int) $user->company->id.':'.(int) $user->id.':'.hash('sha256', $normalized);
    Cache::put($cacheKey, [
        'type' => 'command',
        'id' => 'clients.create',
        'confidence' => 0.99,
    ], now()->addHour());

    $first = app(NovaVoiceIntentService::class)->resolve($user, 'new client');
    $second = app(NovaVoiceIntentService::class)->resolve($user, 'new client');

    expect($first['type'])->toBe('command')
        ->and($first['id'])->toBe('clients.create')
        ->and($first['cached'])->toBeTrue()
        ->and($second['cached'])->toBeTrue()
        ->and($second['url'])->toContain('/clients/create');
});

test('voice intent service returns none when llm unavailable', function () {
    $user = User::factory()->create();

    $result = app(NovaVoiceIntentService::class)->resolve($user, 'ouvre les clients');

    expect($result['type'])->toBeIn(['none', 'chat']);
});

test('voice intent config exposes intent url', function () {
    $user = User::factory()->create();
    $gates = array_fill_keys(\App\Services\PlanLimitService::FEATURE_KEYS, true);

    $config = app(\App\Services\NovaVoiceNavigationService::class)->clientConfig($user, $gates);

    expect($config['intentUrl'])->toBe(route('assistant.voice-intent'));
    $ids = collect($config['commands'])->pluck('id')->all();
    expect($ids)->toContain('clients.edit', 'clients.delete', 'invoices.edit', 'proposals.delete');
});
