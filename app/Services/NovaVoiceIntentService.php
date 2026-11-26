<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class NovaVoiceIntentService
{
    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 7;

    public function __construct(
        private NovaVoiceNavigationService $navigation,
        private NovaVoiceWorkflowService $workflows,
        private NovaVoiceEntityActionService $entities,
        private PlatformLlmRouter $llm,
        private PlanLimitService $planLimits,
    ) {}

    /**
     * Map free-form speech to a voice command/workflow using Gemini (or platform LLM), with cache.
     *
     * @return array{
     *     type: 'command'|'workflow'|'chat'|'none',
     *     id: string|null,
     *     label: string|null,
     *     url: string|null,
     *     action: string|null,
     *     cached: bool,
     *     confidence: float
     * }
     */
    public function resolve(User $user, string $text): array
    {
        $company = $user->company;
        $normalized = $this->normalize($text);
        if ($normalized === '' || $company === null) {
            return $this->none();
        }

        // Smart entity actions (edit/delete client by name/phone, quote/invoice by reference)
        // run before cache/LLM so French verbs cannot be remapped to the wrong entity.
        $entity = $this->entities->resolve($user, $normalized);
        if ($entity !== null) {
            return [...$entity, 'cached' => false];
        }

        // Bare "delete" / "supprimer" → clarify what to delete (do not jump to a list URL).
        if (preg_match('/^(delete|supprimer|effacer|remove)$/u', $normalized) === 1) {
            return [
                'type' => 'workflow',
                'id' => 'delete_record',
                'label' => __('nova_workflow_label_delete_record'),
                'url' => null,
                'action' => null,
                'confidence' => 0.99,
                'cached' => false,
                'workflow_seed' => [],
            ];
        }

        $gates = $this->planLimits->featureGates($company);
        $commands = $this->navigation->commandsFor($user, $gates);
        $workflows = $this->workflows->workflowsFor($user, $gates);

        $cacheKey = $this->cacheKey((int) $company->id, (int) $user->id, $normalized);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['type'])) {
            // Never hydrate entity-targeted intents from a stale id-only cache.
            if (empty($cached['entity_target'])) {
                $hydrated = $this->hydrateCached($cached, $commands, $workflows);
                if ($hydrated !== null && ($hydrated['type'] ?? 'none') !== 'none') {
                    return [...$hydrated, 'cached' => true];
                }
            }
        }

        if (! $this->llm->isAvailable($company)) {
            return $this->none();
        }

        try {
            $parsed = $this->classifyWithLlm($normalized, $commands, $workflows, $company);
        } catch (RuntimeException $e) {
            Log::info('Nova voice intent classification failed.', [
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return $this->none();
        }

        // If Gemini extracted a target, resolve the concrete record URL.
        $target = isset($parsed['target']) && is_string($parsed['target']) ? trim($parsed['target']) : '';
        if ($target !== '' && in_array(($parsed['type'] ?? ''), ['command'], true)) {
            $withTarget = $this->entities->resolve(
                $user,
                $this->rebuildUtteranceFromParsed($parsed['id'] ?? '', $target),
            );
            if ($withTarget !== null) {
                return [...$withTarget, 'cached' => false];
            }
        }

        $result = $this->mapParsedIntent($parsed, $commands, $workflows);
        if (in_array($result['type'], ['command', 'workflow'], true) && $result['confidence'] >= 0.7) {
            Cache::put($cacheKey, [
                'type' => $result['type'],
                'id' => $result['id'],
                'confidence' => $result['confidence'],
            ], now()->addSeconds(self::CACHE_TTL_SECONDS));
        }

        return [...$result, 'cached' => false];
    }

    /**
     * @param  list<array{id: string, label: string, url?: string, action?: string, phrases?: list<string>}>  $commands
     * @param  list<array{id: string, label?: string, phrases?: list<string>}>  $workflows
     * @return array{type: string, id: ?string, confidence: float, target?: ?string}
     */
    private function classifyWithLlm(string $utterance, array $commands, array $workflows, $company): array
    {
        $catalog = [];
        foreach ($commands as $command) {
            $id = (string) ($command['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $phrases = array_slice(array_values(array_filter($command['phrases'] ?? [])), 0, 6);
            $catalog[] = [
                'kind' => 'command',
                'id' => $id,
                'label' => (string) ($command['label'] ?? $id),
                'examples' => $phrases,
            ];
        }
        foreach ($workflows as $workflow) {
            $id = (string) ($workflow['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $phrases = array_slice(array_values(array_filter($workflow['phrases'] ?? [])), 0, 6);
            $catalog[] = [
                'kind' => 'workflow',
                'id' => $id,
                'label' => (string) ($workflow['label'] ?? $id),
                'examples' => $phrases,
            ];
        }

        $system = <<<'PROMPT'
You map spoken workspace commands for ClicFlow SaaS to a catalog entry.
Return ONLY compact JSON:
{"type":"command"|"workflow"|"chat","id":"<catalog id or null>","target":"<optional entity name/phone/reference or null>","confidence":0.0-1.0}

Rules:
- Prefer navigation/actions (open list, create, edit, delete) over chat when the user clearly wants an in-app action.
- CRITICAL: preserve the entity type from the utterance language:
  - client/cliente → clients.* commands (NOT invoices)
  - devis/quote/proposal/proposition → proposals.*
  - facture/invoice → invoices.*
- Never map "supprimer client …" to invoices.delete or "delete invoice".
- When the user names a person, phone, or document reference after edit/delete, put it in "target" and still choose the correct edit/delete catalog id.
- Bare "delete" / "supprimer" without an entity → workflow id "delete_record".
- Delete without a target (e.g. "delete client") maps to the matching *.delete catalog id (workflow clarify).
- Use type "chat" when the user asks a question, wants analysis, or no catalog entry fits.
- id must be exactly one of the catalog ids, or null for chat.
- confidence >= 0.7 only when you are sure.
PROMPT;

        $userPrompt = "Utterance:\n{$utterance}\n\nCatalog JSON:\n".json_encode($catalog, JSON_UNESCAPED_UNICODE);

        $completion = $this->llm->complete($system, $userPrompt, 256, $company);
        $raw = trim((string) ($completion['suggestion'] ?? ''));
        $json = $this->extractJsonObject($raw);
        if ($json === null) {
            return ['type' => 'chat', 'id' => null, 'confidence' => 0.0, 'target' => null];
        }

        $type = (string) ($json['type'] ?? 'chat');
        if (! in_array($type, ['command', 'workflow', 'chat'], true)) {
            $type = 'chat';
        }

        $target = isset($json['target']) && is_string($json['target']) && trim($json['target']) !== ''
            ? trim($json['target'])
            : null;

        return [
            'type' => $type,
            'id' => isset($json['id']) && is_string($json['id']) && $json['id'] !== '' ? $json['id'] : null,
            'confidence' => max(0.0, min(1.0, (float) ($json['confidence'] ?? 0))),
            'target' => $target,
        ];
    }

    private function rebuildUtteranceFromParsed(string $commandId, string $target): string
    {
        $target = trim($target);
        $map = [
            'clients.edit' => 'edit client '.$target,
            'clients.delete' => 'delete client '.$target,
            'proposals.edit' => 'edit quote '.$target,
            'proposals.delete' => 'delete quote '.$target,
            'invoices.edit' => 'edit invoice '.$target,
            'invoices.delete' => 'delete invoice '.$target,
        ];

        return $map[$commandId] ?? $target;
    }

    /**
     * @param  array{type: string, id: ?string, confidence: float}  $parsed
     * @param  list<array<string, mixed>>  $commands
     * @param  list<array<string, mixed>>  $workflows
     * @return array{type: 'command'|'workflow'|'chat'|'none'|'speak', id: ?string, label: ?string, url: ?string, action: ?string, confidence: float, workflow?: ?string, workflow_seed?: array<string, mixed>, reply?: ?string, done?: bool}
     */
    private function mapParsedIntent(array $parsed, array $commands, array $workflows): array
    {
        $type = $parsed['type'];
        $id = $parsed['id'];
        $confidence = (float) $parsed['confidence'];

        if ($type === 'command' && is_string($id)) {
            foreach ($commands as $command) {
                if ((string) ($command['id'] ?? '') !== $id) {
                    continue;
                }

                $workflow = isset($command['workflow']) && is_string($command['workflow'])
                    ? $command['workflow']
                    : null;

                return [
                    'type' => $workflow ? 'workflow' : 'command',
                    'id' => $workflow ?: $id,
                    'label' => (string) ($command['label'] ?? $id),
                    'url' => (string) ($command['url'] ?? ''),
                    'action' => isset($command['action']) ? (string) $command['action'] : null,
                    'confidence' => $confidence,
                    'workflow' => $workflow,
                    'workflow_seed' => is_array($command['workflow_seed'] ?? null) ? $command['workflow_seed'] : [],
                ];
            }
        }

        if ($type === 'workflow' && is_string($id)) {
            foreach ($workflows as $workflow) {
                if ((string) ($workflow['id'] ?? '') !== $id) {
                    continue;
                }

                return [
                    'type' => 'workflow',
                    'id' => $id,
                    'label' => (string) ($workflow['label'] ?? $id),
                    'url' => null,
                    'action' => null,
                    'confidence' => $confidence,
                ];
            }
        }

        if ($type === 'chat') {
            return [
                'type' => 'chat',
                'id' => null,
                'label' => null,
                'url' => null,
                'action' => null,
                'confidence' => $confidence,
            ];
        }

        return $this->none();
    }

    /**
     * @param  array<string, mixed>  $cached
     * @param  list<array<string, mixed>>  $commands
     * @param  list<array<string, mixed>>  $workflows
     * @return array{type: 'command'|'workflow'|'chat'|'none', id: ?string, label: ?string, url: ?string, action: ?string, confidence: float}|null
     */
    private function hydrateCached(array $cached, array $commands, array $workflows): ?array
    {
        return $this->mapParsedIntent([
            'type' => (string) ($cached['type'] ?? 'none'),
            'id' => isset($cached['id']) && is_string($cached['id']) ? $cached['id'] : null,
            'confidence' => (float) ($cached['confidence'] ?? 1),
        ], $commands, $workflows);
    }

    /**
     * @return array{type: 'none', id: null, label: null, url: null, action: null, cached: bool, confidence: float}
     */
    private function none(): array
    {
        return [
            'type' => 'none',
            'id' => null,
            'label' => null,
            'url' => null,
            'action' => null,
            'cached' => false,
            'confidence' => 0.0,
        ];
    }

    private function normalize(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text)) ?? '';

        return mb_strtolower($collapsed);
    }

    private function cacheKey(int $companyId, int $userId, string $normalized): string
    {
        return 'nova_voice_intent:'.$companyId.':'.$userId.':'.hash('sha256', $normalized);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractJsonObject(string $raw): ?array
    {
        if ($raw === '') {
            return null;
        }

        if (preg_match('/\{.*\}/s', $raw, $matches) === 1) {
            $raw = $matches[0];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
