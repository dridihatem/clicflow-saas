<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NovaGeminiLiveService
{
    private const AUTH_TOKENS_URL = 'https://generativelanguage.googleapis.com/v1alpha/auth_tokens';

    private const LIVE_WS_URL = 'wss://generativelanguage.googleapis.com/ws/google.ai.generativelanguage.v1alpha.GenerativeService.BidiGenerateContentConstrained';

    private const GENERATE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    private const DEFAULT_LIVE_MODEL = 'gemini-3.1-flash-live-preview';

    private const DEFAULT_VOICE = 'Kore';

    public function __construct(
        private NovaLiveToolsService $tools,
        private NovaIdentityService $identity,
    ) {}

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * @return array{
     *   mode: string,
     *   model: string,
     *   voice: string,
     *   ws_url: ?string,
     *   access_token: ?string,
     *   setup: array<string, mixed>,
     *   tools_url: string,
     *   turn_url: string,
     *   live_available: bool
     * }
     */
    public function startSession(User $user, Company $company): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException(__('Add a Google AI API key in platform settings to use Gemini Live Nova.'));
        }

        $model = $this->liveModel();
        $voice = $this->voice();
        $setup = $this->bidiSetup($user, $company, $model, $voice);
        $token = null;

        try {
            $token = $this->createEphemeralToken($apiKey, $setup);
        } catch (RuntimeException $e) {
            Log::info('Nova Live: ephemeral token unavailable, using turn-based STS.', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'mode' => $token ? 'live' : 'turn',
            'model' => $model,
            'voice' => $voice,
            'ws_url' => $token ? self::LIVE_WS_URL.'?access_token='.rawurlencode($token) : null,
            'access_token' => $token,
            'setup' => $setup,
            'tools_url' => route('assistant.live.tools'),
            'turn_url' => route('assistant.live.turn'),
            'live_available' => $token !== null,
        ];
    }

    /**
     * Public marketing demo: Gemini Live / turn without workspace tools.
     *
     * @return array{
     *   mode: string,
     *   model: string,
     *   voice: string,
     *   ws_url: ?string,
     *   access_token: ?string,
     *   setup: array<string, mixed>,
     *   live_available: bool
     * }
     */
    public function startPublicDemoSession(string $locale): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException(__('nova_demo_live_unavailable'));
        }

        $model = $this->liveModel();
        $voice = $this->voice();
        $setup = $this->publicBidiSetup($locale, $model, $voice);
        $token = null;

        try {
            $token = $this->createEphemeralToken($apiKey, $setup);
        } catch (RuntimeException $e) {
            Log::info('Nova public demo Live: ephemeral token unavailable, using turn mode.', [
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'mode' => $token ? 'live' : 'turn',
            'model' => $model,
            'voice' => $voice,
            'ws_url' => $token ? self::LIVE_WS_URL.'?access_token='.rawurlencode($token) : null,
            'access_token' => $token,
            'setup' => $setup,
            'live_available' => $token !== null,
        ];
    }

    /**
     * @return array{
     *   mime: string,
     *   audio_base64: ?string,
     *   text: ?string,
     *   tool_calls: list<array{id: string, name: string, args: array<string, mixed>}>,
     *   model: string
     * }
     */
    public function processPublicTurn(?string $audioBase64 = null, ?string $text = null, ?string $locale = null): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException(__('nova_demo_live_unavailable'));
        }

        $locale = $locale ?: app()->getLocale();
        $model = $this->liveModel();
        $parts = [];

        if (is_string($audioBase64) && $audioBase64 !== '') {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => 'audio/pcm;rate=16000',
                    'data' => $audioBase64,
                ],
            ];
            if (is_string($text) && trim($text) !== '') {
                $parts[] = ['text' => trim($text)];
            }
        } elseif (is_string($text) && trim($text) !== '') {
            $parts[] = ['text' => trim($text)];
        } else {
            throw new RuntimeException(__('No speech input received.'));
        }

        $body = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->publicSystemInstruction($locale)],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $this->voice(),
                        ],
                    ],
                ],
            ],
        ];

        /** @var Response $response */
        $response = Http::timeout(90)
            ->connectTimeout(15)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'content-type' => 'application/json',
            ])
            ->post(sprintf(self::GENERATE_URL, rawurlencode($model)), $body);

        if (! $response->successful()) {
            Log::warning('Nova public demo turn failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException($this->httpErrorMessage($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(__('Invalid response from Gemini Live.'));
        }

        return $this->parseGenerateResponse($data, $model);
    }

    /**
     * One spoken turn: mic PCM in → Gemini audio (+ optional tool calls) out.
     *
     * @param  list<array{name: string, args: array<string, mixed>}>|null  $pendingToolResults
     * @return array{
     *   mime: string,
     *   audio_base64: ?string,
     *   text: ?string,
     *   tool_calls: list<array{id: string, name: string, args: array<string, mixed>}>,
     *   model: string
     * }
     */
    public function processTurn(
        User $user,
        Company $company,
        ?string $audioBase64 = null,
        ?string $text = null,
        ?array $pendingToolResults = null,
    ): array {
        $apiKey = $this->apiKey();
        if ($apiKey === null) {
            throw new RuntimeException(__('Add a Google AI API key in platform settings to use Gemini Live Nova.'));
        }

        $model = $this->liveModel();
        $parts = [];

        if (is_array($pendingToolResults) && $pendingToolResults !== []) {
            foreach ($pendingToolResults as $result) {
                $parts[] = [
                    'functionResponse' => [
                        'name' => (string) ($result['name'] ?? ''),
                        'response' => is_array($result['response'] ?? null) ? $result['response'] : ['result' => $result['response'] ?? null],
                    ],
                ];
            }
        } elseif (is_string($audioBase64) && $audioBase64 !== '') {
            $parts[] = [
                'inlineData' => [
                    'mimeType' => 'audio/pcm;rate=16000',
                    'data' => $audioBase64,
                ],
            ];
            if (is_string($text) && trim($text) !== '') {
                $parts[] = ['text' => trim($text)];
            }
        } elseif (is_string($text) && trim($text) !== '') {
            $parts[] = ['text' => trim($text)];
        } else {
            throw new RuntimeException(__('No speech input received.'));
        }

        $body = [
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->systemInstruction($user, $company)],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'tools' => [
                ['functionDeclarations' => $this->tools->functionDeclarations($user)],
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $this->voice(),
                        ],
                    ],
                ],
            ],
        ];

        /** @var Response $response */
        $response = Http::timeout(90)
            ->connectTimeout(15)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'content-type' => 'application/json',
            ])
            ->post(sprintf(self::GENERATE_URL, rawurlencode($model)), $body);

        if (! $response->successful()) {
            Log::warning('Nova Live turn failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException($this->httpErrorMessage($response));
        }

        $data = $response->json();
        if (! is_array($data)) {
            throw new RuntimeException(__('Invalid response from Gemini Live.'));
        }

        return $this->parseGenerateResponse($data, $model);
    }

    /**
     * @param  array<string, mixed>  $setup
     */
    private function createEphemeralToken(string $apiKey, array $setup): string
    {
        $expire = now('UTC')->addMinutes(30)->toIso8601String();
        $newSessionExpire = now('UTC')->addMinutes(2)->toIso8601String();

        /** @var Response $response */
        $response = Http::timeout(20)
            ->withHeaders([
                'x-goog-api-key' => $apiKey,
                'content-type' => 'application/json',
            ])
            ->post(self::AUTH_TOKENS_URL, [
                'uses' => 1,
                'expireTime' => $expire,
                'newSessionExpireTime' => $newSessionExpire,
                'bidiGenerateContentSetup' => $setup,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Ephemeral token request failed: HTTP '.$response->status());
        }

        $name = $response->json('name');
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Ephemeral token missing name.');
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function bidiSetup(User $user, Company $company, string $model, string $voice): array
    {
        return [
            'model' => 'models/'.$model,
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice,
                        ],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->systemInstruction($user, $company)],
                ],
            ],
            'tools' => [
                ['functionDeclarations' => $this->tools->functionDeclarations($user)],
            ],
            'inputAudioTranscription' => new \stdClass,
            'outputAudioTranscription' => new \stdClass,
        ];
    }

    private function systemInstruction(User $user, Company $company): string
    {
        $brand = (string) config('flowdesk.ai_assistant_brand_name', 'Nova');
        $product = (string) config('flowdesk.brand_name', 'ClicFlow');
        $name = trim((string) $user->name) ?: 'there';
        $companyName = trim((string) $company->name) ?: 'this workspace';
        $locale = app()->getLocale();
        $identity = $this->identity->reply($company, $user);
        $toolHints = $this->tools->systemHints($user);

        return <<<TXT
You are {$brand}, the realtime voice co-pilot for {$companyName} on {$product}.
Speak naturally and fluently. Detect and reply in the user's spoken language (French, English, Spanish, Arabic, Hindi, or Indonesian). Current UI locale: {$locale}.
User first name: {$name}.
Identity: {$identity}

You can manage the system with tools. Prefer tools for actions (navigate, create client, change VAT, analyze clients, etc.). After a tool runs, briefly confirm what you did in spoken language.
Keep replies concise for voice (1–3 short sentences) unless the user asks for detail.
{$toolHints}
TXT;
    }

    /**
     * @return array<string, mixed>
     */
    private function publicBidiSetup(string $locale, string $model, string $voice): array
    {
        return [
            'model' => 'models/'.$model,
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice,
                        ],
                    ],
                ],
            ],
            'systemInstruction' => [
                'parts' => [
                    ['text' => $this->publicSystemInstruction($locale)],
                ],
            ],
            'inputAudioTranscription' => new \stdClass,
            'outputAudioTranscription' => new \stdClass,
        ];
    }

    private function publicSystemInstruction(string $locale): string
    {
        $brand = (string) config('flowdesk.ai_assistant_brand_name', 'Nova');
        $product = (string) config('flowdesk.brand_name', 'ClicFlow');

        return <<<TXT
You are {$brand}, the public marketing voice demo for {$product}.
On start, warmly introduce yourself as {$brand} for {$product}: a multi-tenant SaaS where teams manage clients, projects, invoices, and quotes by voice.
Answer questions about the product, voice AI, features, and getting started. You cannot access workspaces or run business tools.
Speak naturally in short turns (1–4 sentences). Detect and reply in the user's spoken language. Preferred UI locale: {$locale}.
Invite visitors to register for the full product when relevant.
TXT;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   mime: string,
     *   audio_base64: ?string,
     *   text: ?string,
     *   tool_calls: list<array{id: string, name: string, args: array<string, mixed>}>,
     *   model: string
     * }
     */
    private function parseGenerateResponse(array $data, string $model): array
    {
        $candidates = $data['candidates'] ?? [];
        $parts = is_array($candidates[0]['content']['parts'] ?? null)
            ? $candidates[0]['content']['parts']
            : [];

        $audioBase64 = null;
        $mime = 'audio/pcm';
        $text = null;
        $toolCalls = [];

        foreach ($parts as $index => $part) {
            if (! is_array($part)) {
                continue;
            }

            if (isset($part['functionCall']) && is_array($part['functionCall'])) {
                $fc = $part['functionCall'];
                $args = $fc['args'] ?? [];
                $toolCalls[] = [
                    'id' => 'call_'.$index,
                    'name' => (string) ($fc['name'] ?? ''),
                    'args' => is_array($args) ? $args : [],
                ];
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $text = trim(($text ? $text.' ' : '').$part['text']);
            }

            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (is_array($inline) && is_string($inline['data'] ?? null) && $inline['data'] !== '') {
                $audioBase64 = $inline['data'];
                $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'audio/pcm');
            }
        }

        return [
            'mime' => $mime,
            'audio_base64' => $audioBase64,
            'text' => $text !== '' ? $text : null,
            'tool_calls' => $toolCalls,
            'model' => $model,
        ];
    }

    private function apiKey(): ?string
    {
        $row = PlatformSetting::query()->first();

        return flowdesk_decrypt_model_attribute($row, 'google_api_key_encrypted');
    }

    private function liveModel(): string
    {
        $configured = trim((string) config('flowdesk.nova_gemini_live_model', self::DEFAULT_LIVE_MODEL));

        return $configured !== '' ? $configured : self::DEFAULT_LIVE_MODEL;
    }

    private function voice(): string
    {
        $row = PlatformSetting::query()->first();
        $voice = trim((string) ($row?->gemini_tts_voice ?? self::DEFAULT_VOICE));

        return $voice !== '' ? $voice : self::DEFAULT_VOICE;
    }

    private function httpErrorMessage(Response $response): string
    {
        $json = $response->json();
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';

        return match ($response->status()) {
            401, 403 => __('Google (Gemini) API rejected the key. Check platform settings.'),
            429 => __('Gemini Live quota reached. Try again shortly.'),
            default => __('Gemini Live request failed').($msg !== '' ? ": {$msg}" : '.'),
        };
    }
}
