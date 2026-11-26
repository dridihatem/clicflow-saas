<?php

namespace App\Services\AiSidebar;

use App\Models\AiConversation;
use App\Models\AiConversationMessage;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\AiCreditUsageService;
use App\Services\NovaAssistantService;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\AiAssistantPrompts;
use Illuminate\Support\Str;
use Throwable;

/**
 * Right-side "Assistant IA" chat panel: persistent per-user/tenant conversation that can
 * (1) prepare a create-form draft (redirect + live fill), (2) adjust the quote/invoice form
 * currently open (ops applied client-side, nothing saved), or (3) answer general questions.
 */
class AiChatService
{
    public const HISTORY_LIMIT = 60;

    public function __construct(
        private AiDraftService $drafts,
        private PlatformLlmRouter $llm,
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
        private AiFormFieldService $formFields,
    ) {}

    public function currentConversation(Company $company, User $user, ?string $id = null): ?AiConversation
    {
        $q = AiConversation::query()
            ->where('company_id', $company->id)
            ->where('user_id', $user->id)
            ->where('channel', AiConversation::CHANNEL_SIDEBAR);
        if ($id) {
            return (clone $q)->where('id', $id)->first();
        }

        return $q->latest('updated_at')->latest('id')->first();
    }

    public function startConversation(Company $company, User $user): AiConversation
    {
        return AiConversation::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'channel' => AiConversation::CHANNEL_SIDEBAR,
            'title' => null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function history(?AiConversation $conversation): array
    {
        if (! $conversation) {
            return [];
        }

        return $conversation->messages()->reorder()->latest('created_at')->latest('id')->limit(self::HISTORY_LIMIT)->get()
            ->reverse()->values()->map(fn (AiConversationMessage $m) => $this->present($m))->all();
    }

    /** @return array<string, mixed> */
    public function present(AiConversationMessage $m): array
    {
        return [
            'id' => (string) $m->id,
            'role' => $m->role === 'user' ? 'user' : 'assistant',
            'content' => (string) $m->content,
            'meta' => is_array($m->meta) ? $m->meta : null,
            'at' => optional($m->created_at)->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context  {form: quote|invoice|null, lines: list<{description, quantity, unit_minor, product_id}>, currency}
     * @return array{conversation_id: string, user: array<string, mixed>, assistant: array<string, mixed>, action: ?array<string, mixed>}
     */
    public function send(Company $company, User $user, string $message, ?string $conversationId, array $context): array
    {
        $message = trim($message);
        $conversation = $this->currentConversation($company, $user, $conversationId) ?? $this->startConversation($company, $user);
        if (! $conversation->title) {
            $conversation->title = Str::limit($message, 80);
        }
        $conversation->touch();
        $conversation->save();

        $userMsg = $conversation->messages()->create(['role' => 'user', 'content' => $message]);

        $form = in_array($context['form'] ?? null, ['quote', 'invoice'], true) ? $context['form'] : null;
        $fieldForm = in_array($context['form'] ?? null, AiFormFieldService::FORMS, true) ? $context['form'] : null;
        $lines = $this->contextLines($context);

        $mode = 'chat';
        if ($this->isCreateRequest($message)) {
            $mode = 'create';
        } elseif ($form !== null && $this->isAdjustRequest($message)) {
            $mode = 'adjust';
        } elseif ($fieldForm !== null && ($this->isAdjustRequest($message) || $this->formFields->mentionsField($message, $fieldForm))) {
            $mode = 'fields';
        }

        try {
            [$content, $meta, $action] = match ($mode) {
                'create' => $this->handleCreate($company, $user, $message),
                'adjust' => $this->handleAdjust($company, $message, $form, $lines, (string) ($context['currency'] ?? '')),
                'fields' => $this->handleFields($company, $message, $fieldForm, is_array($context['fields'] ?? null) ? $context['fields'] : []),
                default => $this->handleChat($company, $conversation, $message, $form ?? $fieldForm),
            };
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // e.g. AI credits exhausted: answer inline instead of breaking the chat.
            [$content, $meta, $action] = [$e->getMessage() !== '' ? $e->getMessage() : __('ai_sidebar_error'), ['type' => 'error'], null];
        }

        $assistant = $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $content,
            'meta' => $meta,
        ]);
        $conversation->touch();

        return [
            'conversation_id' => (string) $conversation->id,
            'user' => $this->present($userMsg),
            'assistant' => $this->present($assistant),
            'action' => $action,
        ];
    }

    // ------------------------------------------------------------------ classification

    public function isCreateRequest(string $message): bool
    {
        $verb = '/\b(cr[ée]{1,2}r?|cr[ée]{1,2}e[sz]?|cr[ée]ation|fais|faire|fait|pr[ée]par\w*|g[ée]n[èée]r\w*|[ée]tabli\w*|r[ée]dig\w*|nouveau|nouvelle|create|make|draft|new|prepare|generate)\b/iu';
        $entity = '/\b(devis|quote|proposition|proposal|offre|facture|invoice|client|customer|produit|product|service|prestation|article|projet|project|t[âa]che|task)\b/iu';

        return (bool) preg_match($verb, $message) && (bool) preg_match($entity, $message);
    }

    public function isAdjustRequest(string $message): bool
    {
        return (bool) preg_match('/(^\s*\+)|\b(ajout\w*|rajout\w*|add|enl[èe]v\w*|retir\w*|supprim\w*|remove|delete|remise|r[ée]duction|discount|rabais|quantit[ée]|mets|met|mettre|passe|change\w*|modifi\w*|remplac\w*|prix|tarif|price|tva|vat)\b/iu', $message);
    }

    // ------------------------------------------------------------------ create

    /** @return array{0: string, 1: ?array<string, mixed>, 2: ?array<string, mixed>} */
    private function handleCreate(Company $company, User $user, string $message): array
    {
        $useLlm = $this->drafts->llmAvailable($company);
        if ($useLlm) {
            $this->planLimits->assertAllows($company, 'ai_credits', $this->usage->creditsForTask('ai_sidebar_draft'));
        }
        $result = $this->drafts->createDraft($company, $user, $message, $useLlm);
        $draft = $result['draft'];
        if ($draft['source'] === 'ai') {
            $this->usage->recordForTask($company, 'ai_sidebar_draft');
        }
        if ($draft['entity'] === 'task' && empty($draft['route_params'])) {
            // No project resolved: nothing to open — ask which project instead of redirecting.
            $content = __('ai_chat_task_need_project').(($draft['project']['title'] ?? '') !== '' ? ' '.__('ai_draft_unresolved_project', ['name' => $draft['project']['title']]) : '');

            return [$content, ['type' => 'help', 'entity' => 'task'], null];
        }
        $url = $this->drafts->draftUrl($draft, $result['token']);

        $entityLabel = __('ai_chat_entity_'.$draft['entity']);
        $parts = [];
        if (in_array($draft['entity'], ['quote', 'invoice'], true)) {
            $total = collect($draft['lines'])->sum(fn ($l) => (int) $l['quantity'] * (int) $l['unit_minor']);
            $parts[] = trans_choice('ai_chat_lines_count', count($draft['lines']), ['count' => count($draft['lines'])]);
            if ($total > 0) {
                $parts[] = flowdesk_format_minor((int) $total, (string) $draft['currency']).' '.strtoupper((string) $draft['currency']);
            }
        }
        if ($draft['entity'] === 'task') {
            if (! empty($draft['fields']['ends_on'])) {
                $parts[] = __('ai_chat_task_due', ['date' => \Carbon\Carbon::parse($draft['fields']['ends_on'])->locale(app()->getLocale())->isoFormat('dddd D MMMM')]);
            }
            foreach ($draft['assignees'] ?? [] as $a) {
                $parts[] = __('ai_chat_op_assignee', ['name' => $a['name']]);
            }
        }
        $who = $draft['entity'] === 'task'
            ? (($draft['fields']['title'] ?? '') !== '' ? '« '.$draft['fields']['title'].' » — '.($draft['project']['title'] ?? '') : (string) ($draft['project']['title'] ?? ''))
            : ($draft['client']['name'] ?? ($draft['fields']['name'] ?? ($draft['fields']['title'] ?? '')));
        $content = __('ai_chat_draft_ready', [
            'entity' => $entityLabel,
            'who' => $who !== '' ? ' '.__('ai_chat_for').' '.$who : '',
            'details' => $parts !== [] ? ' ('.implode(', ', $parts).')' : '',
        ]);
        $unresolved = collect($draft['unresolved'])->pluck('label')->filter()->values()->all();
        if ($unresolved !== []) {
            $content .= "\n".__('ai_chat_to_check').' '.implode(' · ', $unresolved);
        }

        return [$content, [
            'type' => 'draft',
            'entity' => $draft['entity'],
            'url' => $url,
            'source' => $draft['source'],
            'expires_at' => now()->addMinutes(AiDraftService::TTL_MINUTES)->toIso8601String(),
        ], ['type' => 'redirect', 'url' => $url]];
    }

    // ------------------------------------------------------------------ adjust

    /**
     * @param  list<array{description: string, quantity: int, unit_minor: int, product_id: string}>  $lines
     * @return array{0: string, 1: ?array<string, mixed>, 2: ?array<string, mixed>}
     */
    private function handleAdjust(Company $company, string $message, string $form, array $lines, string $currency): array
    {
        $intents = null;
        $source = 'rules';
        if ($this->drafts->llmAvailable($company)) {
            try {
                $this->planLimits->assertAllows($company, 'ai_credits', $this->usage->creditsForTask('ai_sidebar_chat'));
                $intents = $this->adjustIntentsFromLlm($company, $message, $lines);
                $this->usage->recordForTask($company, 'ai_sidebar_chat');
                $source = 'ai';
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                throw $e;
            } catch (Throwable $e) {
                report($e);
                $intents = null;
            }
        }
        if (! $intents) {
            $intents = $this->adjustIntentsFromRules($message, $lines);
            $source = 'rules';
        }

        [$ops, $summary, $issues] = $this->resolveAdjust($company, $intents, $lines);
        if ($ops === []) {
            $content = __('ai_chat_adjust_none');
            if ($issues !== []) {
                $content .= "\n".implode("\n", $issues);
            }

            return [$content, ['type' => 'adjust', 'applied' => 0, 'source' => $source], null];
        }
        $content = __('ai_chat_adjust_done', ['changes' => implode(', ', $summary)]);
        if ($issues !== []) {
            $content .= "\n".implode("\n", $issues);
        }
        $content .= "\n".__('ai_chat_adjust_review');

        return [$content, ['type' => 'adjust', 'applied' => count($ops), 'source' => $source], ['type' => 'ops', 'form' => $form, 'ops' => $ops]];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public function adjustIntentsFromRules(string $message, array $lines): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        $out = [];
        $cur = '(?:€|eur\b|euros?\b|dt\b|tnd\b|\$|usd\b)';

        // Per-line VAT: « TVA 0 % sur la ligne 2 », « mets la TVA à 19 % », « 7 % de TVA sur la formation ».
        if (preg_match('/\b(?:tva|vat)\s*(?:à|a|de|=|:)?\s*(\d{1,3}(?:[.,]\d{1,3})?)\s*%|(\d{1,3}(?:[.,]\d{1,3})?)\s*%\s*(?:de\s+)?(?:tva|vat)\b/iu', $text, $m)) {
            $rate = $this->drafts->parseAmount(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? ''));
            $rest = trim(str_replace($m[0], '', $text));
            $intent = ['op' => 'vat', 'rate' => $rate];
            if (preg_match('/\b(?:sur|pour|on|for)\s+(?:la\s+)?ligne\s*(?:n[°o]?\s*)?(\d{1,3})\b/iu', $rest, $lm)) {
                $intent['line'] = max(0, (int) $lm[1] - 1);
            } elseif (preg_match('/\b(?:sur|pour|on|for)\s+(?:la\s+|le\s+|les\s+|l[\'’])?(?!tout|toutes|all\b|le document|la facture|le devis)(.{3,})$/iu', $rest, $lm)) {
                $intent['match'] = trim($lm[1], ' .');
            }
            $out[] = $intent;

            return $out;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%\s*(?:de\s+)?(?:remise|r[ée]duction|discount|rabais)|(?:remise|r[ée]duction|discount|rabais)\s*(?:de\s+|globale\s+de\s+)?(\d+(?:[.,]\d+)?)\s*%/iu', $text, $m)) {
            $out[] = ['op' => 'discount', 'type' => 'percent', 'value' => $this->drafts->parseAmount($m[1] !== '' ? $m[1] : $m[2])];
            $text = trim(str_replace($m[0], '', $text));
        } elseif (preg_match('/(?:remise|r[ée]duction|discount|rabais)\s*(?:de\s+|globale\s+de\s+)?(\d[\d\s.,]*)\s*'.$cur.'|(\d[\d\s.,]*)\s*'.$cur.'\s*(?:de\s+)?(?:remise|r[ée]duction|discount|rabais)/iu', $text, $m)) {
            $out[] = ['op' => 'discount', 'type' => 'amount', 'value' => $this->drafts->parseAmount(($m[1] ?? '') !== '' ? $m[1] : ($m[2] ?? ''))];
            $text = trim(str_replace($m[0], '', $text));
        } elseif (preg_match('/\b(?:enl[èe]ve|retire|supprime|annule|remove)\s+(?:la\s+)?(?:remise|r[ée]duction|discount|rabais)\b/iu', $text, $m)) {
            $out[] = ['op' => 'discount', 'type' => 'percent', 'value' => 0, 'clear' => true];

            return $out;
        }

        if (preg_match('/\b(?:change|changer|remplace|remplacer|mets|mettre)\s+(?:le\s+)?client\s+(?:par|pour|en|à|a|:)?\s*(.+)$/iu', $text, $m)) {
            $out[] = ['op' => 'client', 'client_name' => trim($m[1], " .\"'«»")];

            return $out;
        }

        if (preg_match('/\b(?:enl[èe]ve|enlever|retire|retirer|supprime|supprimer|remove|delete)\s+(?:la\s+ligne\s+|les\s+lignes\s+)?(.+)$/iu', $text, $m)) {
            foreach (preg_split('/\s*(?:,|;|\bet\b|\band\b)\s*/iu', $m[1]) ?: [] as $seg) {
                $seg = trim(preg_replace('/^(?:le|la|les|l[\'’]|un|une|des|du|de)\s*/iu', '', trim($seg, ' .')) ?? $seg);
                if ($seg !== '') {
                    $out[] = ['op' => 'remove', 'match' => $seg];
                }
            }

            return $out;
        }

        if (preg_match('/\b(?:mets|met|mettre|passe|passer|change|changer|modifie|modifier)\s+(?:le\s+prix\s+|le\s+tarif\s+)?(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|la\s+|le\s+|les\s+|l[\'’])?(.+?)\s+(?:à|a|en|au prix de|=)\s*(\d[\d\s.,]*)\s*'.$cur.'/iu', $text, $m)) {
            $out[] = ['op' => 'price', 'match' => trim($m[1]), 'unit_price' => $this->drafts->parseAmount($m[2])];

            return $out;
        }

        if (preg_match('/\b(?:mets|met|mettre|passe|passer|change|changer|modifie|modifier)\s+(?:la\s+quantit[ée]\s+)?(?:de\s+la\s+|de\s+l[\'’]|du\s+|des\s+|de\s+|la\s+|le\s+|les\s+|l[\'’])?(.+?)\s+(?:à|a|en|=)\s*(\d{1,4})\b(?!\s*(?:%|[.,]\d))/iu', $text, $m)) {
            $out[] = ['op' => 'quantity', 'match' => trim($m[1]), 'quantity' => (int) $m[2]];

            return $out;
        }
        if (preg_match('/\b(?:mets|met|mettre)\s+(\d{1,4})\s+(.+)$/iu', $text, $m)) {
            $out[] = ['op' => 'quantity', 'match' => trim($m[2], ' .'), 'quantity' => (int) $m[1]];

            return $out;
        }

        if (preg_match('/(?:^\s*\+\s*|\b(?:ajoute|ajouter|ajoutez|rajoute|rajouter|add)\s+)(.+)$/iu', $text, $m)) {
            foreach ($this->drafts->parseLineList($m[1]) as $line) {
                $out[] = ['op' => 'add'] + $line;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    private function adjustIntentsFromLlm(Company $company, string $message, array $lines): array
    {
        $catalog = Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)
            ->orderBy('name')->limit(80)->pluck('name')->map(fn ($n) => '- '.$n)->implode("\n");
        $current = collect($lines)->map(fn ($l, $i) => '#'.$i.' '.$l['description'].' | qty '.$l['quantity'].' | unit '.round($l['unit_minor'] / 100, 2).(isset($l['vat_rate']) && $l['vat_rate'] !== null ? ' | TVA '.$l['vat_rate'].'%' : ''))->implode("\n");

        $system = <<<'PROMPT'
You edit the lines of a quote/invoice form that is open on screen. Respond with ONLY valid JSON, no markdown:
{"ops":[
 {"op":"add","description":"","product_name":"","quantity":1,"unit_price":null},
 {"op":"quantity","line":0,"quantity":2},
 {"op":"remove","line":0},
 {"op":"price","line":0,"unit_price":100},
 {"op":"discount","type":"percent","value":10,"line":null},
 {"op":"vat","rate":20,"line":null},
 {"op":"client","client_name":""}
]}
Rules:
- "line" is the #index of the CURRENT lines below. Adding more of an item that already exists ("ajoute 2 sessions") is a quantity op with the NEW total quantity.
- product_name: the EXACT catalog name when it clearly matches, else empty. unit_price only if the user gives a price (major units), else null.
- discount: a document discount before VAT. type "percent" (value = %) or "amount" (value = major units off the total excl. VAT). line null = the whole document (default). value 0 = remove the discount.
- vat: the VAT rate (%) of a line ("line" = #index) or of every line (line null).
- Return {"ops":[]} if the request is not an edit of this form. Never invent prices.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();
        $user = "Catalog:\n".($catalog !== '' ? $catalog : '(empty)')."\n\nCurrent lines:\n".($current !== '' ? $current : '(none)')."\n\nRequest:\n".$message;

        $result = $this->llm->completeFast($system, $user, 700, $company, true);
        $raw = trim((string) ($result['suggestion'] ?? ''));
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! is_array($decoded['ops'] ?? null)) {
            throw new \RuntimeException('AI chat: invalid JSON.');
        }

        return collect($decoded['ops'])->filter(fn ($o) => is_array($o) && in_array($o['op'] ?? null, ['add', 'quantity', 'remove', 'price', 'discount', 'vat', 'client'], true))
            ->take(15)->values()->all();
    }

    /**
     * @param  list<array<string, mixed>>  $intents
     * @param  list<array<string, mixed>>  $lines
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: list<string>}
     */
    private function resolveAdjust(Company $company, array $intents, array $lines): array
    {
        $ops = [];
        $summary = [];
        $issues = [];
        $qty = array_map(fn ($l) => (int) $l['quantity'], $lines);
        $removed = [];

        $findLine = function (array $intent) use ($lines, &$removed): ?int {
            if (isset($intent['line']) && is_numeric($intent['line']) && isset($lines[(int) $intent['line']])) {
                return (int) $intent['line'];
            }
            $q = trim((string) ($intent['match'] ?? ($intent['product_name'] ?? ($intent['description'] ?? ''))));
            if ($q === '') {
                return null;
            }
            $best = null;
            $bestScore = 0.0;
            foreach ($lines as $i => $l) {
                if (in_array($i, $removed, true)) {
                    continue;
                }
                $score = AiDraftService::similarity($q, (string) $l['description']);
                if ($score > $bestScore) {
                    $best = $i;
                    $bestScore = $score;
                }
            }

            return $bestScore >= 60.0 ? $best : null;
        };

        $adds = [];
        foreach ($intents as $intent) {
            $op = $intent['op'] ?? '';
            if ($op === 'vat') {
                $rate = round(max(0.0, min(100.0, (float) ($intent['rate'] ?? 0))), 3);
                $hasTarget = (isset($intent['line']) && is_numeric($intent['line'])) || trim((string) ($intent['match'] ?? '')) !== '';
                $idx = $hasTarget ? $findLine($intent) : null;
                if ($hasTarget && $idx === null) {
                    $issues[] = __('ai_chat_line_not_found', ['name' => (string) ($intent['match'] ?? ('#'.((int) ($intent['line'] ?? 0) + 1)))]);

                    continue;
                }
                $ops[] = ['op' => 'vat', 'rate' => $rate, 'index' => $idx];
                $fmt = rtrim(rtrim(number_format($rate, 3, ',', ''), '0'), ',');
                $summary[] = $idx !== null
                    ? __('ai_chat_op_vat_line', ['rate' => $fmt, 'name' => $lines[$idx]['description']])
                    : __('ai_chat_op_vat_all', ['rate' => $fmt]);

                continue;
            }
            if ($op === 'discount') {
                $type = ($intent['type'] ?? 'percent') === 'amount' ? 'amount' : 'percent';
                $value = (float) ($intent['value'] ?? ($intent['percent'] ?? 0));
                $value = $type === 'percent' ? max(0.0, min(100.0, $value)) : max(0.0, $value);
                $idx = isset($intent['line']) && is_numeric($intent['line']) && isset($lines[(int) $intent['line']]) ? (int) $intent['line'] : null;
                if ($value <= 0) {
                    if (! empty($intent['clear']) || array_key_exists('value', $intent)) {
                        $ops[] = ['op' => 'discount', 'type' => 'percent', 'value' => 0, 'index' => null];
                        $summary[] = __('ai_chat_op_discount_clear');
                    }

                    continue;
                }
                if ($type === 'amount') {
                    $idx = null;
                }
                $ops[] = ['op' => 'discount', 'type' => $type, 'value' => $value, 'percent' => $type === 'percent' ? $value : null, 'index' => $idx];
                $fmt = rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
                $summary[] = $type === 'percent'
                    ? ($idx !== null ? __('ai_chat_op_discount_line', ['percent' => $fmt, 'name' => $lines[$idx]['description']]) : __('ai_chat_op_discount', ['percent' => $fmt]))
                    : __('ai_chat_op_discount_amount', ['amount' => $fmt]);
            } elseif ($op === 'client') {
                $name = trim((string) ($intent['client_name'] ?? ''));
                $client = $name !== '' ? $this->drafts->matchClient($company, $name) : null;
                if ($client) {
                    $ops[] = ['op' => 'client', 'client' => ['id' => (string) $client->id, 'name' => (string) $client->name]];
                    $summary[] = __('ai_chat_op_client', ['name' => $client->name]);
                } elseif ($name !== '') {
                    $issues[] = __('ai_draft_unresolved_client', ['name' => $name]);
                }
            } elseif ($op === 'remove') {
                $i = $findLine($intent);
                if ($i === null) {
                    $issues[] = __('ai_chat_line_not_found', ['name' => (string) ($intent['match'] ?? '')]);

                    continue;
                }
                $removed[] = $i;
                $ops[] = ['op' => 'remove', 'index' => $i];
                $summary[] = __('ai_chat_op_remove', ['name' => $lines[$i]['description']]);
            } elseif ($op === 'quantity') {
                $i = $findLine($intent);
                $n = max(1, (int) ($intent['quantity'] ?? 1));
                if ($i === null) {
                    $issues[] = __('ai_chat_line_not_found', ['name' => (string) ($intent['match'] ?? '')]);

                    continue;
                }
                $qty[$i] = $n;
                $ops[] = ['op' => 'quantity', 'index' => $i, 'quantity' => $n];
                $summary[] = __('ai_chat_op_quantity', ['name' => $lines[$i]['description'], 'qty' => $n]);
            } elseif ($op === 'price') {
                $i = $findLine($intent);
                $price = isset($intent['unit_price']) && is_numeric($intent['unit_price']) ? (float) $intent['unit_price'] : null;
                if ($i === null || $price === null) {
                    $issues[] = __('ai_chat_line_not_found', ['name' => (string) ($intent['match'] ?? '')]);

                    continue;
                }
                $ops[] = ['op' => 'price', 'index' => $i, 'unit_major' => $price];
                $summary[] = __('ai_chat_op_price', ['name' => $lines[$i]['description'], 'price' => rtrim(rtrim(number_format($price, 2, ',', ' '), '0'), ',')]);
            } elseif ($op === 'add') {
                // "ajoute 2 sessions" on an existing line = more of the same item.
                $existing = $findLine(['match' => (string) (($intent['product_name'] ?? '') !== '' ? $intent['product_name'] : ($intent['description'] ?? ''))]);
                $price = isset($intent['unit_price']) && is_numeric($intent['unit_price']) ? (float) $intent['unit_price'] : null;
                if ($existing !== null && $price === null && ! in_array($existing, $removed, true)) {
                    $qty[$existing] += max(1, (int) ($intent['quantity'] ?? 1));
                    $ops[] = ['op' => 'quantity', 'index' => $existing, 'quantity' => $qty[$existing]];
                    $summary[] = __('ai_chat_op_quantity', ['name' => $lines[$existing]['description'], 'qty' => $qty[$existing]]);

                    continue;
                }
                $adds[] = [
                    'description' => (string) ($intent['description'] ?? ''),
                    'product_name' => (string) ($intent['product_name'] ?? ''),
                    'quantity' => max(1, (int) ($intent['quantity'] ?? 1)),
                    'unit_price' => $price,
                ];
            }
        }

        if ($adds !== []) {
            $resolved = $this->drafts->resolveLines($company, $adds);
            foreach ($resolved['lines'] as $line) {
                $ops[] = ['op' => 'add', 'line' => $line];
                $summary[] = __('ai_chat_op_add', ['qty' => $line['quantity'], 'name' => $line['description']]);
            }
            foreach ($resolved['unresolved'] as $u) {
                $issues[] = $u['label'];
            }
        }

        return [$ops, $summary, $issues];
    }

    // ------------------------------------------------------------------ client / product / project / task fields

    /**
     * @param  array<string, mixed>  $current
     * @return array{0: string, 1: ?array<string, mixed>, 2: ?array<string, mixed>}
     */
    private function handleFields(Company $company, string $message, string $form, array $current): array
    {
        $intents = null;
        $source = 'rules';
        $current = collect($current)->filter(fn ($v, $k) => is_string($k) && is_scalar($v))->map(fn ($v) => (string) $v)->all();
        if ($this->drafts->llmAvailable($company)) {
            try {
                $this->planLimits->assertAllows($company, 'ai_credits', $this->usage->creditsForTask('ai_sidebar_chat'));
                $intents = $this->formFields->intentsFromLlm($company, $message, $form, $current);
                $this->usage->recordForTask($company, 'ai_sidebar_chat');
                $source = 'ai';
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                throw $e;
            } catch (Throwable $e) {
                report($e);
                $intents = null;
            }
        }
        if (! $intents) {
            $intents = $this->formFields->intentsFromRules($message, $form);
            $source = 'rules';
        }

        [$ops, $summary, $issues] = $this->formFields->resolve($company, $form, $intents);
        if ($ops === []) {
            $content = __('ai_chat_fields_none');
            if ($issues !== []) {
                $content .= "\n".implode("\n", $issues);
            }

            return [$content, ['type' => 'adjust', 'applied' => 0, 'source' => $source], null];
        }
        $content = __('ai_chat_adjust_done', ['changes' => implode(', ', $summary)]);
        if ($issues !== []) {
            $content .= "\n".implode("\n", $issues);
        }
        $content .= "\n".__('ai_chat_fields_review');

        return [$content, ['type' => 'adjust', 'applied' => count($ops), 'source' => $source], ['type' => 'ops', 'form' => $form, 'ops' => $ops]];
    }

    // ------------------------------------------------------------------ general chat

    /** @return array{0: string, 1: ?array<string, mixed>, 2: ?array<string, mixed>} */
    private function handleChat(Company $company, AiConversation $conversation, string $message, ?string $form): array
    {
        if (! $this->drafts->llmAvailable($company)) {
            return [__('ai_chat_no_llm'), ['type' => 'help'], null];
        }
        $this->planLimits->assertAllows($company, 'ai_credits', $this->usage->creditsForTask('ai_sidebar_chat'));

        $history = $conversation->messages()->reorder()->latest('created_at')->latest('id')->limit(12)->get()->reverse()->values();
        $transcript = $history->map(fn (AiConversationMessage $m) => ($m->role === 'user' ? 'User: ' : 'Assistant: ').Str::limit((string) $m->content, 600))->implode("\n");

        $workspace = '';
        try {
            $workspace = app(NovaAssistantService::class)->buildCompanyContext($company);
        } catch (Throwable $e) {
            report($e);
        }

        $brand = (string) config('flowdesk.brand_name', 'ClicFlow');
        $system = 'You are the AI assistant docked in the side panel of '.$brand.' (business app: clients, quotes, invoices, products, projects) for the company '.$company->name.'. '
            .'Answer concisely and practically. Use ONLY the workspace snapshot for business figures; never invent records. '
            .'You cannot save, send or delete anything yourself. If the user wants a document or a task, tell them to ask e.g. « crée un devis pour <client> avec … » or « crée une tâche pour le projet <projet> : <titre>, pour vendredi, assignée à <personne> » and you will prepare a draft they review before clicking Create. '
            .($form ? 'The user currently has a '.$form.' form open: they can ask you to change it ('.(in_array($form, ['quote', 'invoice'], true) ? 'add lines, change quantities or prices, apply a discount before VAT' : 'e.g. « mets la catégorie VIP », « change le prix à 200 € et TVA 20 % », « date de fin au 30 décembre »').'). ' : '')
            .AiAssistantPrompts::outputLanguageInstruction();
        $user = ($workspace !== '' ? "=== Workspace snapshot ===\n".Str::limit($workspace, 6000)."\n\n" : '')
            ."=== Conversation ===\n".$transcript."\n\nAnswer the last user message.";

        try {
            $result = $this->llm->completeFast($system, $user, 700, $company, false);
            $reply = trim((string) ($result['suggestion'] ?? ''));
        } catch (Throwable $e) {
            report($e);

            return [__('ai_sidebar_error'), ['type' => 'error'], null];
        }
        $this->usage->recordForTask($company, 'ai_sidebar_chat');

        return [$reply !== '' ? $reply : __('ai_sidebar_error'), ['type' => 'answer'], null];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{description: string, quantity: int, unit_minor: int, product_id: string}>
     */
    private function contextLines(array $context): array
    {
        $out = [];
        foreach (array_slice(is_array($context['lines'] ?? null) ? $context['lines'] : [], 0, 40) as $l) {
            if (! is_array($l)) {
                continue;
            }
            $out[] = [
                'description' => Str::limit(trim((string) ($l['description'] ?? '')), 300, ''),
                'quantity' => max(0, (int) ($l['quantity'] ?? 1)),
                'unit_minor' => max(0, (int) ($l['unit_minor'] ?? 0)),
                'product_id' => (string) ($l['product_id'] ?? ''),
                'vat_rate' => isset($l['vat_rate']) && is_numeric(str_replace(',', '.', (string) $l['vat_rate'])) ? (float) str_replace(',', '.', (string) $l['vat_rate']) : null,
            ];
        }

        return $out;
    }
}
