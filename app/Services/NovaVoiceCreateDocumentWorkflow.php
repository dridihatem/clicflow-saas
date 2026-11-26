<?php

namespace App\Services;

use App\Enums\ClientSource;
use App\Enums\ClientStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Conversational Nova flow to create a quote or invoice without a project:
 * client name/company → match list or create new → work description →
 * ask generate line items? → (yes: AI lines / no: description only) → confirm → create → ask send.
 */
class NovaVoiceCreateDocumentWorkflow
{
    public function __construct(
        private ClientCodeService $clientCodes,
        private ProposalQuoteLinesAiService $quoteLinesAi,
        private InvoiceLineItemsAiService $invoiceLinesAi,
        private InvoiceTotalsService $totals,
        private ProposalReferenceService $proposalRefs,
        private InvoiceReferenceService $invoiceRefs,
        private ProposalMailService $proposalMail,
        private InvoiceMailService $invoiceMail,
        private PlanLimitService $planLimits,
        private AiCreditUsageService $usage,
    ) {}

    /**
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    public function start(User $user, string $kind): array
    {
        $kind = $kind === 'invoice' ? 'invoice' : 'quote';

        return [
            'active' => true,
            'workflow' => $kind === 'invoice' ? 'create_invoice' : 'create_quote',
            'reply' => __('nova_workflow_create_doc_ask_name', ['doc' => $this->docLabel($kind)]),
            'done' => false,
            'redirect_url' => null,
            'step' => 'collect_name',
            'data' => ['kind' => $kind],
            'result' => null,
        ];
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>, result?: ?array}
     */
    public function advance(User $user, array $session, string $input): array
    {
        $step = (string) ($session['step'] ?? 'collect_name');
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];
        $kind = (($data['kind'] ?? '') === 'invoice') ? 'invoice' : 'quote';
        $data['kind'] = $kind;
        $workflow = $kind === 'invoice' ? 'create_invoice' : 'create_quote';

        if ($step === 'collect_name' && empty($data['name']) && empty($data['client_id'])) {
            $data = $this->mergeOpening($data, $input);
            if (! empty($data['name'])) {
                return $this->resolveNameToClientOrAsk($user, $workflow, $data);
            }
        }

