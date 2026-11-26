<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class NovaPublicDemoService
{
    private const MAX_HISTORY = 8;

    private const MAX_REPLY_CHARS = 1200;

    public function __construct(
        private PlatformLlmRouter $llm,
        private NovaTextToSpeechService $tts,
        private NovaGeminiLiveService $live,
    ) {}

    public function isChatAvailable(): bool
    {
        return $this->llm->isAvailable(null);
    }

    public function isSpeakAvailable(): bool
    {
        return $this->tts->isConfigured(null);
    }

    public function isLiveAvailable(): bool
    {
        return $this->live->isConfigured();
    }

    /**
     * @return array{reply: string, conversation_id: string, model: string}
     */
    public function chat(string $message, ?string $conversationId = null): array
    {
        if (! $this->isChatAvailable()) {
            throw new RuntimeException(__('nova_demo_unavailable'));
        }

        $message = trim($message);
        if ($message === '') {
            throw new RuntimeException(__('nova_demo_empty_message'));
        }

        $conversationId = is_string($conversationId) && preg_match('/^[A-Za-z0-9_-]{10,40}$/', $conversationId)
            ? $conversationId
            : (string) Str::ulid();

        $history = $this->history($conversationId);
        $history[] = ['role' => 'user', 'content' => mb_substr($message, 0, 1000)];

        $transcript = collect($history)
            ->map(fn (array $row) => strtoupper($row['role']).': '.$row['content'])
            ->implode("\n");

        $result = $this->llm->complete(
            $this->systemPrompt(),
            $transcript."\n\nRespond as Nova to the latest user message only.",
            700,
            null,
        );

        $reply = trim((string) ($result['suggestion'] ?? ''));
        if ($reply === '') {
            throw new RuntimeException(__('nova_demo_empty_reply'));
        }

        $reply = mb_substr($reply, 0, self::MAX_REPLY_CHARS);
        $history[] = ['role' => 'assistant', 'content' => $reply];
        $this->storeHistory($conversationId, $history);

        return [
            'reply' => $reply,
            'conversation_id' => $conversationId,
            'model' => (string) ($result['model'] ?? 'gemini'),
        ];
    }

    /**
     * @return array{binary: string, mime: string, provider: string}
     */
    public function speak(string $text): array
    {
        if (! $this->isSpeakAvailable()) {
            throw new RuntimeException(__('nova_demo_speak_unavailable'));
        }

        $text = trim(mb_substr($text, 0, 2000));
        if ($text === '') {
            throw new RuntimeException(__('nova_demo_empty_message'));
        }

        return $this->tts->synthesize($text, app()->getLocale(), null, ['purpose' => 'demo']);
    }

    /**
     * @return array<string, mixed>
     */
    public function startLiveSession(): array
    {
        if (! $this->isLiveAvailable()) {
            throw new RuntimeException(__('nova_demo_live_unavailable'));
        }

        $session = $this->live->startPublicDemoSession(app()->getLocale());
        $session['chat_url'] = route('demo.nova.chat');
        $session['speak_url'] = route('demo.nova.speak');
        $session['turn_url'] = route('demo.nova.live.turn');
        $session['duration_seconds'] = 180;

        return $session;
    }

    /**
     * @return array{
     *   mime: string,
     *   audio_base64: ?string,
     *   text: ?string,
     *   tool_calls: list<array<string, mixed>>,
     *   model: string
     * }
     */
    public function processLiveTurn(?string $audioBase64 = null, ?string $text = null): array
    {
        if (! $this->isLiveAvailable()) {
            throw new RuntimeException(__('nova_demo_live_unavailable'));
        }

        return $this->live->processPublicTurn($audioBase64, $text, app()->getLocale());
    }

    public function statusPayload(): array
    {
        return [
            'chat_available' => $this->isChatAvailable(),
            'speak_available' => $this->isSpeakAvailable(),
            'live_available' => $this->isLiveAvailable(),
            'locale' => app()->getLocale(),
            'locales' => array_values(config('flowdesk.locales', ['fr', 'en', 'es', 'ar'])),
        ];
    }

    private function systemPrompt(): string
    {
        $brand = (string) config('flowdesk.ai_assistant_brand_name', 'Nova');
        $product = (string) config('flowdesk.brand_name', 'ClicFlow');
        $locale = app()->getLocale();

        return <<<TXT
You are {$brand}, the voice AI agent for {$product} (multi-tenant SaaS for agencies and teams).
You are on the public marketing demo — present {$brand} and {$product}: how voice helps open clients, draft invoices/quotes, navigate projects, and scale teams without hiring call centers.
Do NOT claim to access a real workspace, create invoices, or run tools. Invite visitors to register for the full product.
Keep replies concise (2–5 short sentences). Reply in the UI locale: {$locale} (en/fr/es/ar/hi/id). Match the user's language if they write in another supported language.
Be warm, professional, and clear. No markdown tables. No inventing private customer data.
TXT;
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function history(string $conversationId): array
    {
        $rows = Cache::get($this->cacheKey($conversationId), []);

        return is_array($rows) ? array_values(array_filter($rows, fn ($r) => is_array($r) && isset($r['role'], $r['content']))) : [];
    }

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    private function storeHistory(string $conversationId, array $history): void
    {
        $trimmed = array_slice($history, -self::MAX_HISTORY);
        Cache::put($this->cacheKey($conversationId), $trimmed, now()->addMinutes(45));
    }

    private function cacheKey(string $conversationId): string
    {
        return 'nova_public_demo:'.$conversationId;
    }
}
