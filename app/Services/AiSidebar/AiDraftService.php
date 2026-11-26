<?php

namespace App\Services\AiSidebar;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\Project;
use App\Models\User;
use App\Services\AiAssistantPrompts;
use App\Services\PlatformLlmRouter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sidebar "Demander à l'IA": turns a short prompt into a create-form draft (quote, invoice, client,
 * product, project). Nothing is persisted except a short-lived cache entry; the user reviews and
 * clicks the normal Create button.
 */
class AiDraftService
{
    public const TTL_MINUTES = 30;

    public const ENTITIES = ['quote', 'invoice', 'client', 'product', 'project', 'task'];

    public function __construct(private PlatformLlmRouter $llm) {}

    public function llmAvailable(Company $company): bool
    {
        try {
            return $this->llm->isAvailable($company);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return array{token: string, draft: array<string, mixed>}
     */
    public function createDraft(Company $company, User $user, string $prompt, bool $useLlm): array
    {
        $intent = null;
        $source = 'rules';
        if ($useLlm) {
            try {
                $intent = $this->intentFromLlm($company, $prompt);
                $source = 'ai';
            } catch (Throwable $e) {
                report($e);
                $intent = null;
            }
        }
        $intent ??= $this->intentFromRules($prompt);

        $draft = $this->resolve($company, $intent);
        $draft['source'] = $source;
        $draft['prompt'] = mb_substr($prompt, 0, 2000);
        $draft['company_id'] = (string) $company->id;
        $draft['user_id'] = (string) $user->id;

        $token = Str::random(32);
        Cache::put($this->cacheKey($token), $draft, now()->addMinutes(self::TTL_MINUTES));

        return ['token' => $token, 'draft' => $draft];
    }

    /** @return array<string, mixed>|null */
    public function find(string $token, User $user): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            return null;
        }
        $draft = Cache::get($this->cacheKey($token));
        if (! is_array($draft)
            || (string) ($draft['company_id'] ?? '') !== (string) $user->company_id
            || (string) ($draft['user_id'] ?? '') !== (string) $user->id) {
            return null;
        }

        return $draft;
    }

    /** URL of the form that will live-fill this draft (a task opens its project's Tasks tab). */
    public function draftUrl(array $draft, string $token): string
    {
        if (($draft['entity'] ?? '') === 'task' && empty($draft['route_params'])) {
            return route('projects.index');
        }

        return route((string) $draft['route'], (array) ($draft['route_params'] ?? []) + ['ai_draft' => $token]);
    }

    public function targetRoute(string $entity): string
    {
        return match ($entity) {
            'invoice' => 'invoices.create',
            'client' => 'clients.create',
            'product' => 'commerce.products.create',
            'project' => 'projects.create',
            'task' => 'projects.show',
            default => 'proposals.create',
        };
    }

    private function cacheKey(string $token): string
    {
        return 'ai_sidebar_draft:'.$token;
    }

    // ------------------------------------------------------------------ intent extraction

    /** @return array<string, mixed> */
    private function intentFromLlm(Company $company, string $prompt): array
    {
        $catalog = Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)
            ->orderBy('name')->limit(80)->pluck('name')->map(fn ($n) => '- '.$n)->implode("\n");

        $system = <<<'PROMPT'
You turn a short staff request into a JSON draft for a business app (CRM, quotes, invoices). Respond with ONLY valid JSON, no markdown:
{"entity":"quote|invoice|client|product|project|task","client_name":"","title":"","notes":"","due_date":"YYYY-MM-DD or empty","valid_until":"YYYY-MM-DD or empty",
 "lines":[{"description":"","product_name":"","quantity":1,"unit_price":null}],
 "fields":{}}
Rules:
- entity: quote for devis/proposition/quote, invoice for facture/invoice, task for tâche/task (a task inside a project), else client, product (product or service), project.
- client_name: the customer named in the request (company or person), exactly as written; empty if none.
- lines (quote/invoice only): one per item requested. product_name: the EXACT catalog name when an item clearly matches the catalog below, else empty. quantity: positive integer. unit_price: number in major units only if the request gives a price, else null.
- fields for client: name, email, phone, company_name, contact_name, address_line1, address_postal_code, address_city, address_country.
- fields for product: name, kind (product|service), base_price, tax_rate, unit, description.
- fields for project: title, description, final_deadline (YYYY-MM-DD), negotiated_price.
- fields for task: project_name (the project the task belongs to, as written), title, description, starts_on, ends_on (YYYY-MM-DD, resolve relative dates like « vendredi », « demain », « fin du mois » from Today), assignee_name (person, as written), priority (low|medium|high|urgent or empty).
- Never invent prices, emails or phone numbers. Keep the user's language.
PROMPT;
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();
        $user = 'Today: '.now()->toDateString().' ('.now()->locale('en')->dayName.")\nCatalog:\n".($catalog !== '' ? $catalog : '(empty)')."\n\nRequest:\n".$prompt;

