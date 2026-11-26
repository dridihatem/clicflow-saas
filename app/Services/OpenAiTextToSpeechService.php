<?php

namespace App\Services;

use App\Exceptions\TtsRateLimitedException;
use App\Models\PlatformSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class OpenAiTextToSpeechService
{
    private const API_URL = 'https://api.openai.com/v1/audio/speech';

    private const DEFAULT_MODEL = 'tts-1-hd';

    private const DEFAULT_VOICE = 'nova';

    /** @var list<string> */
    public const VOICES = ['alloy', 'ash', 'ballad', 'coral', 'echo', 'fable', 'nova', 'onyx', 'sage', 'shimmer', 'verse'];

    public function isConfigured(): bool
    {
        $row = PlatformSetting::query()->first();

        return flowdesk_decrypt_model_attribute($row, 'openai_api_key_encrypted') !== null;
    }

    public function synthesize(string $text, ?string $locale = null): string
    {
        $input = flowdesk_sanitize_speech_text($text);
        if ($input === '') {
            throw new RuntimeException('Speech text is empty.');
        }

        $row = PlatformSetting::query()->first();
        $apiKey = flowdesk_decrypt_model_attribute($row, 'openai_api_key_encrypted');
        if ($apiKey === null) {
            throw new RuntimeException('OpenAI API key is not configured.');
        }

        $model = $row->openai_tts_model ?: self::DEFAULT_MODEL;
        $voice = $this->normalizeVoice($row->openai_tts_voice);

        try {
            /** @var Response $response */
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withToken($apiKey)
                ->withHeaders(['Accept' => 'audio/mpeg'])
                ->post(self::API_URL, [
                    'model' => $model,
                    'voice' => $voice,
                    'input' => mb_substr($input, 0, 4096),
                    'response_format' => 'mp3',
                    'speed' => 1.0,
                ]);
        } catch (ConnectionException $e) {
            Log::warning('OpenAI TTS connection error', ['message' => $e->getMessage()]);

            throw new RuntimeException(__('OpenAI TTS is temporarily unavailable. Try again shortly.'));
        }

        if (! $response->successful()) {
            Log::warning('OpenAI TTS error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $message = $this->errorMessage($response);
            if ($response->status() === 429) {
                throw new TtsRateLimitedException($message);
            }

            throw new RuntimeException($message);
        }

        $binary = $response->body();
        if ($binary === '') {
            throw new RuntimeException('Empty audio from OpenAI TTS.');
        }

        return $binary;
    }

    public function publicConfig(): array
    {
        $row = PlatformSetting::query()->first();

        return [
            'available' => $this->isConfigured(),
            'voice' => $this->normalizeVoice($row?->openai_tts_voice),
            'model' => $row?->openai_tts_model ?: self::DEFAULT_MODEL,
        ];
    }

    private function normalizeVoice(?string $voice): string
    {
        $voice = strtolower(trim((string) $voice));

        return in_array($voice, self::VOICES, true) ? $voice : self::DEFAULT_VOICE;
    }

    private function errorMessage(Response $response): string
    {
        $json = $response->json();
        $msg = is_array($json) ? (string) ($json['error']['message'] ?? '') : '';

        return match ($response->status()) {
            401 => __('OpenAI TTS rejected the API key. Check platform settings.'),
            429 => __('OpenAI TTS rate limit reached. Cartesia or Microsoft Edge voice is used automatically when available.'),
            default => __('OpenAI TTS request failed').($msg !== '' ? ": {$msg}" : '.'),
        };
    }
}
