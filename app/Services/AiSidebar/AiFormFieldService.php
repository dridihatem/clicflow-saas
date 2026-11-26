<?php

namespace App\Services\AiSidebar;

use App\Models\Company;
use App\Services\AiAssistantPrompts;
use App\Services\PlatformLlmRouter;
use Illuminate\Support\Str;

/**
 * Chat edits of the open client / product / project / task form (« mets la catégorie VIP »,
 * « change le prix à 200 € et TVA 20 % », « date de fin au 30 décembre »). Returns field ops the
 * browser types into the form; nothing is saved server-side.
 */
class AiFormFieldService
{
    public const FORMS = ['client', 'product', 'project', 'task'];

    /** Editable fields per form. */
    public const FIELDS = [
        'client' => ['name', 'email', 'phone', 'company_name', 'contact_name', 'category', 'status', 'address_line1', 'address_postal_code', 'address_city', 'address_country', 'source', 'notes'],
        'product' => ['name', 'kind', 'base_price', 'currency', 'tax_rate', 'tax_rate_2', 'unit', 'category', 'description', 'sku'],
        'project' => ['title', 'description', 'client_id', 'final_deadline', 'negotiated_price', 'final_price', 'status', 'source'],
        'task' => ['title', 'description', 'status', 'priority', 'starts_on', 'ends_on', 'assignee'],
    ];

    public function __construct(private AiDraftService $drafts, private PlatformLlmRouter $llm) {}

    /**
     * Label regex (accent-free, lowercase) => field, longest first.
     *
     * @return array<string, string>
     */
    public function labels(string $form): array
    {
        $map = match ($form) {
            'client' => [
                'adresse e ?mail|adresse mail|e ?mail|courriel|mail' => 'email',
                'telephone|tel|phone|portable|mobile|numero' => 'phone',
                'raison sociale|societe|entreprise|company' => 'company_name',
                'personne de contact|interlocuteur|contact' => 'contact_name',
                'categorie|category|segment' => 'category',
                'statut|status|etat' => 'status',
                'code postal|cp|zip|postal code' => 'address_postal_code',
                'ville|city' => 'address_city',
                'pays|country' => 'address_country',
                'adresse|address|rue' => 'address_line1',
                'source|origine|provenance' => 'source',
                'notes?|remarques?|commentaires?' => 'notes',
                'nom|name' => 'name',
            ],
            'product' => [
                'tva 2|taxe 2|seconde taxe|tax 2' => 'tax_rate_2',
                'tva|vat|taxe|tax' => 'tax_rate',
                'prix de base|prix unitaire|prix|tarif|price|montant' => 'base_price',
                'devise|monnaie|currency' => 'currency',
                'unite|unit' => 'unit',
                'categorie|category|famille' => 'category',
                'reference|ref|sku|code' => 'sku',
                'type|kind|nature' => 'kind',
                'description|descriptif' => 'description',
                'nom|name|libelle' => 'name',
            ],
            'project' => [
                'date de fin|date limite|date de livraison|echeance|deadline|fin|livraison' => 'final_deadline',
                'prix final|montant final|final price' => 'final_price',
                'prix negocie|budget|prix|tarif|montant|price' => 'negotiated_price',
                'statut|status|etat' => 'status',
                'client' => 'client_id',
                'source|origine' => 'source',
                'description|descriptif' => 'description',
                'titre|title|nom|intitule' => 'title',
            ],
            'task' => [
                'date de debut|debut|start' => 'starts_on',
                'date de fin|date limite|echeance|deadline|fin|due' => 'ends_on',
                'priorite|priority' => 'priority',
                'statut|status|etat|colonne' => 'status',
                'assignee|assigne|assignation|responsable|assign' => 'assignee',
                'description|descriptif' => 'description',
                'titre|title|nom|intitule' => 'title',
            ],
            default => [],
        };

        return $map;
    }

