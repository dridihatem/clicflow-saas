<?php

namespace App\Services;

use App\Exceptions\TtsRateLimitedException;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CartesiaTextToSpeechService
{
    private const API_BASE = 'https://api.cartesia.ai';

    private const API_VERSION = '2026-03-01';

    private const DEFAULT_MODEL = 'sonic-3';

    /** Skylar — friendly English default. */
    private const DEFAULT_VOICE = 'db6b0ed5-d5d3-463d-ae85-518a07d3c2b4';

    /**
     * SaaS UI locales that have Cartesia voices in this catalog.
     *
     * @var list<string>
     */
    public const SAAS_LANGUAGES = ['en', 'fr', 'es', 'ar', 'hi', 'id'];

    /**
     * Default voice id per SaaS language (used when no company/platform voice is set).
     *
     * @var array<string, string>
     */
    public const DEFAULT_VOICE_BY_LANGUAGE = [
        'en' => 'db6b0ed5-d5d3-463d-ae85-518a07d3c2b4', // Skylar
        'fr' => 'faa75703-00e3-4a57-9955-0703001e3231', // Amélie
        'es' => 'de38f545-c574-44e8-9b54-a7d6fec1c6b1', // Marta
        'ar' => '002622d8-19d0-4567-a16a-f99c7397c062', // Huda
        'hi' => 'faf0731e-dfb9-4cfc-8119-259a79b27e12', // Riya
        'id' => 'b441c4fd-4910-4c55-ae56-f0291057e2cc', // Siti
    ];

    /**
     * Curated Cartesia voices for ClicFlow locales (en, fr, es, ar, hi, id).
     * Companies may also paste any voice UUID from play.cartesia.ai/voices.
     *
     * @var list<array{id: string, name: string, tagline: string, language: string, gender: string}>
     */
    public const VOICES = [
        // English
        ['id' => 'db6b0ed5-d5d3-463d-ae85-518a07d3c2b4', 'name' => 'Skylar', 'tagline' => 'Friendly Guide', 'language' => 'en', 'gender' => 'feminine'],
        ['id' => 'f786b574-daa5-4673-aa0c-cbe3e8534c02', 'name' => 'Katie', 'tagline' => 'Friendly Fixer', 'language' => 'en', 'gender' => 'feminine'],
        ['id' => '62ae83ad-4f6a-430b-af41-a9bede9286ca', 'name' => 'Gemma', 'tagline' => 'Decisive Agent', 'language' => 'en', 'gender' => 'feminine'],
        ['id' => '47c38ca4-5f35-497b-b1a3-415245fb35e1', 'name' => 'Daniel', 'tagline' => 'Modern Assistant', 'language' => 'en', 'gender' => 'masculine'],
        ['id' => '630ed21c-2c5c-41cf-9d82-10a7fd668370', 'name' => 'Corey', 'tagline' => 'Supportive Buddy', 'language' => 'en', 'gender' => 'masculine'],
        ['id' => 'a167e0f3-df7e-4d52-a9c3-f949145efdab', 'name' => 'Blake', 'tagline' => 'Helpful Agent', 'language' => 'en', 'gender' => 'masculine'],
        // French
        ['id' => 'faa75703-00e3-4a57-9955-0703001e3231', 'name' => 'Amélie', 'tagline' => 'Decisive Agent', 'language' => 'fr', 'gender' => 'feminine'],
        ['id' => '7c58f4a4-a72c-42fa-a503-41b9408820f3', 'name' => 'Inès', 'tagline' => 'Poised Communicator', 'language' => 'fr', 'gender' => 'feminine'],
        ['id' => 'a8a1eb38-5f15-4c1d-8722-7ac0f329727d', 'name' => 'Calm French Woman', 'tagline' => 'Soft narrator', 'language' => 'fr', 'gender' => 'feminine'],
        ['id' => '65b25c5d-ff07-4687-a04c-da2f43ef6fa9', 'name' => 'Pauline', 'tagline' => 'Helpful Companion', 'language' => 'fr', 'gender' => 'feminine'],
        ['id' => '0418348a-0ca2-4e90-9986-800fb8b3bbc0', 'name' => 'Antoine', 'tagline' => 'Stern Man', 'language' => 'fr', 'gender' => 'masculine'],
        ['id' => 'ce74c4da-4aee-435d-bc6d-81d1a9367e12', 'name' => 'Marc', 'tagline' => 'Conversational Buddy', 'language' => 'fr', 'gender' => 'masculine'],
        ['id' => '93c98a2b-7d15-4f7b-8236-294b1e02b1c0', 'name' => 'Mathieu', 'tagline' => 'Assured Expert', 'language' => 'fr', 'gender' => 'masculine'],
        // Spanish
        ['id' => 'de38f545-c574-44e8-9b54-a7d6fec1c6b1', 'name' => 'Marta', 'tagline' => 'Friendly Guide', 'language' => 'es', 'gender' => 'feminine'],
        ['id' => '5c5ad5e7-1020-476b-8b91-fdcbe9cc313c', 'name' => 'Daniela', 'tagline' => 'Relaxed Woman', 'language' => 'es', 'gender' => 'feminine'],
        ['id' => 'c0925108-d541-4dc4-bbae-39f4e57ba10c', 'name' => 'Lucia', 'tagline' => 'Radiant Host', 'language' => 'es', 'gender' => 'feminine'],
        ['id' => '9d8c6b2e-0a23-4a15-ae1b-121d5b5af417', 'name' => 'Nuria', 'tagline' => 'Trusted Advisor', 'language' => 'es', 'gender' => 'feminine'],
        ['id' => 'b0689631-eee7-4a6c-bb86-195f1d267c2e', 'name' => 'Emilio', 'tagline' => 'Friendly Optimist', 'language' => 'es', 'gender' => 'masculine'],
        ['id' => '15d0c2e2-8d29-44c3-be23-d585d5f154a1', 'name' => 'Pedro', 'tagline' => 'Formal Speaker', 'language' => 'es', 'gender' => 'masculine'],
        // Arabic
        ['id' => '002622d8-19d0-4567-a16a-f99c7397c062', 'name' => 'Huda', 'tagline' => 'Approachable Speaker', 'language' => 'ar', 'gender' => 'feminine'],
        ['id' => '731ace69-ee17-41bc-8c6f-665c9f1db95c', 'name' => 'Fatima', 'tagline' => 'Graceful Guide', 'language' => 'ar', 'gender' => 'feminine'],
        ['id' => 'fc923f89-1de5-4ddf-b93c-6da2ba63428a', 'name' => 'Nour', 'tagline' => 'Engaging Speaker', 'language' => 'ar', 'gender' => 'feminine'],
        ['id' => '9825cf5f-6aff-412a-80c5-bc58a8d55bc4', 'name' => 'Maryam', 'tagline' => 'Friendly Voice', 'language' => 'ar', 'gender' => 'feminine'],
        ['id' => '9cbad5f7-fbf6-4416-a22f-1ecc75ad40a2', 'name' => 'Youssef', 'tagline' => 'Clear Communicator', 'language' => 'ar', 'gender' => 'masculine'],
        ['id' => 'db873303-3a70-4d9d-867a-0d70a6377195', 'name' => 'Tariq', 'tagline' => 'Wise Advisor', 'language' => 'ar', 'gender' => 'masculine'],
        // Hindi
        ['id' => 'faf0731e-dfb9-4cfc-8119-259a79b27e12', 'name' => 'Riya', 'tagline' => 'College Roommate', 'language' => 'hi', 'gender' => 'feminine'],
        ['id' => '95d51f79-c397-46f9-b49a-23763d3eaa2d', 'name' => 'Arushi', 'tagline' => 'Hinglish Speaker', 'language' => 'hi', 'gender' => 'feminine'],
        ['id' => '6b02ffe5-e3cb-48c0-a023-c72f85953375', 'name' => 'Sneha', 'tagline' => 'Empathetic Voice', 'language' => 'hi', 'gender' => 'feminine'],
        ['id' => '56e35e2d-6eb6-4226-ab8b-9776515a7094', 'name' => 'Kavita', 'tagline' => 'Customer Care Agent', 'language' => 'hi', 'gender' => 'feminine'],
        ['id' => '4877b818-c7fe-4c89-b1cf-eadf8e23da72', 'name' => 'Rohan', 'tagline' => 'Steady Communicator', 'language' => 'hi', 'gender' => 'masculine'],
        ['id' => '098fb15d-2597-4186-8b74-25340050b6e7', 'name' => 'Vishal', 'tagline' => 'Assured Expert', 'language' => 'hi', 'gender' => 'masculine'],
        ['id' => '910fb75e-1d20-4840-ac63-ac6b26a71bdc', 'name' => 'Dev', 'tagline' => 'Friendly Host', 'language' => 'hi', 'gender' => 'masculine'],
        // Indonesian
        ['id' => 'b441c4fd-4910-4c55-ae56-f0291057e2cc', 'name' => 'Siti', 'tagline' => 'Ad Narrator', 'language' => 'id', 'gender' => 'feminine'],
        ['id' => 'a053f6bc-7df4-40de-96d4-de026bc47ce8', 'name' => 'Andi', 'tagline' => 'Dynamic Presenter', 'language' => 'id', 'gender' => 'masculine'],
    ];

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @param  array{voice?: string|null}  $options
     * @return array{binary: string, mime: string, provider: string, model: string, voice: string}
     */
    public function synthesize(string $text, ?string $locale = null, array $options = []): array
    {
        $transcript = flowdesk_sanitize_speech_text($text);
        if ($transcript === '') {
            throw new RuntimeException('Speech text is empty.');
        }

        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException('Cartesia API key is not configured.');
        }

        $row = PlatformSetting::query()->first();
        $model = $this->normalizeModel($row?->cartesia_tts_model);
        $voice = $this->normalizeVoice($options['voice'] ?? $row?->cartesia_tts_voice, $locale);
        $language = $this->languageForVoice($voice) ?? $this->languageCode($locale) ?? 'en';

        try {
            /** @var Response $response */
            // Keep under php artisan serve's default max_execution_time (30s + hard_timeout)
            // so a hung TTS call throws ConnectionException instead of a fatal in CurlHandler.
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withHeaders([
                    'Cartesia-Version' => self::API_VERSION,
                    'X-API-Key' => $apiKey,
                    'Authorization' => 'Bearer '.$apiKey,
                    'Accept' => 'audio/mpeg, audio/wav, audio/*',
                ])
                ->post(self::API_BASE.'/tts/bytes', array_filter([
                    'model_id' => $model,
                    'transcript' => mb_substr($transcript, 0, 4096),
                    'voice' => [
                        'mode' => 'id',
                        'id' => $voice,
                    ],
                    'language' => $language,
                    // Cartesia: 1.0 = normal pace (do not speed up Nova).
                    'speed' => 1.0,
                    'output_format' => [
                        'container' => 'mp3',
                        'sample_rate' => 44100,
                    ],
                ], fn ($value) => $value !== null));
        } catch (ConnectionException $e) {
            Log::warning('Cartesia TTS connection error', ['message' => $e->getMessage()]);

            throw new RuntimeException(__('Cartesia TTS is temporarily unavailable. Try again shortly.'));
        }

        if (! $response->successful()) {
            Log::warning('Cartesia TTS error', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);

            $message = $this->errorMessage($response);
            if ($response->status() === 429) {
                throw new TtsRateLimitedException($message);
            }

            throw new RuntimeException($message);
        }

        $binary = $response->body();
        if ($binary === '') {
            throw new RuntimeException('Empty audio from Cartesia TTS.');
        }

        $mime = $this->detectMime($response->header('Content-Type'), $binary);

        return [
            'binary' => $binary,
            'mime' => $mime,
            'provider' => 'cartesia',
            'model' => $model,
            'voice' => $voice,
        ];
    }

    /**
     * @return array{available: bool, voice: string, model: string, catalog: array<string, string>, catalog_by_language: array<string, array<string, string>>}
     */
    public function publicConfig(?string $voiceOverride = null): array
    {
        $row = PlatformSetting::query()->first();

        return [
            'available' => $this->isConfigured(),
            'voice' => $this->normalizeVoice($voiceOverride ?? $row?->cartesia_tts_voice),
            'model' => $this->normalizeModel($row?->cartesia_tts_model),
            'catalog' => $this->catalog(),
            'catalog_by_language' => $this->catalogGroupedByLanguage(),
        ];
    }

    /**
     * Flat id => "Name — tagline (LANG)" map.
     *
     * @return array<string, string>
     */
    public function catalog(): array
    {
        $out = [];
        foreach (self::VOICES as $voice) {
            $out[$voice['id']] = $this->formatVoiceLabel($voice);
        }

        return $out;
    }

    /**
     * language => [id => label] in SaaS locale order.
     *
     * @return array<string, array<string, string>>
     */
    public function catalogGroupedByLanguage(): array
    {
        $grouped = [];
        foreach (self::SAAS_LANGUAGES as $lang) {
            $grouped[$lang] = [];
        }

        foreach (self::VOICES as $voice) {
            $lang = $voice['language'];
            if (! isset($grouped[$lang])) {
                $grouped[$lang] = [];
            }
            $grouped[$lang][$voice['id']] = $this->formatVoiceLabel($voice, withLanguage: false);
        }

        return array_filter($grouped, fn (array $voices): bool => $voices !== []);
    }

    /**
     * @return array<string, string> language code => display label
     */
    public function languageLabels(): array
    {
        return [
            'en' => __('English'),
            'fr' => __('French'),
            'es' => __('Spanish'),
            'ar' => __('Arabic'),
            'hi' => __('Hindi'),
            'id' => __('Indonesian'),
        ];
    }

    public function languageForVoice(string $voiceId): ?string
    {
        foreach (self::VOICES as $voice) {
            if ($voice['id'] === $voiceId) {
                return $voice['language'];
            }
        }

        return null;
    }

    /**
     * Best default Cartesia voice for a UI / speech locale.
     */
    public function defaultVoiceForLocale(?string $locale = null): string
    {
        $code = $this->languageCode($locale) ?? 'en';

        return self::DEFAULT_VOICE_BY_LANGUAGE[$code] ?? self::DEFAULT_VOICE;
    }

    /**
     * @param  array{id: string, name: string, tagline: string, language: string, gender: string}  $voice
     */
    public function formatVoiceLabel(array $voice, bool $withLanguage = true): string
    {
        $base = trim($voice['name'].($voice['tagline'] !== '' ? ' — '.$voice['tagline'] : ''));
        if (! $withLanguage) {
            return $base;
        }

        return $base.' ('.strtoupper($voice['language']).')';
    }

    public function normalizeVoice(?string $voice, ?string $locale = null): string
    {
        $voice = trim((string) $voice);
        if ($this->isVoiceId($voice)) {
            return $voice;
        }

        $row = PlatformSetting::query()->first();
        $fallback = trim((string) ($row?->cartesia_tts_voice ?? ''));
        if ($this->isVoiceId($fallback)) {
            return $fallback;
        }

        return $this->defaultVoiceForLocale($locale);
    }

    public function isVoiceId(string $voice): bool
    {
        return (bool) preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $voice,
        );
    }

    private function apiKey(): ?string
    {
        $row = PlatformSetting::query()->first();
        $fromDb = flowdesk_decrypt_model_attribute($row, 'cartesia_api_key_encrypted');
        if ($fromDb !== null) {
            return $fromDb;
        }

        $fromEnv = trim((string) env('CARTESIA_API_KEY', ''));

        return $fromEnv !== '' ? $fromEnv : null;
    }

    private function normalizeModel(?string $model): string
    {
        $model = trim((string) $model);

        return $model !== '' ? $model : self::DEFAULT_MODEL;
    }

    private function languageCode(?string $locale): ?string
    {
        $locale = strtolower(trim((string) $locale));
        if ($locale === '') {
            return null;
        }

        $base = explode('-', str_replace('_', '-', $locale))[0] ?? '';

        return in_array($base, self::SAAS_LANGUAGES, true) ? $base : null;
    }

    private function detectMime(?string $contentType, string $binary): string
    {
        $contentType = strtolower((string) $contentType);
        if (str_contains($contentType, 'mpeg') || str_contains($contentType, 'mp3')) {
            return 'audio/mpeg';
        }
        if (str_contains($contentType, 'wav')) {
            return 'audio/wav';
        }
        if (str_starts_with($binary, 'ID3') || str_starts_with($binary, "\xFF\xFB")) {
            return 'audio/mpeg';
        }
        if (str_starts_with($binary, 'RIFF')) {
            return 'audio/wav';
        }

        return 'audio/mpeg';
    }

    private function errorMessage(Response $response): string
    {
        $json = $response->json();
        $msg = is_array($json)
            ? (string) ($json['message'] ?? $json['error'] ?? $json['title'] ?? '')
            : '';

        return match ($response->status()) {
            401, 403 => __('Cartesia TTS rejected the API key. Check platform settings.'),
            429 => __('Cartesia TTS rate limit reached. Another voice provider is used automatically when available.'),
            default => __('Cartesia TTS request failed').($msg !== '' ? ": {$msg}" : '.'),
        };
    }
}
