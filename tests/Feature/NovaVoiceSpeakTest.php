<?php

use App\Models\PlatformSetting;
use App\Models\UsageTracking;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('nova speak endpoint requires authentication', function () {
    $this->post(route('assistant.speak'), ['text' => 'Hello'])
        ->assertRedirect();
});

test('nova speak returns audio when openai tts is configured', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'openai_tts_model' => 'tts-1-hd',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => 'Yes Alex, what would you like?'])
        ->assertOk()
        ->assertHeader('Content-Type', 'audio/mpeg')
        ->assertHeader('X-AI-Credits-Cost', '5');
});

test('nova speak strips markdown before openai tts request', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'openai_tts_model' => 'tts-1-hd',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => '**Revenus** ce mois: 5200 EUR'])
        ->assertOk();

    Http::assertSent(function (Request $request) {
        $input = $request->data()['input'] ?? '';

        return str_contains($request->url(), 'audio/speech')
            && $input === 'Revenus ce mois: 5200 EUR'
            && ! str_contains($input, '*');
    });
});

test('nova speak returns 503 when browser voice only is selected', function () {
    PlatformSetting::query()->create([
        'tts_provider' => 'browser',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => 'Hello'])
        ->assertStatus(503)
        ->assertJson([
            'fallback' => 'browser',
            'code' => 'tts_unconfigured',
        ]);
});

test('nova speak falls back to edge when openai tts is selected without paid subscription', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    $this->mock(\App\Services\MicrosoftEdgeTextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('publicConfig')->andReturn(['available' => true, 'voice' => 'en-US-JennyNeural', 'locales' => []]);
        $mock->shouldReceive('synthesize')->andReturn([
            'binary' => 'fake-edge-mp3',
            'mime' => 'audio/mpeg',
            'provider' => 'edge',
            'voice' => 'en-US-JennyNeural',
        ]);
    });

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    $plan = \App\Models\Plan::factory()->create(['price_monthly' => 29]);
    \App\Models\PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'ai_credits',
        'limit_value' => 10000,
    ]);
    \App\Models\PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'premium_tts',
        'limit_value' => 0,
    ]);
    \App\Models\Subscription::factory()->create([
        'company_id' => $user->company_id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'trial_ends_at' => null,
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => 'Hello'])
        ->assertOk()
        ->assertHeader('X-Nova-TTS-Provider', 'edge');
});

test('nova speak falls back to edge when openai tts is selected without key', function () {
    $this->mock(\App\Services\MicrosoftEdgeTextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('publicConfig')->andReturn(['available' => true, 'voice' => 'en-US-JennyNeural', 'locales' => []]);
        $mock->shouldReceive('synthesize')->andReturn([
            'binary' => 'fake-edge-mp3',
            'mime' => 'audio/mpeg',
            'provider' => 'edge',
            'voice' => 'en-US-JennyNeural',
        ]);
    });

    PlatformSetting::query()->create([
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    $plan = \App\Models\Plan::factory()->create(['price_monthly' => 29]);
    \App\Models\PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'ai_credits',
        'limit_value' => 10000,
    ]);
    \App\Models\PlanLimit::query()->create([
        'plan_id' => $plan->id,
        'feature_key' => 'premium_tts',
        'limit_value' => 0,
    ]);
    \App\Models\Subscription::factory()->create([
        'company_id' => $user->company_id,
        'plan_id' => $plan->id,
        'status' => 'active',
        'trial_ends_at' => null,
        'current_period_end' => now()->addMonth(),
    ]);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => 'Hello'])
        ->assertOk()
        ->assertHeader('X-Nova-TTS-Provider', 'edge');
});