    /** Does the message mention a field of this form (« date de fin au 30 décembre » has no verb)? */
    public function mentionsField(string $message, string $form): bool
    {
        $t = self::fold($message);
        foreach (array_keys($this->labels($form)) as $re) {
            if (preg_match('/\b(?:'.$re.')\b/u', $t)) {
                return true;
            }
        }

        return (bool) preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $message);
    }

    /** Accent-tolerant version of an accent-free regex fragment (e → [eéèêë], …). */
    private static function tol(string $re): string
    {
        return strtr($re, [
            'a' => '[aàâä]', 'e' => '[eéèêë]', 'i' => '[iîï]', 'o' => '[oôö]', 'u' => '[uùûü]', 'c' => '[cç]', "'" => "['’]", ' ' => '[\s\-]',
        ]);
    }

    /** @return list<array{field: string, value: string}> */
    public function intentsFromRules(string $message, string $form): array
    {
        $labels = $this->labels($form);
        if ($labels === []) {
            return [];
        }
        $tolLabels = [];
        foreach ($labels as $re => $f) {
            $tolLabels[self::tol($re)] = $f;
        }
        $alt = implode('|', array_keys($tolLabels));
        $verbs = self::tol('mets|met|mettre|change|changer|changez|modifie|modifier|passe|passer|ajoute|ajouter|rajoute|definis|definir|fixe|fixer|regle|remplace|remplacer|indique|renseigne|set|update|add|utilise');
        $art = self::tol("(?:le|la|les|l'|un|une|son|sa|ses|du|de la|des|leur|the|a)");
        $text = trim(preg_replace('/\s+/u', ' ', $message) ?? $message);
        // Split on , ; and on « et » when it introduces another field.
        $segments = preg_split("/\\s*[,;]\\s*|\\s+(?:et|and|puis)\\s+(?=(?:(?:{$verbs})\\s+)?(?:{$art}\\s*)?(?:{$alt})\\b)/iu", $text) ?: [];

        $out = [];
        foreach ($segments as $seg) {
            $raw = trim($seg, ' .');
            $s = preg_replace("/^(?:{$verbs})\\s+/iu", '', $raw) ?? $raw;
            $s = preg_replace("/^{$art}(?:\\s+|(?<=['’]))/iu", '', $s) ?? $s;
            $joiner = self::tol("(?:(?:du client|du produit|du projet|de la tache)\\s*)?(?:(?:aux|au|a|en|par|sur|pour|est|sera|de|du|devient)(?=\\s)|d'|:|=|->)?");
            if (preg_match("/^({$alt})\\b\\s*{$joiner}\\s*(.+)$/iu", $s, $m)) {
                $field = null;
                foreach ($tolLabels as $re => $f) {
                    if (preg_match('/^(?:'.$re.')$/iu', $m[1])) {
                        $field = $f;
                        break;
                    }
                }
                if ($field !== null) {
                    $out[] = ['field' => $field, 'value' => trim($m[2], " \"'«»“”.")];

                    continue;
                }
            }
            if ($form === 'client' && preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $raw, $m)) {
                $out[] = ['field' => 'email', 'value' => $m[0]];
            } elseif ($form === 'client' && preg_match('/(\+?\d[\d\s.]{6,}\d)/u', $raw, $m)) {
                $out[] = ['field' => 'phone', 'value' => trim($m[1])];
            } elseif ($form === 'task' && preg_match('/\b(?:assign\w*|attribu\w*|affect\w*)\s+(?:a|à|au|to)\s+(.+)$/iu', $raw, $m)) {
                $out[] = ['field' => 'assignee', 'value' => trim($m[1], ' .')];
            } elseif ($form === 'task' && preg_match('/\burgent/iu', $raw)) {
                $out[] = ['field' => 'priority', 'value' => 'urgent'];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $current
     * @return list<array{field: string, value: string}>
     */
    public function intentsFromLlm(Company $company, string $message, string $form, array $current): array
    {
        $fields = implode(', ', self::FIELDS[$form] ?? []);
        $now = collect($current)->map(fn ($v, $k) => $k.' = '.Str::limit((string) $v, 80))->implode("\n");
        $system = 'You edit fields of a '.$form.' form open on screen in a business app. Respond with ONLY valid JSON, no markdown: '
            .'{"fields":[{"field":"<name>","value":"<new value>"}]}. Allowed field names: '.$fields.'. '
            .'Dates as YYYY-MM-DD (resolve relative French dates from Today). Prices and tax rates as plain numbers. '
            .'status/priority/kind: use the words the user gave. client_id / assignee: the person or company name as written. '
            .'Return {"fields":[]} if the request is not an edit of this form. Never invent emails, phones or prices.';
        $system .= "\n\n".AiAssistantPrompts::outputLanguageInstruction();
        $user = 'Today: '.now()->toDateString().' ('.now()->locale('en')->dayName.")\nCurrent values:\n".($now !== '' ? $now : '(empty)')."\n\nRequest:\n".$message;

        $result = $this->llm->completeFast($system, $user, 500, $company, true);
        $raw = trim((string) ($result['suggestion'] ?? ''));
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $raw, $m)) {
            $raw = trim($m[1]);
        }
        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! is_array($decoded['fields'] ?? null)) {
            throw new \RuntimeException('AI chat: invalid JSON.');
        }

        return collect($decoded['fields'])->filter(fn ($f) => is_array($f) && is_string($f['field'] ?? null) && is_scalar($f['value'] ?? null))
            ->map(fn ($f) => ['field' => (string) $f['field'], 'value' => trim((string) $f['value'])])
            ->take(12)->values()->all();
    }

    /**
     * Validate + normalize intents into browser ops.
     *
     * @param  list<array{field: string, value: string}>  $intents
     * @return array{0: list<array<string, mixed>>, 1: list<string>, 2: list<string>}
     */
    public function resolve(Company $company, string $form, array $intents): array
    {
        $ops = [];
        $summary = [];
        $issues = [];
        $allowed = self::FIELDS[$form] ?? [];
        $dates = new AiDateParser;
        foreach ($intents as $intent) {
            $field = $intent['field'];
            $value = trim((string) $intent['value']);
            if (! in_array($field, $allowed, true) || $value === '') {
                continue;
            }
            $display = $value;
            $set = $value;
            switch ($field) {
                case 'base_price':
                case 'negotiated_price':
                case 'final_price':
                    if (! preg_match('/\d/', $value)) {
                        $set = null;
                        break;
                    }
                    $set = rtrim(rtrim(number_format($this->drafts->parseAmount($value), 3, '.', ''), '0'), '.');
                    $display = str_replace('.', ',', $set);
                    break;
                case 'tax_rate':
                case 'tax_rate_2':
                    $n = preg_match('/(\d+(?:[.,]\d+)?)/', $value, $m) ? (float) str_replace(',', '.', $m[1]) : null;
                    $set = $n !== null && $n >= 0 && $n <= 100 ? rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') : null;
                    $display = $set !== null ? str_replace('.', ',', $set).' %' : $value;
                    break;
                case 'final_deadline':
                case 'starts_on':
                case 'ends_on':
                    $set = preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : $dates->parse($value);
                    $display = $set ? \Carbon\Carbon::parse($set)->locale(app()->getLocale())->isoFormat('D MMMM YYYY') : $value;
                    break;
                case 'email':
                    $set = filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : (preg_match('/[\w.+-]+@[\w-]+\.[\w.-]+/u', $value, $m) ? $m[0] : null);
                    break;
                case 'phone':
                    $set = preg_match('/(\+?\d[\d\s.\-()]{5,}\d)/u', $value, $m) ? trim($m[1]) : null;
                    break;
                case 'status':
                    $set = $this->status($form, $value);
                    break;
                case 'priority':
                    $set = $this->priority($value);
                    break;
                case 'kind':
                    $f = self::fold($value);
                    $set = preg_match('/service|prestation/', $f) ? 'service' : (preg_match('/produit|product|article|bien/', $f) ? 'product' : null);
                    break;
                case 'currency':
                    $f = self::fold($value);
                    $set = match (true) {
                        str_contains($value, '€') || (bool) preg_match('/\beur|euro/', $f) => 'EUR',
                        (bool) preg_match('/\btnd\b|dinar|\bdt\b/', $f) => 'TND',
                        str_contains($value, '$') || (bool) preg_match('/\busd\b|dollar/', $f) => 'USD',
                        (bool) preg_match('/^[a-z]{3}$/', $f) => strtoupper($f),
                        default => null,
                    };
                    break;
                case 'client_id':
                    $client = $this->drafts->matchClient($company, $value);
                    $set = $client ? (string) $client->id : null;
                    $display = $client?->name ?? $value;
                    if (! $client) {
                        $issues[] = __('ai_draft_unresolved_client', ['name' => $value]);

                        continue 2;
                    }
                    break;
                case 'assignee':
                    foreach (preg_split('/\s*(?:,|&|\bet\b|\band\b)\s*/iu', $value) ?: [] as $q) {
                        $member = trim($q) !== '' ? $this->drafts->matchStaff($company, trim($q)) : null;
                        if ($member) {
                            $ops[] = ['op' => 'assignee', 'id' => (string) $member->id, 'role' => null];
                            $summary[] = __('ai_chat_op_assignee', ['name' => $member->name]);
                        } elseif (trim($q) !== '') {
                            $issues[] = __('ai_draft_unresolved_assignee', ['name' => trim($q)]);
                        }
                    }

                    continue 2;
                default:
                    $set = Str::limit($value, in_array($field, ['description', 'notes'], true) ? 5000 : 190, '');
            }
            if ($set === null || $set === '') {
                $issues[] = __('ai_chat_field_invalid', ['field' => __('ai_chat_field_'.$field), 'value' => $value]);

                continue;
            }
            $ops[] = ['op' => 'field', 'name' => $field, 'value' => $set];
            $summary[] = __('ai_chat_op_field', ['field' => __('ai_chat_field_'.$field), 'value' => $display]);
        }

        return [$ops, $summary, $issues];
    }

    private function status(string $form, string $value): ?string
    {
        $f = self::fold($value);
        $map = match ($form) {
            'client' => ['inactif|inactive|archive' => 'inactive', 'prospect|potentiel|potential|lead' => 'potential', 'actif|active|client' => 'active'],
            'project' => ['brouillon|draft' => 'draft', 'en attente|attente|pending' => 'pending', 'approuve|valide|accepte|approved' => 'approved', 'en cours|cours|demarre|in progress|commence' => 'in_progress', 'termine|fini|livre|complete|clos' => 'completed'],
            'task' => ['a faire|todo|to do' => 'todo', 'en cours|cours|in progress|demarre' => 'in_progress', 'revue|relecture|review|validation' => 'review', 'termine|fini|fait|done' => 'done'],
            default => [],
        };
        foreach ($map as $re => $v) {
            if (preg_match('/\b(?:'.$re.')\b/', $f)) {
                return $v;
            }
        }

        return null;
    }

    private function priority(string $value): ?string
    {
        $f = self::fold($value);

        return match (true) {
            (bool) preg_match('/urgent|asap|critique/', $f) => 'urgent',
            (bool) preg_match('/haute|elevee|forte|high|importante?/', $f) => 'high',
            (bool) preg_match('/basse|faible|low/', $f) => 'low',
            (bool) preg_match('/moyenne|normale|medium|normal/', $f) => 'medium',
            default => null,
        };
    }

    public static function fold(string $s): string
    {
        return str_replace(['’', '-'], ["'", ' '], Str::lower(Str::ascii($s)));
    }
}
