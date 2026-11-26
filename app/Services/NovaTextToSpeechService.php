<?php

namespace App\Services;

use App\Exceptions\TtsRateLimitedException;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NovaTextToSpeechService
{
    /** Skip a rate-limited vendor for this many seconds so Nova switches immediately. */
    private const RATE_LIMIT_COOLDOWN_SECONDS = 180;

    public function __construct(
        private MicrosoftEdgeTextToSpeechService $edge,
        private GoogleGeminiTextToSpeechService $gemini,
        private OpenAiTextToSpeechService $openai,
        private CartesiaTextToSpeechService $cartesia,
        private PlanLimitService $planLimits,
    ) {}

    public function isConfigured(?Company $company = null): bool
    {
        return $this->resolveProvider(PlatformSetting::query()->first(), $company) !== null;
    }

    /**
     * @param  array{prefer_edge?: bool}  $options
     * @return array{binary: string, mime: string, provider: string, model?: string, voice?: string}
     */
    public function synthesize(string $text, ?string $locale = null, ?Company $company = null, array $options = []): array
    {
        // UI chrome / prefetch lines: use free Edge when available so paid TTS is not hit on every page load.
        if (($options['prefer_edge'] ?? false) && $this->edge->isConfigured() && ! $this->isProviderRateLimited('edge')) {
            try {
                return $this->edge->synthesize($text, $locale);
            } catch (RuntimeException $e) {
                Log::info('Nova TTS: Edge preferred for UI chrome failed, falling back.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $settings = PlatformSetting::query()->first();
        $preference = $settings?->tts_provider ?? 'cartesia';
        $chain = $this->providerChain($preference, $settings, $company);

        if ($chain === [] && in_array($preference, ['google', 'openai', 'cartesia'], true)) {
            throw new RuntimeException($this->premiumTtsUnavailableMessage($preference, $company));
        }

        $companyVoice = $this->companyCartesiaVoice($company);
        $lastError = null;
        $chainCount = count($chain);
        foreach ($chain as $index => $provider) {
            try {
                return match ($provider) {
                    'cartesia' => $this->cartesia->synthesize($text, $locale, [
                        'voice' => $companyVoice,
                    ]),
                    'edge' => $this->edge->synthesize($text, $locale),
                    'google' => $this->gemini->synthesize($text, $locale),
                    'openai' => $this->wrapOpenAi($this->openai->synthesize($text, $locale)),
                    default => throw new RuntimeException('Unknown TTS provider.'),
                };
            } catch (RuntimeException $e) {
                $lastError = $e;

                if ($e instanceof TtsRateLimitedException) {
                    $this->markProviderRateLimited($provider);
                }

                if ($index >= $chainCount - 1) {
                    throw $e;
                }

                Log::info('Nova TTS: provider unavailable, trying next.', [
                    'provider' => $provider,
                    'preference' => $preference,
                    'rate_limited' => $e instanceof TtsRateLimitedException,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        throw $lastError ?? new RuntimeException(__('Configure a voice provider in platform settings to enable Nova voice.'));
    }

    /**
     * @return array{provider: string|null, available: bool, server: bool, premium_tts: bool, edge: array<string, mixed>, gemini: array<string, mixed>, openai: array<string, mixed>, cartesia: array<string, mixed>}
     */
    public function publicConfig(?Company $company = null): array
    {
        $row = PlatformSetting::query()->first();
        $preference = $row?->tts_provider ?? 'cartesia';
        $provider = $this->resolveProvider($row, $company);
        $premiumTts = $company !== null && $this->planLimits->allowsPremiumTts($company);
        $companyVoice = $this->companyCartesiaVoice($company);

        return [
            'provider' => $preference === 'browser' ? 'browser' : $provider,
            'available' => $provider !== null || $preference === 'browser',
            'server' => $provider !== null,
            'premium_tts' => $premiumTts,
            'edge' => $this->edge->publicConfig(),
            'gemini' => array_merge($this->gemini->publicConfig(), [
                'eligible' => $premiumTts && $this->gemini->isConfigured(),
            ]),
            'openai' => array_merge($this->openai->publicConfig(), [
                'eligible' => $premiumTts && $this->openai->isConfigured(),
            ]),
            'cartesia' => array_merge($this->cartesia->publicConfig($companyVoice), [
                'eligible' => $this->cartesia->isConfigured(),
                'company_voice' => $companyVoice,
            ]),
        ];
    }

    /**
     * @return 'cartesia'|'edge'|'google'|'openai'|null
     */
    public function resolveProvider(?PlatformSetting $settings, ?Company $company = null): ?string
    {
        $preference = $settings?->tts_provider ?? 'cartesia';

        if ($preference === 'browser') {
            return null;
        }

        $chain = $this->providerChain($preference, $settings, $company);

        return $chain[0] ?? null;
    }

    /**
     * @return list<'cartesia'|'edge'|'google'|'openai'>
     */
    private function providerChain(string $preference, ?PlatformSetting $settings, ?Company $company = null): array
    {
        // Cartesia sits ahead of Edge as premium failover when Gemini/OpenAI hit quota.
        $chain = match ($preference) {
            'cartesia' => ['cartesia', 'edge'],
            'edge' => ['edge'],
            'google' => ['google', 'cartesia', 'edge'],
            'openai' => ['openai', 'cartesia', 'edge'],
            'browser' => [],
            default => ['cartesia', 'edge', 'google', 'openai'],
        };

        return array_values(array_filter(
            $chain,
            fn (string $provider): bool => $this->providerIsAvailable($provider, $settings, $company),
        ));
    }

    private function providerIsAvailable(string $provider, ?PlatformSetting $settings, ?Company $company = null): bool
    {
        if ($this->isProviderRateLimited($provider)) {
            return false;
        }

        return match ($provider) {
            'cartesia' => $this->cartesia->isConfigured(),
            'edge' => $this->edge->isConfigured(),
            'google' => $this->gemini->isConfigured() && $company !== null && $this->planLimits->allowsPremiumTts($company),
            'openai' => $this->openai->isConfigured() && $company !== null && $this->planLimits->allowsPremiumTts($company),
            default => false,
        };
    }

    private function markProviderRateLimited(string $provider): void
    {
        Cache::put(
            $this->rateLimitCacheKey($provider),
            true,
            now()->addSeconds(self::RATE_LIMIT_COOLDOWN_SECONDS),
        );
    }

    private function isProviderRateLimited(string $provider): bool
    {
        return Cache::get($this->rateLimitCacheKey($provider)) === true;
    }

    private function rateLimitCacheKey(string $provider): string
    {
        return 'nova_tts_rate_limited:'.$provider;
    }

    private function companyCartesiaVoice(?Company $company): ?string
    {
        if ($company === null) {
            return null;
        }

        $settings = CompanySetting::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $agent = is_array($settings?->ai_agent) ? $settings->ai_agent : [];
        $voice = trim((string) ($agent['cartesia_tts_voice'] ?? ''));

        return $this->cartesia->isVoiceId($voice) ? $voice : null;
    }

    private function premiumTtsUnavailableMessage(string $preference, ?Company $company): string
    {
        if ($preference === 'cartesia') {
            return __('Add a Cartesia API key in platform settings to use Cartesia TTS.');
        }

        if ($company === null) {
            return __('Premium Nova voice requires a workspace with an active paid subscription.');
        }

        if (! $this->planLimits->allowsPremiumTts($company)) {
            return __('Premium Nova voice (Gemini/OpenAI TTS) requires an active paid subscription. Use Cartesia, Automatic, or Microsoft Edge.');
        }

        return match ($preference) {
            'google' => __('Add a Google AI API key in platform settings to use Gemini TTS.'),
            'openai' => __('Add an OpenAI API key in platform settings to use OpenAI TTS.'),
            default => __('Premium Nova voice is not available for this workspace.'),
        };
    }

    /**
     * @return array{binary: string, mime: string, provider: string, model: string, voice: string}
     */
    private function wrapOpenAi(string $binary): array
    {
        $config = $this->openai->publicConfig();

        return [
            'binary' => $binary,
            'mime' => 'audio/mpeg',
            'provider' => 'openai',
            'model' => (string) ($config['model'] ?? ''),
            'voice' => (string) ($config['voice'] ?? ''),
        ];
    }
}