        $result = $this->llm->completeFast($system, $user, 900, $company, true);
        $raw = trim((string) ($result['suggestion'] ?? ''));
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new \RuntimeException('AI sidebar: invalid JSON.');
        }
        $entity = in_array($decoded['entity'] ?? null, self::ENTITIES, true) ? $decoded['entity'] : $this->detectEntity($prompt);

        return [
            'entity' => $entity,
            'client_name' => trim((string) ($decoded['client_name'] ?? '')),
            'title' => trim((string) ($decoded['title'] ?? '')),
            'notes' => trim((string) ($decoded['notes'] ?? '')),
            'due_date' => $this->validDate($decoded['due_date'] ?? null),
            'valid_until' => $this->validDate($decoded['valid_until'] ?? null),
            'lines' => collect(is_array($decoded['lines'] ?? null) ? $decoded['lines'] : [])->filter(fn ($l) => is_array($l))->map(fn (array $l) => [
                'description' => trim((string) ($l['description'] ?? '')),
                'product_name' => trim((string) ($l['product_name'] ?? '')),
                'quantity' => max(1, (int) ($l['quantity'] ?? 1)),
                'unit_price' => is_numeric($l['unit_price'] ?? null) ? (float) $l['unit_price'] : null,
            ])->take(15)->values()->all(),
            'fields' => is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [],
        ];
    }

    /**
     * Deterministic parser used when no AI provider is configured (and in tests).
     *
     * @return array<string, mixed>
     */
    public function intentFromRules(string $prompt): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $prompt) ?? $prompt);
        $entity = $this->detectEntity($text);
        $intent = ['entity' => $entity, 'client_name' => '', 'title' => '', 'notes' => '', 'due_date' => null, 'valid_until' => null, 'lines' => [], 'fields' => []];

        $stop = '(?=\s+(?:avec|with|comprenant|contenant|incluant|including|pour un montant|email|e-mail|mail|t[ée]l[ée]phone|tel|phone|à|a|au prix|prix|tva|vat|taxe)\b|\s*[:;,\n]|$)';
        if (in_array($entity, ['quote', 'invoice', 'project'], true)
            && preg_match('/\b(?:pour|for)\s+(?:le\s+client\s+|la\s+soci[ée]t[ée]\s+|l[\'’]entreprise\s+|client\s+)?(.+?)'.$stop.'/iu', $text, $m)) {
            $intent['client_name'] = trim($m[1], " \"'«»");
        } elseif (in_array($entity, ['quote', 'invoice', 'project'], true) && preg_match('/\bclient\s+(.+?)'.$stop.'/iu', $text, $m)) {
            $intent['client_name'] = trim($m[1], " \"'«»");
        }

        if (in_array($entity, ['quote', 'invoice'], true)) {
            $body = preg_match('/\b(?:avec|with|comprenant|contenant|incluant|including)\b\s*(.+)$/iu', $text, $m) ? $m[1]
                : (preg_match('/:\s*(.+)$/u', $text, $m2) ? $m2[1] : '');
            foreach (preg_split('/\s*(?:,|;|\+|\bet\b|\band\b)\s*/iu', (string) $body) ?: [] as $segment) {
                $line = $this->parseLineSegment($segment);
                if ($line !== null) {
                    $intent['lines'][] = $line;
                }
            }
            if (preg_match('/\b(?:intitul[ée]|titre|objet)\s*[:«"]?\s*([^»",;]+)/iu', $text, $m)) {
                $intent['title'] = trim($m[1]);
            }
        }

        if ($entity === 'client') {
            if (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $text, $m)) {
                $intent['fields']['email'] = $m[0];
            }
            if (preg_match('/\b(?:code\s+postal|cp|zip(?:\s*code)?|postal\s+code)\s*:?\s*([A-Z0-9][A-Z0-9 -]{2,9}[A-Z0-9])\b/iu', $text, $m)) {
                $intent['fields']['address_postal_code'] = strtoupper(trim($m[1]));
                $text = str_replace($m[0], ' ', $text);
            }
            if (preg_match('/(\+?\d[\d\s.]{6,}\d)/u', $text, $m)) {
                $intent['fields']['phone'] = trim($m[1]);
            }
            if (preg_match('/\bclient\s+(?:nomm[ée]\s+|appel[ée]\s+)?(.+?)'.$stop.'/iu', $text, $m)) {
                $intent['fields']['name'] = trim($m[1], " \"'«»");
            }
        }

        if ($entity === 'product') {
            if (preg_match('/\b(?:produit|service|prestation|article)\s+(?:nomm[ée]\s+|appel[ée]\s+)?(.+?)'.$stop.'/iu', $text, $m)) {
                $intent['fields']['name'] = trim($m[1], " \"'«»");
            }
            $intent['fields']['kind'] = preg_match('/\b(service|prestation)\b/iu', $text) ? 'service' : 'product';
            if (preg_match('/(\d[\d\s.,]*)\s*(?:€|eur|euros?|dt|tnd|\$|usd)/iu', $text, $m)) {
                $intent['fields']['base_price'] = $this->decimal($m[1]);
            }
            if (preg_match('/(?:tva|vat|taxe)\s*(?:de\s*)?(\d+(?:[.,]\d+)?)\s*%?/iu', $text, $m)) {
                $intent['fields']['tax_rate'] = $this->decimal($m[1]);
            }
        }

        if ($entity === 'task') {
            $intent['fields'] = $this->taskFieldsFromRules($text);
        }

        if ($entity === 'project') {
            if (preg_match('/\bprojet\s+(?:nomm[ée]\s+|appel[ée]\s+|intitul[ée]\s+)?(.+?)(?=\s+(?:pour|for|avec|with)\b|\s*[:;,]|$)/iu', $text, $m)) {
                $intent['fields']['title'] = trim($m[1], " \"'«»");
            }
        }

        return $intent;
    }

    /**
     * « crée une tâche pour le projet X : maquette page d'accueil, pour vendredi, assignée à Sami, urgente ».
     *
     * @return array<string, string>
     */
    public function taskFieldsFromRules(string $text, ?\Carbon\CarbonInterface $today = null): array
    {
        $f = [];
        $stop = '(?=\s*[:;,.]|\s+(?:pour|avant|d[\'’]ici|assign\w*|attribu\w*|affect\w*|avec|[ée]ch[ée]ance|priorit\w*|urgent\w*|due|by|from|à partir)\b|$)';
        if (preg_match('/\b(?:projet|project)\s+(?:nomm[ée]\s+|intitul[ée]\s+|appel[ée]\s+)?[«"“]?\s*(.+?)\s*[»"”]?'.$stop.'/iu', $text, $m)) {
            $f['project_name'] = trim($m[1], " \"'«»“”.");
        }

        $title = '';
        $cut = '/\s*[,;]\s*|\s+(?=(?:pour|avant|d[\'’]ici|assign\w*|attribu\w*|affect\w*|[ée]ch[ée]ance|priorit[ée]|urgent\w*|due|by|à partir)\b)/iu';
        if (preg_match('/[«"“]([^»"”]{2,190})[»"”]/u', $text, $m) && mb_stripos((string) ($f['project_name'] ?? ''), $m[1]) === false) {
            $title = $m[1];
        } elseif (preg_match('/:\s*(.+)$/u', $text, $m)) {
            $title = (string) (preg_split($cut, $m[1]) ?: [''])[0];
        } elseif (preg_match('/\b(?:t[âa]che|task)\s+(?:intitul[ée]e?\s+|nomm[ée]e?\s+|appel[ée]e?\s+)?(?!pour\b|sur\b|dans\b)(.+?)(?=\s+(?:pour|sur|dans|du|au)\s+(?:le\s+)?(?:projet|project)\b|$)/iu', $text, $m)) {
            $title = (string) (preg_split($cut, $m[1]) ?: [''])[0];
        }
        $title = trim(preg_replace('/^(?:de|d[\'’]|la|le|les|l[\'’]|une?|faire)\s+/iu', '', trim($title, " \"'«»“”.")) ?? $title);
        if ($title !== '') {
            $f['title'] = Str::ucfirst(Str::limit($title, 190, ''));
        }

        // Dates are read outside the title / project name (a title like « Bilan 30/06 » is not a deadline).
        $rest = ' '.$text.' ';
        foreach (array_filter([$f['title'] ?? null, $f['project_name'] ?? null]) as $chunk) {
            $rest = str_ireplace($chunk, ' ', $rest);
        }
        $dates = new AiDateParser;
        if (preg_match('/\b(?:à partir d[eu]|a partir d[eu]|d[ée]but(?:e|ant)?|commen\w+|starts?|from)\s+(?:le\s+|du\s+)?(.{2,40}?)(?=\s*[,;]|\s+(?:jusqu|et|pour|avant|assign\w*)\b|$)/iu', $rest, $m)) {
            if ($d = $dates->parse($m[1], $today)) {
                $f['starts_on'] = $d;
                $rest = str_replace($m[0], ' ', $rest);
            }
        }
        if ($d = $dates->parse($rest, $today)) {
            $f['ends_on'] = $d;
        }

        if (preg_match('/\b(?:assign[ée]e?s?|attribu[ée]e?s?|affect[ée]e?s?|confi[ée]e?s?|assigned|assign it)\s+(?:à|a|au|to)\s+(.+?)(?=\s*[,;.]|\s+(?:pour|avant|en tant|comme|priorit\w*|urgent\w*|d[\'’]ici|et\s+(?:pour|avant|urgent\w*|priorit\w*|d[\'’]ici))\b|$)/iu', $text, $m)) {
            $f['assignee_name'] = trim($m[1], " \"'«»“”");
        }
        if (preg_match('/\b(?:en tant qu[e\'’]\s*|comme\s+|as\s+)?(?:[ée]diteur|editor|r[ée]dacteur)\b/iu', $text)) {
            $f['assignee_role'] = 'editor';
        }

        $p = Str::lower(Str::ascii($text));
        $f['priority'] = match (true) {
            (bool) preg_match('/\b(urgent|urgente|asap|tres urgent)\b/', $p) => 'urgent',
            (bool) preg_match('/\b(priorite (haute|elevee|forte)|haute priorite|high priority|prioritaire|important)\b/', $p) => 'high',
            (bool) preg_match('/\b(priorite (basse|faible)|basse priorite|low priority|pas urgent)\b/', $p) => 'low',
            (bool) preg_match('/\b(priorite (moyenne|normale)|medium priority)\b/', $p) => 'medium',
            default => '',
        };
        if ($f['priority'] === '') {
            unset($f['priority']);
        }

        return $f;
    }

    /** @return array<string, mixed> */
    private function resolveTask(Company $company, array $intent): array
    {
        $f = (array) ($intent['fields'] ?? []);
        $unresolved = [];
        $fields = [];
        $dates = new AiDateParser;

        $projectQuery = trim((string) ($f['project_name'] ?? ''));
        $project = null;
        if ($projectQuery !== '') {
            $project = $this->matchProject($company, $projectQuery);
            if (! $project) {
                $unresolved[] = ['type' => 'project', 'label' => __('ai_draft_unresolved_project', ['name' => $projectQuery]), 'value' => $projectQuery];
            }
        } else {
            $unresolved[] = ['type' => 'project_missing', 'label' => __('ai_draft_unresolved_project_missing'), 'value' => ''];
        }

        $title = trim((string) ($f['title'] ?? ($intent['title'] ?? '')));
        if ($title !== '') {
            $fields['title'] = Str::limit($title, 190, '');
        } else {
            $unresolved[] = ['type' => 'field', 'label' => __('ai_draft_unresolved_title'), 'value' => ''];
        }
        if (trim((string) ($f['description'] ?? '')) !== '') {
            $fields['description'] = Str::limit(trim((string) $f['description']), 5000, '');
        }
        foreach (['starts_on', 'ends_on'] as $key) {
            $v = trim((string) ($f[$key] ?? ''));
            $date = $this->validDate($v) ?? ($v !== '' ? $dates->parse($v) : null);
            if ($date) {
                $fields[$key] = $date;
            }
        }
        if (in_array($f['priority'] ?? null, ['low', 'medium', 'high', 'urgent'], true)) {
            $fields['priority'] = $f['priority'];
        }

        $assignees = [];
        $assigneeQuery = trim((string) ($f['assignee_name'] ?? ''));
        if ($assigneeQuery !== '') {
            foreach (preg_split('/\s*(?:,|&|\bet\b|\band\b)\s*/iu', $assigneeQuery) ?: [] as $q) {
                $q = trim($q);
                if ($q === '') {
                    continue;
                }
                $member = $this->matchStaff($company, $q);
                if ($member) {
                    $assignees[] = ['id' => (string) $member->id, 'name' => (string) $member->name, 'role' => ($f['assignee_role'] ?? '') === 'editor' ? 'editor' : null];
                } else {
                    $unresolved[] = ['type' => 'assignee', 'label' => __('ai_draft_unresolved_assignee', ['name' => $q]), 'value' => $q];
                }
            }
        }

        return [
            'entity' => 'task',
            'route' => 'projects.show',
            'route_params' => $project ? ['project' => (string) $project->id, 'tab' => 'tasks'] : null,
            'form_selector' => 'form[action$="/tasks"]',
            'project' => $project ? ['id' => (string) $project->id, 'title' => (string) $project->title] : ($projectQuery !== '' ? ['id' => null, 'title' => $projectQuery] : null),
            'client' => null,
            'fields' => $fields,
            'assignees' => $assignees,
            'lines' => [],
            'currency' => strtoupper((string) ($company->default_currency ?? flowdesk_default_currency())),
            'unresolved' => $unresolved,
        ];
    }

    public function matchProject(Company $company, string $query): ?\App\Models\Project
    {
        $best = null;
        $bestScore = 0.0;
        foreach (\App\Models\Project::query()->withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->latest('updated_at')->limit(500)->get(['id', 'title']) as $project) {
            $score = self::similarity($query, (string) $project->title);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $project;
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    public function matchStaff(Company $company, string $query): ?User
    {
        $best = null;
        $bestScore = 0.0;
        foreach (User::query()->where('company_id', $company->id)->workspaceStaff()->get(['id', 'name', 'email']) as $member) {
            foreach ([(string) $member->name, (string) Str::before((string) $member->email, '@')] as $candidate) {
                $score = $candidate !== '' ? self::similarity($query, $candidate) : 0.0;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $member;
                }
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    private function detectEntity(string $text): string
    {
        if (preg_match('/\b(t[âa]ches?|tasks?|to-?do)\b/iu', $text) && ! preg_match('/\b(devis|quote|facture|invoice)\b/iu', $text)) {
            return 'task';
        }
        $map = [
            'quote' => '/\b(devis|quote|proposition commerciale|proposal|offre)\b/iu',
            'invoice' => '/\b(facture|invoice|facturer)\b/iu',
            'project' => '/\b(projet|project)\b/iu',
            'product' => '/\b(produit|product|service|prestation|article)\b/iu',
            'client' => '/\b(client|customer|contact)\b/iu',
        ];
        $best = 'quote';
        $bestPos = PHP_INT_MAX;
        foreach ($map as $entity => $re) {
            if (preg_match($re, $text, $m, PREG_OFFSET_CAPTURE) && $m[0][1] < $bestPos) {
                $best = $entity;
                $bestPos = $m[0][1];
            }
        }

        return $best;
    }

    /** @return array{description: string, product_name: string, quantity: int, unit_price: ?float}|null */
    private function parseLineSegment(string $segment): ?array
    {
        $s = trim($segment, " .");
        if ($s === '') {
            return null;
        }
        $price = null;
        if (preg_match('/\s*(?:à|a|@|at|au prix de|pour|:)?\s*(\d[\d\s.,]*)\s*(?:€|eur\b|euros?\b|dt\b|tnd\b|\$|usd\b)(?:\s*(?:ht|ttc|l[\'’]unit[ée]|chacun|chacune|\/\s*\w+))*\s*$/iu', $s, $m, PREG_OFFSET_CAPTURE)) {
            $price = (float) $this->decimal($m[1][0]);
            $s = trim(mb_substr($s, 0, mb_strlen(substr($s, 0, $m[0][1]))));
        } elseif (preg_match('/^\s*(?:€|\$)\s*(\d[\d\s.,]*)/u', $s, $m)) {
            $price = (float) $this->decimal($m[1]);
        }
        $qty = 1;
        if (preg_match('/^(\d{1,4})\s*(?:x|×|fois)?\s+(.+)$/iu', $s, $m)) {
            $qty = max(1, (int) $m[1]);
            $s = $m[2];
        } elseif (preg_match('/^(?:un|une|a|an|one)\s+(.+)$/iu', $s, $m)) {
            $s = $m[1];
        }
        $s = trim(preg_replace('/^(?:des|de|du|d[\'’]|le|la|les|l[\'’])\s*/iu', '', $s) ?? $s);
        if ($s === '' || mb_strlen($s) < 2) {
            return null;
        }

        return ['description' => Str::ucfirst($s), 'product_name' => '', 'quantity' => $qty, 'unit_price' => $price];
    }

    private function decimal(string $raw): string
    {
        $s = preg_replace('/[^\d,.]/', '', $raw) ?? '';
        if (str_contains($s, ',') && str_contains($s, '.')) {
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }

        return is_numeric($s) ? $s : '0';
    }

    private function validDate(mixed $value): ?string
    {
        $s = trim((string) ($value ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : null;
    }

    // ------------------------------------------------------------------ resolution

    /**
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    public function resolve(Company $company, array $intent): array
    {
        $entity = in_array($intent['entity'] ?? null, self::ENTITIES, true) ? $intent['entity'] : 'quote';
        if ($entity === 'task') {
            return $this->resolveTask($company, $intent);
        }
        $currency = strtoupper((string) ($company->default_currency ?? flowdesk_default_currency()));
        $unresolved = [];
        $fields = [];

        $client = null;
        $clientQuery = trim((string) ($intent['client_name'] ?? ''));
        if ($clientQuery !== '' && in_array($entity, ['quote', 'invoice', 'project'], true)) {
            $client = $this->matchClient($company, $clientQuery);
            if (! $client) {
                $unresolved[] = ['type' => 'client', 'label' => __('ai_draft_unresolved_client', ['name' => $clientQuery]), 'value' => $clientQuery];
            }
        } elseif (in_array($entity, ['quote', 'invoice'], true)) {
            $unresolved[] = ['type' => 'client_missing', 'label' => __('ai_draft_unresolved_client_missing'), 'value' => ''];
        }

        $lines = [];
        if (in_array($entity, ['quote', 'invoice'], true)) {
            $products = Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->where('is_active', true)->with('variants')->get();
            $matched = [];
            foreach ((array) ($intent['lines'] ?? []) as $line) {
                $query = trim((string) (($line['product_name'] ?? '') !== '' ? $line['product_name'] : ($line['description'] ?? '')));
                $product = $query !== '' ? $this->matchProduct($products, $query, (string) ($line['description'] ?? '')) : null;
                $matched[] = [$line, $product];
            }
            $currencies = collect($matched)->map(fn ($m) => $m[1]?->currency)->filter()->unique()->values();
            if ($currencies->count() === 1) {
                $currency = strtoupper((string) $currencies->first());
            }
            foreach ($matched as [$line, $product]) {
                $qty = max(1, (int) ($line['quantity'] ?? 1));
                $given = isset($line['unit_price']) && $line['unit_price'] !== null ? (float) $line['unit_price'] : null;
                if ($product) {
                    $variant = $product->variants->first();
                    $minor = $given !== null
                        ? flowdesk_decimal_to_minor((string) $given, $currency)
                        : ($variant ? $variant->unitPriceMinor() : (int) $product->base_price_minor);
                    $lines[] = [
                        'description' => (string) $product->name,
                        'detail' => (string) ($product->description ?? ''),
                        'quantity' => $qty,
                        'unit_minor' => (int) $minor,
                        'product_id' => (string) $product->id,
                        'product_variant_id' => $variant?->id ? (string) $variant->id : null,
                        'unit' => \App\Support\CatalogUnits::label($product->unit) ?: (string) ($product->unit ?? ''),
                        'tax_label' => $product->taxLabel(),
                        'vat_rate' => $product->tax_rate !== null ? (float) $product->tax_rate : null,
                        'matched' => true,
                    ];
                } else {
                    $desc = trim((string) ($line['description'] ?? '')) ?: (string) ($line['product_name'] ?? '');
                    if ($desc === '') {
                        continue;
                    }
                    $lines[] = [
                        'description' => $desc,
                        'detail' => '',
                        'quantity' => $qty,
                        'unit_minor' => $given !== null ? flowdesk_decimal_to_minor((string) $given, $currency) : 0,
                        'product_id' => null,
                        'product_variant_id' => null,
                        'unit' => '',
                        'tax_label' => '',
                        'vat_rate' => null,
                        'matched' => false,
                    ];
                    $unresolved[] = ['type' => 'product', 'label' => __('ai_draft_unresolved_product', ['name' => $desc]), 'value' => $desc];
                }
            }
            if ($lines === []) {
                $unresolved[] = ['type' => 'lines', 'label' => __('ai_draft_unresolved_lines'), 'value' => ''];
            }
            $fields['currency'] = $currency;
            if ($entity === 'quote') {
                $title = trim((string) ($intent['title'] ?? ''));
                $fields['name'] = $title !== '' ? $title : __('ai_draft_quote_title', ['client' => $client?->name ?? ($clientQuery !== '' ? $clientQuery : '…')]);
                if (! empty($intent['valid_until'])) {
                    $fields['valid_until'] = $intent['valid_until'];
                }
            } elseif (! empty($intent['due_date'])) {
                $fields['due_date'] = $intent['due_date'];
            }
            if (trim((string) ($intent['notes'] ?? '')) !== '') {
                $fields['customer_notes'] = trim((string) $intent['notes']);
            }
        }

        $allowed = match ($entity) {
            'client' => ['name', 'email', 'phone', 'company_name', 'contact_name', 'address_line1', 'address_postal_code', 'address_city', 'address_country'],
            'product' => ['name', 'kind', 'base_price', 'tax_rate', 'unit', 'description'],
            'project' => ['title', 'description', 'final_deadline', 'negotiated_price'],
            default => [],
        };
        foreach ((array) ($intent['fields'] ?? []) as $key => $value) {
            if (in_array($key, $allowed, true) && is_scalar($value) && trim((string) $value) !== '') {
                $fields[$key] = mb_substr(trim((string) $value), 0, 2000);
            }
        }
        if ($entity === 'product' && isset($fields['kind']) && ! in_array($fields['kind'], ['product', 'service'], true)) {
            unset($fields['kind']);
        }
        if ($entity === 'client' && empty($fields['name'])) {
            $unresolved[] = ['type' => 'field', 'label' => __('ai_draft_unresolved_name'), 'value' => ''];
        }
        if ($entity === 'project' && empty($fields['title'])) {
            $fields['title'] = trim((string) ($intent['title'] ?? ''));
            if ($fields['title'] === '') {
                unset($fields['title']);
                $unresolved[] = ['type' => 'field', 'label' => __('ai_draft_unresolved_title'), 'value' => ''];
            }
        }

        return [
            'entity' => $entity,
            'route' => $this->targetRoute($entity),
            'client' => $client ? ['id' => (string) $client->id, 'name' => (string) $client->name, 'query' => $clientQuery, 'matched' => true]
                : ($clientQuery !== '' ? ['id' => null, 'name' => $clientQuery, 'query' => $clientQuery, 'matched' => false] : null),
            'fields' => $fields,
            'lines' => $lines,
            'currency' => $currency,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * Split a free-text list ("2 sessions de mentoring, un audit à 390 €") into line intents.
     *
     * @return list<array{description: string, product_name: string, quantity: int, unit_price: ?float}>
     */
    public function parseLineList(string $body): array
    {
        $out = [];
        foreach (preg_split('/\s*(?:,|;|\+|\bet\b|\band\b)\s*/iu', $body) ?: [] as $segment) {
            $line = $this->parseLineSegment($segment);
            if ($line !== null) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /**
     * Resolve line intents against the tenant catalog (same rules as a full draft).
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{lines: list<array<string, mixed>>, unresolved: list<array<string, string>>, currency: string}
     */
    public function resolveLines(Company $company, array $lines): array
    {
        $r = $this->resolve($company, ['entity' => 'quote', 'client_name' => '', 'lines' => $lines]);

        return [
            'lines' => $r['lines'],
            'unresolved' => array_values(array_filter($r['unresolved'], fn ($u) => ($u['type'] ?? '') === 'product')),
            'currency' => (string) $r['currency'],
        ];
    }

    public function parseAmount(string $raw): float
    {
        return (float) $this->decimal($raw);
    }

    public static function normalize(string $s): string
    {
        $s = Str::lower(Str::ascii($s));
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s) ?? $s;

        return trim(preg_replace('/\s+/', ' ', $s) ?? $s);
    }

    /** @return list<string> */
    private static function tokens(string $s): array
    {
        $stop = ['les', 'des', 'une', 'pour', 'avec', 'the', 'and', 'sur', 'par', 'aux', 'sarl', 'suarl', 'sas', 'ste', 'societe', 'client'];

        return array_values(array_unique(array_filter(
            array_map(fn ($t) => strlen($t) > 4 && str_ends_with($t, 's') ? substr($t, 0, -1) : $t, explode(' ', self::normalize($s))),
            fn ($t) => strlen($t) >= 3 && ! in_array($t, $stop, true)
        )));
    }

    public static function similarity(string $query, string $candidate): float
    {
        $q = self::normalize($query);
        $c = self::normalize($candidate);
        if ($q === '' || $c === '') {
            return 0.0;
        }
        if ($q === $c) {
            return 100.0;
        }
        if (strlen($q) >= 3 && (str_contains($c, $q) || str_contains($q, $c) && strlen($c) >= 4)) {
            return 90.0;
        }
        similar_text($q, $c, $pct);
        $qt = self::tokens($q);
        $ct = self::tokens($c);
        $overlap = 0.0;
        if ($qt !== [] && $ct !== []) {
            $common = count(array_intersect($qt, $ct));
            $overlap = 100.0 * $common / min(count($qt), count($ct));
        }

        return max((float) $pct, $overlap * 0.85);
    }

    public function matchClient(Company $company, string $query): ?Client
    {
        $best = null;
        $bestScore = 0.0;
        $clients = Client::query()->withoutGlobalScopes()->where('company_id', $company->id)->get(['id', 'name', 'company_name', 'contact_name', 'email']);
        foreach ($clients as $client) {
            foreach ([$client->name, $client->company_name, $client->contact_name] as $candidate) {
                if (! is_string($candidate) || $candidate === '') {
                    continue;
                }
                $score = self::similarity($query, $candidate);
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = $client;
                }
            }
        }

        return $bestScore >= 72.0 ? $best : null;
    }

    /** @param  \Illuminate\Support\Collection<int, Product>  $products */
    private function matchProduct($products, string $query, string $description): ?Product
    {
        $scores = [];
        foreach ($products as $product) {
            $scores[(string) $product->id] = max(self::similarity($query, (string) $product->name), $description !== '' ? self::similarity($description, (string) $product->name) : 0);
        }
        if ($scores === []) {
            return null;
        }
        arsort($scores);
        $ids = array_keys($scores);
        $top = $scores[$ids[0]];
        $second = isset($ids[1]) ? $scores[$ids[1]] : 0;
        if ($top >= 72.0 || ($top >= 60.0 && $top - $second >= 15.0)) {
            return $products->firstWhere('id', $ids[0]);
        }

        return null;
    }
}
