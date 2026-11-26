<?php

namespace App\Http\Controllers\Api\Company;

use App\Http\Controllers\Controller;
use App\Enums\CalendarMeetingLinkType;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\WorkspaceCalendarEvent;
use App\Services\AiCreditUsageService;
use App\Services\CalendarMeetingLinkService;
use App\Services\GoogleCalendarSyncService;
use App\Services\NovaTextToSpeechService;
use App\Services\NovaVoiceNavigationService;
use App\Services\NovaVoiceWorkflowService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\WorkspaceCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class NovaController extends Controller
{
    private const MAX_HISTORY = 20;

    private const CACHE_TTL = 60 * 60 * 24 * 7;

    public function chat(
        Request $request,
        PlatformLlmRouter $llm,
        NovaVoiceWorkflowService $workflows,
        PlanLimitService $limits,
    ): JsonResponse {
        $user = $request->user();
        $company = $user->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string', 'max:40'],
        ]);

        $conversationId = is_string($data['conversation_id'] ?? null) && preg_match('/^[A-Za-z0-9_-]{10,40}$/', $data['conversation_id'])
            ? $data['conversation_id']
            : (string) Str::ulid();

        $message = trim($data['message']);

        if ($user->locale) {
            app()->setLocale($user->locale);
        }

        // Persist create-client (and other) voice workflows — same as web Nova.
        try {
            if ($workflows->hasActiveSession($user)) {
                $voice = $workflows->advance($user, $message);
                $this->appendHistory($user->id, $conversationId, $message, (string) ($voice['reply'] ?? ''));

                return response()->json([
                    'reply' => (string) ($voice['reply'] ?? ''),
                    'conversation_id' => $conversationId,
                    'model' => 'nova-voice-workflow',
                    'workflow' => $voice['workflow'] ?? null,
                    'step' => $voice['step'] ?? null,
                    'done' => (bool) ($voice['done'] ?? false),
                    'awaiting_input' => ! ($voice['done'] ?? false),
                    'redirect_url' => $voice['redirect_url'] ?? null,
                    'result' => $voice['result'] ?? null,
                    'speak' => true,
                ]);
            }

            $gates = $limits->featureGates($company);
            $workflowId = $workflows->matchWorkflowId($user, $message, $gates);
            if ($workflowId !== null) {
                $voice = $workflows->start($user, $workflowId);

                // If the same utterance already includes details (name/email…), slot-fill now.
                // Quote/invoice workflows also accept "create quote Acme" in one message.
                if (! ($voice['done'] ?? false)
                    && $workflows->hasActiveSession($user)
                    && (
                        $this->openingMessageHasClientDetails($message)
                        || in_array($workflowId, ['create_quote', 'create_invoice', 'create_client'], true)
                    )) {
                    $voice = $workflows->advance($user, $message);
                }

                $this->appendHistory($user->id, $conversationId, $message, (string) ($voice['reply'] ?? ''));

                return response()->json([
                    'reply' => (string) ($voice['reply'] ?? ''),
                    'conversation_id' => $conversationId,
                    'model' => 'nova-voice-workflow',
                    'workflow' => $voice['workflow'] ?? null,
                    'step' => $voice['step'] ?? null,
                    'done' => (bool) ($voice['done'] ?? false),
                    'awaiting_input' => ! ($voice['done'] ?? false),
                    'redirect_url' => $voice['redirect_url'] ?? null,
                    'result' => $voice['result'] ?? null,
                    'speak' => true,
                ]);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        }

        if (! $llm->isAvailable($company)) {
            return response()->json(['message' => __('nova_demo_unavailable')], 503);
        }

        $key = $this->historyKey($user->id, $conversationId);
        $history = Cache::get($key, []);
        if (! is_array($history)) {
            $history = [];
        }

        $history[] = ['role' => 'user', 'content' => mb_substr($message, 0, 2000), 'at' => now()->toIso8601String()];

        $context = $this->companyContext($company->id);
        $transcript = collect($history)
            ->map(fn (array $row) => strtoupper($row['role']).': '.$row['content'])
            ->implode("\n");

        try {
            $result = $llm->complete(
                $this->systemPrompt($company->name ?? 'ClicFlow', $user->name, $context),
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
        $this->touchIndex($user->id, $conversationId, $message);

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

    private function appendHistory(string $userId, string $conversationId, string $userMessage, string $reply): void
    {
        $key = $this->historyKey($userId, $conversationId);
        $history = Cache::get($key, []);
        if (! is_array($history)) {
            $history = [];
        }
        $history[] = ['role' => 'user', 'content' => mb_substr($userMessage, 0, 2000), 'at' => now()->toIso8601String()];
        $history[] = ['role' => 'assistant', 'content' => mb_substr($reply, 0, 2500), 'at' => now()->toIso8601String()];
        Cache::put($key, array_slice($history, -self::MAX_HISTORY), self::CACHE_TTL);
        $this->touchIndex($userId, $conversationId, $userMessage);
    }

    public function feedback(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'conversation_id' => ['nullable', 'string', 'max:40'],
            'message' => ['required', 'string', 'max:4000'],
            'rating' => ['required', 'string', 'in:like,unlike'],
        ]);

        $key = 'company_nova_feedback:'.$company->id.':'.$user->id;
        $rows = Cache::get($key, []);
        if (! is_array($rows)) {
            $rows = [];
        }
        $rows[] = [
            'conversation_id' => $data['conversation_id'] ?? null,
            'rating' => $data['rating'],
            'message' => mb_substr($data['message'], 0, 500),
            'at' => now()->toIso8601String(),
        ];
        Cache::put($key, array_slice($rows, -100), self::CACHE_TTL);

        return response()->json(['ok' => true]);
    }

    /**
     * Synthesize speech using the company/platform TTS chain (Gemini, Cartesia, Edge, OpenAI).
     */
    public function speak(
        Request $request,
        PlanLimitService $planLimits,
        AiCreditUsageService $usage,
        NovaTextToSpeechService $tts,
        NovaVoiceNavigationService $voiceNav,
    ): Response {
        $user = $request->user();
        $company = $user?->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
            'purpose' => ['sometimes', 'string', 'in:ui,content'],
        ]);

        if ($user->locale) {
            app()->setLocale($user->locale);
        }

        $purpose = (string) ($data['purpose'] ?? 'content');
        $billable = ! ($purpose === 'ui' && $voiceNav->isNonBillableSpeech($user, $data['text']));
        $creditCost = $billable
            ? $usage->creditsForTask(AiCreditUsageService::TASK_ASSISTANT, 'nova_voice')
            : 0;

        if (! $planLimits->isFeatureEnabled($company, 'ai_credits')) {
            return response()->json([
                'message' => __('AI credits are not available on your plan.'),
                'fallback' => 'browser',
                'code' => 'plan_disabled',
            ], 403);
        }

        if ($billable && ! $planLimits->allows($company, 'ai_credits', $creditCost)) {
            $nav = $planLimits->aiCreditsForNav($company);
            $remaining = ! empty($nav['unlimited']) ? null : (int) ($nav['remaining'] ?? 0);

            return response()->json([
                'message' => __('ai_credits_insufficient', [
                    'required' => max(1, $creditCost),
                    'remaining' => $remaining ?? __('Unlimited'),
                ]),
                'fallback' => 'browser',
                'code' => 'ai_credits_limit',
            ], 403);
        }

        if (! $tts->isConfigured($company)) {
            return response()->json([
                'message' => __('Configure a voice provider in platform settings to enable Nova voice.'),
                'fallback' => 'browser',
                'code' => 'tts_unconfigured',
            ], 503);
        }

        try {
            $result = $tts->synthesize($data['text'], app()->getLocale(), $company);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'fallback' => 'browser',
                'code' => 'tts_failed',
            ], 503);
        }

        $charged = 0;
        if ($billable) {
            $charged = $usage->recordForTask($company, AiCreditUsageService::TASK_ASSISTANT, 'nova_voice');
        }

        $nav = $planLimits->aiCreditsForNav($company);
        $remaining = (! empty($nav['unlimited']) || ($nav['remaining'] ?? null) === null)
            ? null
            : max(0, (int) $nav['remaining']);

        return response($result['binary'], 200, array_filter([
            'Content-Type' => $result['mime'],
            'Cache-Control' => $billable ? 'no-store, private' : 'private, max-age=3600',
            'X-AI-Credits-Charged' => (string) $charged,
            'X-AI-Credits-Cost' => (string) $creditCost,
            'X-AI-Credits-Remaining' => $remaining === null ? null : (string) $remaining,
            'X-Nova-TTS-Provider' => $result['provider'],
            'Access-Control-Expose-Headers' => 'X-AI-Credits-Charged, X-AI-Credits-Cost, X-AI-Credits-Remaining, X-Nova-TTS-Provider',
        ], fn ($value) => $value !== null));
    }

    public function voiceCommands(
        Request $request,
        NovaVoiceNavigationService $voiceNav,
        PlanLimitService $limits,
    ): JsonResponse {
        $user = $request->user();
        $company = $user->company;
        abort_if(! $company, 403);

        $locale = $user->locale ?? app()->getLocale();
        if (is_string($locale) && $locale !== '') {
            app()->setLocale($locale);
        }

        $gates = $limits->featureGates($company);
        $config = $voiceNav->clientConfig($user, $gates);

        $commands = [];
        foreach ($config['commands'] ?? [] as $command) {
            if (! is_array($command)) {
                continue;
            }
            $phrases = array_values(array_filter(array_map(
                fn ($p) => is_string($p) ? trim($p) : '',
                $command['phrases'] ?? [],
            )));
            if ($phrases === []) {
                continue;
            }
            $kind = 'navigate';
            if (! empty($command['workflow'])) {
                $kind = 'workflow';
            } elseif (! empty($command['action'])) {
                $kind = 'action';
            }
            $commands[] = [
                'id' => (string) ($command['id'] ?? ''),
                'label' => (string) ($command['label'] ?? ''),
                'kind' => $kind,
                'phrases' => $phrases,
            ];
        }

        $workflows = [];
        foreach ($config['workflows'] ?? [] as $workflow) {
            if (! is_array($workflow)) {
                continue;
            }
            $phrases = array_values(array_filter(array_map(
                fn ($p) => is_string($p) ? trim($p) : '',
                $workflow['phrases'] ?? [],
            )));
            if ($phrases === []) {
                continue;
            }
            $workflows[] = [
                'id' => (string) ($workflow['id'] ?? ''),
                'label' => (string) ($workflow['label'] ?? ''),
                'kind' => 'workflow',
                'phrases' => $phrases,
            ];
        }

        $briefing = array_values(array_filter(array_map(
            fn ($p) => is_string($p) ? trim($p) : '',
            $config['briefingPhrases'] ?? [],
        )));

        return response()->json([
            'locale' => app()->getLocale(),
            'commands' => $commands,
            'workflows' => $workflows,
            'briefing_phrases' => $briefing,
        ]);
    }

    public function calendar(Request $request, WorkspaceCalendarService $calendar, GoogleCalendarSyncService $google): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $monthParam = $request->query('month');
        $anchor = is_string($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam)
            ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $from = $anchor->copy()->subMonth()->startOfMonth();
        $to = $anchor->copy()->addMonth()->endOfMonth();

        $events = $calendar->eventsForCompany($company, $from, $to, null, false, $request->user());

        usort($events, fn ($a, $b) => strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? '')));

        return response()->json([
            'month' => $anchor->format('Y-m'),
            'events' => array_values($events),
            'google_calendar_connected' => $google->isConnected($company),
        ]);
    }

    public function storeEvent(
        Request $request,
        CalendarMeetingLinkService $meetings,
        GoogleCalendarSyncService $google,
    ): JsonResponse {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['nullable', 'date_format:H:i'],
            'message' => ['nullable', 'string', 'max:2000'],
            'client_id' => ['nullable', 'string'],
            'meeting_link_type' => ['nullable', 'string', Rule::enum(CalendarMeetingLinkType::class)],
            'sync_google' => ['sometimes', 'boolean'],
            'kind' => ['nullable', 'string', Rule::in(['meeting', 'appointment', 'reminder', 'note'])],
        ]);

        $clientId = $data['client_id'] ?? null;
        if ($clientId) {
            $exists = Client::query()
                ->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('id', $clientId)
                ->exists();
            abort_unless($exists, 422);
        }

        $linkType = CalendarMeetingLinkType::tryFrom((string) ($data['meeting_link_type'] ?? 'none'))
            ?? CalendarMeetingLinkType::None;

        if ($linkType === CalendarMeetingLinkType::GoogleMeet && ! $google->isConnected($company)) {
            $linkType = CalendarMeetingLinkType::None;
        }

        $kind = (string) ($data['kind'] ?? ($clientId || $linkType !== CalendarMeetingLinkType::None ? 'meeting' : 'appointment'));

        try {
            $meetingFields = $meetings->resolveForNewEvent(
                $company,
                $linkType,
                null,
                $data['title'],
                $data['date'],
            );
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first()], 422);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $clientId,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['message'] ?? '',
            'starts_on' => $data['date'],
            'start_time' => $data['time'] ?? null,
            'ends_on' => $data['date'],
            'kind' => $kind,
            'source_type' => 'mobile_app',
            ...$meetingFields,
        ]);

        if ($linkType === CalendarMeetingLinkType::GoogleMeet && $google->isConnected($company)) {
            $meetings->finalizeGoogleMeet($event);
            $event->refresh();
        }

        // Default true: mobile events sync to Google when the workspace is connected.
        $syncGoogle = $request->boolean('sync_google', true);
        if ($syncGoogle && $google->isConnected($company)) {
            $google->syncWorkspaceEvent($event, $linkType === CalendarMeetingLinkType::GoogleMeet);
            $event->refresh();
        }

        return response()->json([
            'event' => [
                'id' => $event->id,
                'title' => $event->title,
                'date' => $event->starts_on->toDateString(),
                'time' => $event->start_time,
                'description' => $event->description,
                'meeting_link_type' => $event->meeting_link_type?->value ?? (string) $event->meeting_link_type,
                'meeting_url' => $meetings->publicMeetingUrl($event),
                'google_synced' => filled($event->google_calendar_event_id),
            ],
            'message' => __('Event created'),
        ], 201);
    }

    public function destroyEvent(Request $request, string $event, GoogleCalendarSyncService $google): JsonResponse
    {
        $company = $request->user()->company;
        abort_if(! $company, 403);

        $eventId = $event;
        if (str_starts_with($eventId, 'custom-')) {
            $eventId = substr($eventId, 7);
        }

        $model = WorkspaceCalendarEvent::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($eventId)
            ->firstOrFail();

        if (filled($model->google_calendar_event_id)) {
            $googleId = (string) $model->google_calendar_event_id;
            $google->deleteEventForCompany($company, $googleId);
            $googleRemoved = true;
        } else {
            $googleRemoved = false;
        }

        $model->delete();

        return response()->json([
            'ok' => true,
            'message' => __('Event deleted'),
            'google_removed' => $googleRemoved,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $index = Cache::get($this->indexKey($request->user()->id), []);
        if (! is_array($index)) {
            $index = [];
        }

        return response()->json([
            'conversations' => array_values($index),
        ]);
    }

    public function destroyConversation(Request $request, string $conversationId): JsonResponse
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{10,40}$/', $conversationId) === 1, 404);

        $userId = $request->user()->id;
        Cache::forget($this->historyKey($userId, $conversationId));

        $indexKey = $this->indexKey($userId);
        $index = Cache::get($indexKey, []);
        if (! is_array($index)) {
            $index = [];
        }
        unset($index[$conversationId]);
        Cache::put($indexKey, $index, self::CACHE_TTL);

        return response()->json(['ok' => true]);
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $indexKey = $this->indexKey($userId);
        $index = Cache::get($indexKey, []);
        if (! is_array($index)) {
            $index = [];
        }

        foreach (array_keys($index) as $conversationId) {
            if (is_string($conversationId)) {
                Cache::forget($this->historyKey($userId, $conversationId));
            }
        }

        Cache::forget($indexKey);

        return response()->json(['ok' => true]);
    }

    public function conversation(Request $request, string $conversationId): JsonResponse
    {
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
        return 'company_nova_hist:'.$userId.':'.$conversationId;
    }

    private function indexKey(string $userId): string
    {
        return 'company_nova_index:'.$userId;
    }

    private function openingMessageHasClientDetails(string $message): bool
    {
        if (\App\Services\NovaVoiceInputParser::extractEmail($message) !== null) {
            return true;
        }
        if (\App\Services\NovaVoiceInputParser::extractPhone($message) !== null) {
            return true;
        }
        if (\App\Services\NovaVoiceInputParser::looksLikeMultiFieldInput($message)) {
            return true;
        }

        return (bool) preg_match('/\b(named?|called|email|mail|phone|tel|nom|prénom|prenom)\b/iu', $message);
    }

    private function systemPrompt(string $companyName, string $userName, string $context): string
    {
        $brand = config('flowdesk.ai_assistant_brand_name', 'Nova');

        return "You are {$brand}, the AI assistant for {$companyName}, helping workspace user {$userName} via the ClicFlow company mobile app. "
            ."Be concise, practical, and action-oriented. Use the workspace context below when answering.\n"
            ."IMPORTANT: You cannot create or modify database records yourself. When the user wants to create a client, "
            ."tell them to say \"create client\". For a quote without a project, tell them to say \"create quote\". "
            ."For an invoice without a project, tell them to say \"create invoice\". "
            ."Those guided workflows collect client name, email, work description, then create and offer to send.\n\n"
            ."WORKSPACE CONTEXT:\n{$context}";
    }

    private function companyContext(string $companyId): string
    {
        $clients = Client::query()->withoutGlobalScopes()->where('company_id', $companyId)->latest()->limit(8)->get(['name', 'email', 'status']);
        $projects = Project::query()->withoutGlobalScopes()->where('company_id', $companyId)->latest()->limit(8)->get(['title', 'status', 'final_price', 'negotiated_price']);
        $invoices = Invoice::query()->withoutGlobalScopes()->where('company_id', $companyId)->latest()->limit(8)->get(['number', 'status', 'amount', 'currency']);

        $lines = ['Clients:'];
        foreach ($clients as $c) {
            $lines[] = "- {$c->name}".($c->email ? " <{$c->email}>" : '').' ['.$this->enumValue($c->status).']';
        }
        if ($clients->isEmpty()) {
            $lines[] = '- none';
        }

        $lines[] = 'Projects:';
        foreach ($projects as $p) {
            $price = $p->final_price ?? $p->negotiated_price ?? 'n/a';
            $lines[] = "- {$p->title} [".$this->enumValue($p->status)."] price={$price}";
        }
        if ($projects->isEmpty()) {
            $lines[] = '- none';
        }

        $lines[] = 'Invoices:';
        foreach ($invoices as $inv) {
            $lines[] = '- '.($inv->number ?? $inv->id).' ['.$this->enumValue($inv->status)."] {$inv->amount} {$inv->currency}";
        }
        if ($invoices->isEmpty()) {
            $lines[] = '- none';
        }

        return implode("\n", $lines);
    }

    private function enumValue(mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return is_scalar($value) || $value === null ? (string) ($value ?? '') : '';
    }
}
