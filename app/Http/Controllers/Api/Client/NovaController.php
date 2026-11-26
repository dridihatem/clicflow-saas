<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\ResolvesPortalClient;
use App\Models\WorkspaceCalendarEvent;
use App\Services\NovaClientVoiceWorkflowService;
use App\Services\PlatformLlmRouter;
use App\Services\WorkspaceCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

class NovaController extends Controller
{
    use ResolvesPortalClient;

    private const MAX_HISTORY = 20;

    private const CACHE_TTL = 60 * 60 * 24 * 7;

    public function chat(
        Request $request,
        PlatformLlmRouter $llm,
        NovaClientVoiceWorkflowService $workflows,
    ): JsonResponse {
        $client = $this->portalClient($request);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string', 'max:40'],
        ]);

        $conversationId = is_string($data['conversation_id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{10,40}$/', $data['conversation_id'])
            ? $data['conversation_id']
            : (string) Str::ulid();

        $message = trim($data['message']);
        $voice = $workflows->handle($request->user(), $client, $company, $message);

        if ($voice['handled']) {
            $this->appendHistory($request->user()->id, $conversationId, $message, $voice['reply']);

            return response()->json([
                'reply' => $voice['reply'],
                'conversation_id' => $conversationId,
                'model' => 'nova-voice-workflow',
                'workflow' => $voice['workflow'] ?? null,
                'step' => $voice['step'] ?? null,
                'done' => (bool) ($voice['done'] ?? false),
                'awaiting_input' => ! ($voice['done'] ?? false),
                'result' => $voice['result'] ?? null,
                'speak' => true,
            ]);
        }

        if (! $llm->isAvailable($company)) {
            return response()->json(['message' => __('nova_demo_unavailable')], 503);
        }

        $key = $this->historyKey($request->user()->id, $conversationId);
        $history = Cache::get($key, []);
        if (! is_array($history)) {
            $history = [];
        }

        $history[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000), 'at' => now()->toIso8601String()];

        $context = $this->clientContext($client);
        $transcript = collect($history)
            ->map(fn (array $row) => strtoupper($row['role']).': '.$row['content'])
            ->implode("\n");

        try {
            $result = $llm->complete(
                $this->systemPrompt($company->name ?? 'ClicFlow', $client->name, $context),
                $transcript."\n\nRespond as Nova to the latest user message only.",
                900,
                $company,
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $reply = trim((string) ($result['suggestion'] ?? ''));
        if ($reply === '') {
            return response()->json(['message' => __('nova_demo_empty_reply')], 503);
        }

        $reply = mb_substr($reply, 0, 2500);
        $history[] = ['role' => 'assistant', 'content' => $reply, 'at' => now()->toIso8601String()];
        $history = array_slice($history, -self::MAX_HISTORY);
        Cache::put($key, $history, self::CACHE_TTL);
        $this->touchIndex($request->user()->id, $conversationId, $message);

        return response()->json([
            'reply' => $reply,
            'conversation_id' => $conversationId,
            'model' => (string) ($result['model'] ?? 'llm'),
            'workflow' => null,
            'step' => null,
            'done' => true,
            'awaiting_input' => false,
            'speak' => true,
        ]);
    }

    public function calendar(Request $request, WorkspaceCalendarService $calendar): JsonResponse
    {
        $client = $this->portalClient($request);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $monthParam = $request->query('month');
        $anchor = is_string($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam)
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $from = $anchor->copy()->subMonth()->startOfMonth();
        $to = $anchor->copy()->addMonth()->endOfMonth();

        $events = $calendar->eventsForCompany($company, $from, $to, (string) $client->id, true);

        usort($events, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        return response()->json([
            'month' => $anchor->format('Y-m'),
            'events' => array_values($events),
        ]);
    }

    public function storeEvent(Request $request): JsonResponse
    {
        $client = $this->portalClient($request);
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'string', 'max:16'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['message'] ?? '',
            'starts_on' => $data['date'],
            'start_time' => $data['time'] ?? null,
            'ends_on' => $data['date'],
            'kind' => 'meeting',
            'source_type' => 'mobile_app',
        ]);

        return response()->json([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->starts_on->toDateString(),
                'time' => $event->start_time,
                'description' => $event->description,
            ],
        ], 201);
    }

    public function history(Request $request): JsonResponse
    {
        $this->portalClient($request);
        $index = Cache::get($this->indexKey($request->user()->id), []);
        if (! is_array($index)) {
            $index = [];
        }

        return response()->json([
            'conversations' => array_values($index),
        ]);
    }

    public function conversation(Request $request, string $conversationId): JsonResponse
    {
        $this->portalClient($request);
        abort_unless(preg_match('/^[A-Za-z0-9_-]{10,40}$/', $conversationId) === 1, 404);

        $history = Cache::get($this->historyKey($request->user()->id, $conversationId), []);
        if (! is_array($history)) {
            $history = [];
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'messages' => $history,
        ]);
    }

    private function appendHistory(string $userId, string $conversationId, string $message, string $reply): void
    {
        $key = $this->historyKey($userId, $conversationId);
        $history = Cache::get($key, []);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000), 'at' => now()->toIso8601String()];
        $history[] = ['role' => 'assistant', 'content' => mb_substr($reply, 0, 2500), 'at' => now()->toIso8601String()];
        Cache::put($key, array_slice($history, -self::MAX_HISTORY), self::CACHE_TTL);
        $this->touchIndex($userId, $conversationId, $message);
    }

    private function touchIndex(string $userId, string $conversationId, string $message): void
    {
        $indexKey = $this->indexKey($userId);
        $index = Cache::get($indexKey, []);
        if (! is_array($index)) {
            $index = [];
        }
        $index[$conversationId] = [
            'id' => $conversationId,
            'title' => mb_substr($message, 0, 80),
            'updated_at' => now()->toIso8601String(),
        ];
        uasort($index, fn ($a, $b) => strcmp($b['updated_at'] ?? '', $a['updated_at'] ?? ''));
        Cache::put($indexKey, array_slice($index, 0, 50, true), self::CACHE_TTL);
    }

    private function historyKey(string $userId, string $conversationId): string
    {
        return 'client_nova_hist:'.$userId.':'.$conversationId;
    }

    private function indexKey(string $userId): string
    {
        return 'client_nova_index:'.$userId;
    }

    private function systemPrompt(string $companyName, string $clientName, string $context): string
    {
        $brand = config('flowdesk.ai_assistant_brand_name', 'Nova');

        return "You are {$brand}, the AI assistant for {$companyName}, helping the client {$clientName} via the ClicFlow mobile app. "
            ."Be concise, friendly, and practical. Use the client context below when answering about their work.\n"
            ."If they want to create something, suggest saying: create invoice, create event, or create quote.\n\n"
            ."CLIENT CONTEXT:\n{$context}";
    }

    private function clientContext(\App\Models\Client $client): string
    {
        $projects = $client->projects()->latest()->limit(8)->get(['id', 'title', 'status', 'final_price', 'negotiated_price']);
        $invoices = $client->invoices()->latest()->limit(8)->get(['id', 'number', 'status', 'amount', 'currency']);

        $lines = [];
        $lines[] = 'Projects:';
        foreach ($projects as $p) {
            $price = $p->final_price ?? $p->negotiated_price ?? 'n/a';
            $lines[] = "- {$p->title} [{$p->status}] price={$price}";
        }
        if ($projects->isEmpty()) {
            $lines[] = '- none';
        }
        $lines[] = 'Invoices:';
        foreach ($invoices as $inv) {
            $lines[] = '- '.($inv->number ?? $inv->id)." [{$inv->status}] {$inv->amount} {$inv->currency}";
        }
        if ($invoices->isEmpty()) {
            $lines[] = '- none';
        }

        return implode("\n", $lines);
    }
}