test('nova speak returns credit limit json with browser fallback', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'openai_tts_model' => 'tts-1-hd',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    $company = $user->company;
    seedPaidPremiumTtsForCompany($company, 1);
    UsageTracking::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'metric' => 'ai_credits',
        'value' => 1,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
    ]);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), ['text' => 'Hello'])
        ->assertStatus(403)
        ->assertJson([
            'fallback' => 'browser',
            'code' => 'ai_credits_limit',
        ])
        ->assertJsonPath('message', __('ai_credits_insufficient', [
            'required' => 5,
            'remaining' => 0,
        ]));
});

test('nova speak ui chrome and prefetch lines do not consume ai credits', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'openai_tts_model' => 'tts-1-hd',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $before = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    $line = __('nova_voice_wake_reply_listening');

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), [
            'text' => $line,
            'purpose' => 'ui',
        ])
        ->assertOk()
        ->assertHeader('X-AI-Credits-Charged', '0')
        ->assertHeader('X-AI-Credits-Cost', '0');

    $after = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    expect($after)->toBe($before);
});

test('nova speak ui navigating lines with page label do not consume ai credits', function () {
    $this->mock(\App\Services\MicrosoftEdgeTextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('publicConfig')->andReturn(['available' => true, 'voice' => 'en-US-JennyNeural', 'locales' => []]);
        $mock->shouldReceive('synthesize')->once()->andReturn([
            'binary' => 'fake-edge-mp3',
            'mime' => 'audio/mpeg',
            'provider' => 'edge',
            'voice' => 'en-US-JennyNeural',
        ]);
    });

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $before = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    $line = __('nova_voice_navigating').' Dashboard';

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), [
            'text' => $line,
            'purpose' => 'ui',
        ])
        ->assertOk()
        ->assertHeader('X-AI-Credits-Charged', '0')
        ->assertHeader('X-Nova-TTS-Provider', 'edge');

    $after = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    expect($after)->toBe($before);
});

test('nova speak ui chrome prefers edge even when google tts is selected', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'should-not-call-gemini']], 500),
    ]);

    $this->mock(\App\Services\MicrosoftEdgeTextToSpeechService::class, function ($mock) {
        $mock->shouldReceive('isConfigured')->andReturn(true);
        $mock->shouldReceive('publicConfig')->andReturn(['available' => true, 'voice' => 'en-US-JennyNeural', 'locales' => []]);
        $mock->shouldReceive('synthesize')->once()->andReturn([
            'binary' => 'fake-edge-mp3',
            'mime' => 'audio/mpeg',
            'provider' => 'edge',
            'voice' => 'en-US-JennyNeural',
        ]);
    });

    PlatformSetting::query()->create([
        'google_api_key_encrypted' => 'google-test-key',
        'gemini_tts_model' => 'gemini-3.1-flash-tts-preview',
        'tts_provider' => 'google',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), [
            'text' => __('nova_voice_wake_reply_listening'),
            'purpose' => 'ui',
        ])
        ->assertOk()
        ->assertHeader('X-Nova-TTS-Provider', 'edge')
        ->assertHeader('X-AI-Credits-Charged', '0');
});

test('nova speak content purpose still charges credits', function () {
    Http::fake([
        'api.openai.com/v1/audio/speech' => Http::response('fake-mp3-bytes', 200, ['Content-Type' => 'audio/mpeg']),
    ]);

    PlatformSetting::query()->create([
        'openai_api_key_encrypted' => 'sk-test-key',
        'openai_tts_voice' => 'nova',
        'openai_tts_model' => 'tts-1-hd',
        'tts_provider' => 'openai',
    ]);

    $user = User::factory()->create();
    seedPaidPremiumTtsForCompany($user->company);

    $before = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    $this->actingAs($user)
        ->postJson(route('assistant.speak'), [
            'text' => 'Custom briefing answer for the workspace.',
            'purpose' => 'content',
        ])
        ->assertOk()
        ->assertHeader('X-AI-Credits-Charged', '5');

    $after = (int) UsageTracking::query()->withoutGlobalScopes()
        ->where('company_id', $user->company_id)
        ->where('metric', 'ai_credits')
        ->sum('value');

    expect($after)->toBe($before + 5);
});