        return match ($step) {
            'collect_name' => $this->afterName($user, $workflow, $data, $input),
            'select_client' => $this->afterSelectClient($user, $workflow, $data, $input),
            'collect_email' => $this->afterEmail($user, $workflow, $data, $input),
            'collect_description' => $this->afterDescription($user, $workflow, $data, $input),
            'collect_generate_lines' => $this->afterGenerateLinesChoice($user, $workflow, $data, $input),
            'confirm_create' => $this->afterConfirm($user, $workflow, $data, $input),
            'collect_send' => $this->afterSend($user, $workflow, $data, $input),
            default => $this->continue($workflow, 'collect_name', $data, $this->promptFor('collect_name', $kind, $data)),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function afterName(User $user, string $workflow, array $data, string $input): array
    {
        $email = NovaVoiceInputParser::extractEmail($input);
        if ($email) {
            $data['email'] = $email;
        }

        $name = trim(preg_replace('/\s+/u', ' ', str_ireplace((string) $email, '', $input)) ?? $input);
        $name = trim(preg_replace(
            '/\b(create|new|add|quote|proposal|invoice|devis|facture|créer|creer|nouveau|nouvelle|ajouter|for|pour|client|company|entreprise)\b/iu',
            ' ',
            $name,
        ) ?? $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '' || mb_strlen($name) < 2) {
            return $this->continue($workflow, 'collect_name', $data, __('nova_workflow_create_doc_need_name'));
        }
        $data['name'] = mb_substr($name, 0, 200);

        return $this->resolveNameToClientOrAsk($user, $workflow, $data);
    }

    /**
     * Search existing clients by name/company. Exact single match → use it.
     * Multiple (or fuzzy) matches → numbered list. None → create new (ask email).
     *
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function resolveNameToClientOrAsk(User $user, string $workflow, array $data): array
    {
        $company = $user->company;
        if (! $company) {
            throw new RuntimeException(__('nova_workflow_no_company'));
        }

        $query = (string) $data['name'];
        $matches = $this->findClientMatches((string) $company->id, $query);
        $needle = mb_strtolower(trim($query));

        $exact = $matches->first(
            fn (Client $c) => mb_strtolower(trim((string) $c->name)) === $needle
        );

        // Unique exact name → use immediately.
        if ($exact && $matches->count() === 1) {
            return $this->useExistingClient($workflow, $data, $exact);
        }

        if ($matches->isEmpty()) {
            $data['creating_new'] = true;
            unset($data['client_id'], $data['candidates']);

            if (! empty($data['email'])) {
                return $this->continue(
                    $workflow,
                    'collect_description',
                    $data,
                    __('nova_workflow_create_doc_ask_description', ['doc' => $this->docLabel((string) $data['kind'])]),
                );
            }

            return $this->continue(
                $workflow,
                'collect_email',
                $data,
                __('nova_workflow_create_doc_no_match_ask_email', ['name' => $query]),
            );
        }

        $candidates = [];
        $lines = [];
        foreach ($matches->values() as $i => $client) {
            $n = $i + 1;
            $candidates[] = [
                'id' => (string) $client->id,
                'name' => (string) $client->name,
                'email' => trim((string) ($client->email ?? '')),
            ];
            $emailPart = $client->email ? ' — '.$client->email : '';
            $lines[] = $n.'. '.$client->name.$emailPart;
        }

        $data['candidates'] = $candidates;
        unset($data['client_id'], $data['creating_new']);

        return $this->continue(
            $workflow,
            'select_client',
            $data,
            __('nova_workflow_create_doc_matches', [
                'name' => $query,
                'list' => implode("\n", $lines),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function afterSelectClient(User $user, string $workflow, array $data, string $input): array
    {
        $raw = trim($input);
        $normalized = mb_strtolower($raw);

        if ($this->isCreateNewClientPhrase($normalized)) {
            $data['creating_new'] = true;
            unset($data['client_id'], $data['candidates']);

            if (! empty($data['email'])) {
                return $this->continue(
                    $workflow,
                    'collect_description',
                    $data,
                    __('nova_workflow_create_doc_ask_description', ['doc' => $this->docLabel((string) $data['kind'])]),
                );
            }

            return $this->continue(
                $workflow,
                'collect_email',
                $data,
                __('nova_workflow_create_doc_ask_email', ['name' => (string) ($data['name'] ?? '')]),
            );
        }

        /** @var list<array{id: string, name: string, email: string}> $candidates */
        $candidates = is_array($data['candidates'] ?? null) ? $data['candidates'] : [];

        if (preg_match('/^\s*(\d{1,2})\s*$/u', $raw, $m) === 1) {
            $index = ((int) $m[1]) - 1;
            if (isset($candidates[$index])) {
                return $this->useCandidate($workflow, $data, $candidates[$index]);
            }
        }

        $needle = mb_strtolower(trim($raw));
        foreach ($candidates as $candidate) {
            if (mb_strtolower(trim($candidate['name'])) === $needle) {
                return $this->useCandidate($workflow, $data, $candidate);
            }
        }

        foreach ($candidates as $candidate) {
            if (str_contains(mb_strtolower($candidate['name']), $needle) && mb_strlen($needle) >= 2) {
                return $this->useCandidate($workflow, $data, $candidate);
            }
        }

        // Treat as a fresh name search.
        if (mb_strlen($needle) >= 2) {
            $data['name'] = mb_substr($raw, 0, 200);
            unset($data['candidates'], $data['client_id'], $data['creating_new']);

            return $this->resolveNameToClientOrAsk($user, $workflow, $data);
        }

        $list = [];
        foreach ($candidates as $i => $candidate) {
            $emailPart = $candidate['email'] !== '' ? ' — '.$candidate['email'] : '';
            $list[] = ($i + 1).'. '.$candidate['name'].$emailPart;
        }

        return $this->continue(
            $workflow,
            'select_client',
            $data,
            __('nova_workflow_create_doc_need_pick', [
                'list' => implode("\n", $list),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{id: string, name: string, email: string}  $candidate
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function useCandidate(string $workflow, array $data, array $candidate): array
    {
        $data['client_id'] = $candidate['id'];
        $data['name'] = $candidate['name'];
        if ($candidate['email'] !== '') {
            $data['email'] = $candidate['email'];
        }
        unset($data['candidates'], $data['creating_new']);

        $kind = (string) $data['kind'];
        if (empty($data['email'])) {
            return $this->continue(
                $workflow,
                'collect_email',
                $data,
                __('nova_workflow_create_doc_ask_email', ['name' => $data['name']]),
            );
        }

        return $this->continue(
            $workflow,
            'collect_description',
            $data,
            __('nova_workflow_create_doc_client_selected', [
                'name' => $data['name'],
                'doc' => $this->docLabel($kind),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function useExistingClient(string $workflow, array $data, Client $client): array
    {
        $kind = (string) $data['kind'];
        $data['client_id'] = (string) $client->id;
        $data['name'] = (string) $client->name;
        if (filled($client->email)) {
            $data['email'] = (string) $client->email;
        }
        unset($data['candidates'], $data['creating_new']);

        if (empty($data['email'])) {
            return $this->continue(
                $workflow,
                'collect_email',
                $data,
                __('nova_workflow_create_doc_ask_email', ['name' => $data['name']]),
            );
        }

        return $this->continue(
            $workflow,
            'collect_description',
            $data,
            __('nova_workflow_create_doc_client_selected', [
                'name' => $data['name'],
                'doc' => $this->docLabel($kind),
            ]),
        );
    }

    /**
     * @return \Illuminate\Support\Collection<int, Client>
     */
    private function findClientMatches(string $companyId, string $query)
    {
        $needle = mb_strtolower(trim($query));
        if ($needle === '') {
            return collect();
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $needle);

        return Client::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($escaped, $needle): void {
                $q->whereRaw('LOWER(name) LIKE ?', ['%'.$escaped.'%']);
                if (str_contains($needle, '@')) {
                    $q->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', ['%'.$escaped.'%']);
                }
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 WHEN LOWER(name) LIKE ? THEN 1 ELSE 2 END', [$needle, $needle.'%'])
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);
    }

    private function isCreateNewClientPhrase(string $normalized): bool
    {
        return (bool) preg_match(
            '/^(new|create|create\s+new|nouveau|nouvelle|créer|creer|nouveau\s+client|none|aucun|pas\s+dans\s+la\s+liste|not\s+in\s+(the\s+)?list|جديد)$/iu',
            trim($normalized),
        ) || str_contains($normalized, 'create new')
            || str_contains($normalized, 'new client')
            || str_contains($normalized, 'nouveau client')
            || str_contains($normalized, 'créer un nouveau')
            || str_contains($normalized, 'creer un nouveau');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function afterEmail(User $user, string $workflow, array $data, string $input): array
    {
        $kind = (string) $data['kind'];
        $email = NovaVoiceInputParser::extractEmail($input) ?? (filter_var(trim($input), FILTER_VALIDATE_EMAIL) ? strtolower(trim($input)) : null);
        if (! $email) {
            return $this->continue($workflow, 'collect_email', $data, __('nova_workflow_create_doc_need_email'));
        }
        $data['email'] = $email;

        // If email already belongs to a client, prefer that record.
        if (empty($data['client_id'])) {
            $company = $user->company;
            if ($company) {
                $byEmail = Client::query()->withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                    ->first();
                if ($byEmail) {
                    return $this->useExistingClient($workflow, $data, $byEmail);
                }
            }
            $data['creating_new'] = true;
        }

        return $this->continue($workflow, 'collect_description', $data, __('nova_workflow_create_doc_ask_description', ['doc' => $this->docLabel($kind)]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function afterDescription(User $user, string $workflow, array $data, string $input): array
    {
        $description = trim($input);
        if (mb_strlen($description) < 10) {
            return $this->continue($workflow, 'collect_description', $data, __('nova_workflow_create_doc_need_description'));
        }
        $data['description'] = mb_substr($description, 0, 4000);
        unset($data['items'], $data['generate_lines'], $data['subtotal_minor']);

        return $this->continue(
            $workflow,
            'collect_generate_lines',
            $data,
            __('nova_workflow_create_doc_ask_generate_lines', ['doc' => $this->docLabel((string) $data['kind'])]),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function afterGenerateLinesChoice(User $user, string $workflow, array $data, string $input): array
    {
        $yes = NovaVoiceInputParser::parseYesNo($input);
        if ($yes === null) {
            if (preg_match('/\b(lines?|items?|elements?|lignes?|éléments?|ai|génér|gener)\b/iu', $input) === 1) {
                $yes = true;
            } elseif (preg_match('/\b(description\s+only|only\s+description|juste\s+description|sans\s+lignes?|no\s+lines?)\b/iu', $input) === 1) {
                $yes = false;
            }
        }

        if ($yes === null) {
            return $this->continue(
                $workflow,
                'collect_generate_lines',
                $data,
                __('nova_workflow_create_doc_ask_generate_lines_again', ['doc' => $this->docLabel((string) $data['kind'])]),
            );
        }

        $data['generate_lines'] = $yes;

        if ($yes) {
            return $this->generateAndPreview($user, $workflow, $data);
        }

        return $this->descriptionOnlyPreview($user, $workflow, $data);
    }

    /**
     * Single line = work description (no AI-priced elements).
     *
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function descriptionOnlyPreview(User $user, string $workflow, array $data): array
    {
        $company = $user->company;
        if (! $company) {
            throw new RuntimeException(__('nova_workflow_no_company'));
        }

        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());
        $brief = (string) ($data['description'] ?? '');

        // Description only — no priced line elements; text is stored on the document notes.
        $data['items'] = [];
        $data['currency'] = $currency;
        $data['subtotal_minor'] = 0;

        $reply = __('nova_workflow_create_doc_preview_description_only', [
            'doc' => $this->docLabel((string) $data['kind']),
            'client' => (string) $data['name'],
            'email' => (string) ($data['email'] ?? '—'),
            'description' => $brief,
        ]);

        return $this->continue($workflow, 'confirm_create', $data, $reply);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function generateAndPreview(User $user, string $workflow, array $data): array
    {
        $company = $user->company;
        if (! $company) {
            throw new RuntimeException(__('nova_workflow_no_company'));
        }

        $kind = (string) $data['kind'];
        $currency = flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency());
        $brief = (string) $data['description'];
        $creditTask = $kind === 'invoice' ? 'invoice_line_items' : 'quote_line_items';
        $creditCost = $this->usage->creditsForTask($creditTask);

        if (! $this->planLimits->isFeatureEnabled($company, 'ai_credits')
            || ! $this->planLimits->allows($company, 'ai_credits', $creditCost)) {
            // Fallback: single line from description.
            $unitMinor = (int) round(100 * flowdesk_currency_minor_scale($currency));
            $items = [[
                'description' => mb_substr($brief, 0, 500),
                'quantity' => 1,
                'unit_amount' => max(1, $unitMinor),
            ]];
        } else {
            try {
                $items = $kind === 'invoice'
                    ? $this->invoiceLinesAi->suggestLinesDraft($brief, $currency, null, mb_substr($brief, 0, 120), $company)
                    : $this->quoteLinesAi->suggestLinesDraft($brief, $currency, mb_substr($brief, 0, 80), (string) ($data['name'] ?? null), null, null, $company);
                $this->usage->recordForTask($company, $creditTask);
            } catch (Throwable $e) {
                $unitMinor = (int) round(100 * flowdesk_currency_minor_scale($currency));
                $items = [[
                    'description' => mb_substr($brief, 0, 500),
                    'quantity' => 1,
                    'unit_amount' => max(1, $unitMinor),
                ]];
            }
        }

        if ($items === []) {
            return $this->continue($workflow, 'collect_description', $data, __('nova_workflow_create_doc_need_description'));
        }

        $data['items'] = $items;
        $data['currency'] = $currency;
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int) $item['quantity']) * ((int) $item['unit_amount']);
        }
        $data['subtotal_minor'] = $subtotal;

        $lines = [];
        foreach ($items as $i => $item) {
            $lineTotal = ((int) $item['quantity']) * ((int) $item['unit_amount']);
            $lines[] = ($i + 1).'. '.(($item['title'] ?? null) ?: $item['description'])
                .' × '.(int) $item['quantity']
                .' = '.flowdesk_format_minor($lineTotal, $currency).' '.$currency;
        }

        $reply = __('nova_workflow_create_doc_preview', [
            'doc' => $this->docLabel($kind),
            'client' => (string) $data['name'],
            'email' => (string) $data['email'],
            'lines' => implode("\n", $lines),
            'total' => flowdesk_format_minor($subtotal, $currency).' '.$currency,
        ]);

        return $this->continue($workflow, 'confirm_create', $data, $reply);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>, result?: ?array}
     */
    private function afterConfirm(User $user, string $workflow, array $data, string $input): array
    {
        $yes = NovaVoiceInputParser::parseYesNo($input);
        if ($yes === false) {
            return $this->continue($workflow, 'collect_description', $data, __('nova_workflow_create_doc_ask_description_again'));
        }
        if ($yes !== true && ! preg_match('/\b(create|ok|okay|confirm|go|oui|créer|creer|نعم)\b/iu', $input)) {
            return $this->continue($workflow, 'confirm_create', $data, __('nova_workflow_create_doc_confirm_again'));
        }

        return $this->executeCreate($user, $workflow, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>, result?: ?array}
     */
    private function executeCreate(User $user, string $workflow, array $data): array
    {
        $company = $user->company;
        abort_if(! $company, 403);
        $kind = (string) $data['kind'];
        $currency = (string) ($data['currency'] ?? flowdesk_normalize_currency_code($company->default_currency ?? flowdesk_default_currency()));
        /** @var list<array{description: string, quantity: int, unit_amount: int}> $items */
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        $client = $this->resolveClient(
            $company->id,
            (string) $data['name'],
            (string) ($data['email'] ?? ''),
            isset($data['client_id']) ? (string) $data['client_id'] : null,
        );

        if ($kind === 'invoice') {
            $doc = $this->createInvoice(
                $company->id,
                $client,
                $currency,
                $items,
                (string) ($data['description'] ?? ''),
            );
            $result = [
                'type' => 'invoice',
                'id' => (string) $doc->id,
                'reference' => (string) ($doc->number ?? $doc->id),
                'title' => (string) ($doc->number ?? __('Invoice')),
                'amount' => flowdesk_format_minor((int) $doc->amount, $currency).' '.$currency,
                'client_id' => (string) $client->id,
            ];
            $reply = __('nova_workflow_create_doc_created', [
                'doc' => $this->docLabel('invoice'),
                'reference' => $result['reference'],
                'total' => $result['amount'],
            ])."\n\n".__('nova_workflow_create_doc_ask_send');
        } else {
            $doc = $this->createProposal(
                $company->id,
                $client,
                $currency,
                $items,
                (string) ($data['description'] ?? ''),
            );
            $result = [
                'type' => 'quote',
                'id' => (string) $doc->id,
                'reference' => (string) ($doc->number ?? $doc->id),
                'title' => (string) ($doc->name ?? $doc->number ?? __('Quote')),
                'amount' => flowdesk_format_minor((int) $doc->amount, $currency).' '.$currency,
                'client_id' => (string) $client->id,
            ];
            $reply = __('nova_workflow_create_doc_created', [
                'doc' => $this->docLabel('quote'),
                'reference' => $result['reference'],
                'total' => $result['amount'],
            ])."\n\n".__('nova_workflow_create_doc_ask_send');
        }

        $data['result'] = $result;
        $data['document_id'] = $result['id'];

        return $this->continue($workflow, 'collect_send', $data, $reply, $result);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>, result?: ?array}
     */
    private function afterSend(User $user, string $workflow, array $data, string $input): array
    {
        $company = $user->company;
        abort_if(! $company, 403);
        $kind = (string) $data['kind'];
        $result = is_array($data['result'] ?? null) ? $data['result'] : null;
        $yes = NovaVoiceInputParser::parseYesNo($input);

        if ($yes === true || preg_match('/\b(send|email|mail|envoyer|نعم)\b/iu', $input) === 1) {
            try {
                if ($kind === 'invoice') {
                    $invoice = Invoice::query()->withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->findOrFail((string) $data['document_id']);
                    $this->invoiceMail->send($invoice->load('client'), $company);
                    $invoice->update(['status' => InvoiceStatus::Sent]);
                } else {
                    $proposal = Proposal::query()->withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->findOrFail((string) $data['document_id']);
                    $this->proposalMail->send($proposal->load('client'), $company);
                    $proposal->update(['status' => ProposalStatus::Sent]);
                }
                $reply = __('nova_workflow_create_doc_sent', ['doc' => $this->docLabel($kind)]);
            } catch (Throwable $e) {
                $reply = __('nova_workflow_create_doc_send_failed', ['error' => $e->getMessage()]);
            }

            return [
                'active' => false,
                'workflow' => null,
                'reply' => $reply,
                'done' => true,
                'redirect_url' => null,
                'result' => $result,
            ];
        }

        if ($yes === false || preg_match('/\b(no|non|skip|later|draft|keep|pas\s+maintenant|لا)\b/iu', $input) === 1) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_create_doc_kept_draft', ['doc' => $this->docLabel($kind)]),
                'done' => true,
                'redirect_url' => null,
                'result' => $result,
            ];
        }

        return $this->continue($workflow, 'collect_send', $data, __('nova_workflow_create_doc_ask_send'), $result);
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int}>  $items
     */
    private function createProposal(
        string $companyId,
        Client $client,
        string $currency,
        array $items,
        string $brief,
    ): Proposal {
        $client->loadMissing('company');
        $company = $client->company;
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int) $item['quantity']) * ((int) $item['unit_amount']);
        }
        $taxes = $this->totals->fromSubtotalMinor($subtotal, $company);

        return DB::transaction(function () use ($companyId, $client, $company, $currency, $items, $brief, $taxes) {
            $proposal = Proposal::query()->withoutGlobalScope('tenant')->create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'project_id' => null,
                'name' => mb_substr($brief !== '' ? $brief : __('Quote'), 0, 120),
                'number' => null,
                'status' => ProposalStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'customer_notes' => $brief !== '' ? $brief : null,
            ]);

            foreach ($items as $item) {
                ProposalItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'proposal_id' => $proposal->id,
                    ...flowdesk_split_line_label($item['title'] ?? null, $item['description'] ?? ''),
                    'quantity' => (int) $item['quantity'],
                    'unit_amount' => (int) $item['unit_amount'],
                    'total_amount' => ((int) $item['quantity']) * ((int) $item['unit_amount']),
                ]);
            }

            $this->proposalRefs->assignNextNumber($proposal, $company);

            return $proposal->refresh();
        });
    }

    /**
     * @param  list<array{description: string, quantity: int, unit_amount: int}>  $items
     */
    private function createInvoice(
        string $companyId,
        Client $client,
        string $currency,
        array $items,
        string $brief,
    ): Invoice {
        $client->loadMissing('company');
        $company = $client->company;
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += ((int) $item['quantity']) * ((int) $item['unit_amount']);
        }
        $taxes = $this->totals->fromSubtotalMinor($subtotal, $company);

        return DB::transaction(function () use ($companyId, $client, $company, $currency, $items, $brief, $taxes) {
            $invoice = Invoice::query()->withoutGlobalScope('tenant')->create([
                'company_id' => $companyId,
                'client_id' => $client->id,
                'proposal_id' => null,
                'project_id' => null,
                'number' => null,
                'status' => InvoiceStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
                'due_date' => null,
                'customer_notes' => $brief !== '' ? $brief : null,
            ]);

            foreach ($items as $item) {
                InvoiceItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $companyId,
                    'invoice_id' => $invoice->id,
                    ...flowdesk_split_line_label($item['title'] ?? null, $item['description'] ?? ''),
                    'quantity' => (int) $item['quantity'],
                    'unit_amount' => (int) $item['unit_amount'],
                    'total_amount' => ((int) $item['quantity']) * ((int) $item['unit_amount']),
                ]);
            }

            $this->invoiceRefs->assignNextNumber($invoice, $company);

            return $invoice->refresh();
        });
    }

    private function resolveClient(string $companyId, string $name, string $email, ?string $clientId = null): Client
    {
        if ($clientId) {
            $existing = Client::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('id', $clientId)
                ->first();
            if ($existing) {
                if ($email !== '' && ! $existing->email) {
                    $existing->forceFill(['email' => $email])->save();
                }

                return $existing->refresh();
            }
        }

        if ($email !== '') {
            $byEmail = Client::query()->withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
            if ($byEmail) {
                return $byEmail;
            }
        }

        $byName = Client::query()->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();
        if ($byName) {
            if ($email !== '' && ! $byName->email) {
                $byName->forceFill(['email' => $email])->save();
            }

            return $byName->refresh();
        }

        $client = Client::query()->withoutGlobalScopes()->create([
            'company_id' => $companyId,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => null,
            'source' => ClientSource::Other->value,
            'status' => ClientStatus::Active->value,
        ]);
        $this->clientCodes->assignIfMissing($client);

        return $client;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeOpening(array $data, string $input): array
    {
        $email = NovaVoiceInputParser::extractEmail($input);
        if ($email) {
            $data['email'] = $email;
        }
        $name = trim(preg_replace('/\s+/u', ' ', str_ireplace((string) $email, '', $input)) ?? $input);
        // Strip create-quote/invoice intent words from opening utterance.
        $name = trim(preg_replace(
            '/\b(create|new|add|quote|proposal|invoice|devis|facture|créer|creer|nouveau|nouvelle|ajouter|for|pour|client|company|entreprise)\b/iu',
            ' ',
            $name,
        ) ?? $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name !== '' && mb_strlen($name) >= 2 && NovaVoiceInputParser::parseYesNo($name) === null) {
            $data['name'] = mb_substr($name, 0, 200);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function missingFields(array $data): array
    {
        $missing = [];
        if (empty($data['client_id']) && empty($data['name'])) {
            $missing[] = 'collect_name';
        }
        if (empty($data['client_id']) && empty($data['email'])) {
            $missing[] = 'collect_email';
        }
        if (empty($data['description'])) {
            $missing[] = 'collect_description';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasAnyClientField(array $data): bool
    {
        return ! empty($data['name']) || ! empty($data['email']) || ! empty($data['client_id']);
    }

    private function promptFor(string $step, string $kind, array $data): string
    {
        return match ($step) {
            'collect_name' => __('nova_workflow_create_doc_ask_name', ['doc' => $this->docLabel($kind)]),
            'select_client' => __('nova_workflow_create_doc_need_pick', ['list' => '']),
            'collect_email' => __('nova_workflow_create_doc_ask_email', ['name' => (string) ($data['name'] ?? '')]),
            'collect_description' => __('nova_workflow_create_doc_ask_description', ['doc' => $this->docLabel($kind)]),
            'collect_generate_lines' => __('nova_workflow_create_doc_ask_generate_lines', ['doc' => $this->docLabel($kind)]),
            default => __('nova_workflow_create_doc_ask_name', ['doc' => $this->docLabel($kind)]),
        };
    }

    private function docLabel(string $kind): string
    {
        return $kind === 'invoice' ? __('Invoice') : __('Quote');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $result
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step: string, data: array<string, mixed>, result: ?array}
     */
    private function continue(string $workflow, string $step, array $data, string $reply, ?array $result = null): array
    {
        return [
            'active' => true,
            'workflow' => $workflow,
            'reply' => $reply,
            'done' => false,
            'redirect_url' => null,
            'step' => $step,
            'data' => $data,
            'result' => $result ?? (is_array($data['result'] ?? null) ? $data['result'] : null),
        ];
    }
}
