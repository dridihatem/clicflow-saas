<?php

use App\Models\PlatformSetting;
use App\Services\GoogleGeminiTextToSpeechService;
use Illuminate\Support\Facades\DB;

test('gemini tts does not crash when encrypted google key cannot be decrypted', function () {
    DB::table('platform_settings')->insert([
        'google_api_key_encrypted' => 'eyJpdiI6InRlc3QiLCJ2YWx1ZSI6InRlc3QiLCJtYWMiOiJpbnZhbGlkIn0=',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(GoogleGeminiTextToSpeechService::class);

    expect($service->isConfigured())->toBeFalse();
    expect(fn () => $service->synthesize('Hello'))->toThrow(RuntimeException::class, 'Google (Gemini) API key is not configured.');
});

test('gemini tts reads google key encrypted with current app key', function () {
    PlatformSetting::query()->create([
        'google_api_key_encrypted' => 'google-secret-key',
    ]);

    expect(app(GoogleGeminiTextToSpeechService::class)->isConfigured())->toBeTrue();
});
