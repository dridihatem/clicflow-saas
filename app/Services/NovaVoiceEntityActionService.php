<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class NovaVoiceEntityActionService
{
    public function __construct(
        private NovaVoiceWorkflowService $workflows,
        private NovaVoiceDocumentReferenceMatcher $references,
    ) {}

    /**
     * Deterministically map "edit/delete client|quote|invoice + target" to a record URL or delete confirm.
     *
     * @return array{
     *     type: 'command'|'workflow'|'speak',
     *     id: string,
     *     label: string|null,
     *     url: string|null,
     *     action: null,
     *     confidence: float,
     *     target: string,
     *     reply?: string,
     *     done?: bool,
     *     workflow?: string
     * }|null
     */
    public function resolve(User $user, string $normalizedUtterance): ?array
    {
        $parsed = $this->parse($normalizedUtterance);
        if ($parsed === null) {
            return null;
        }

        return match ($parsed['entity']) {
            'client' => $this->resolveClient($user, $parsed['verb'], $parsed['target']),
            'proposal' => $this->resolveProposal($user, $parsed['verb'], $parsed['target']),
            'invoice' => $this->resolveInvoice($user, $parsed['verb'], $parsed['target']),
            default => null,
        };
    }

    /**
     * @return array{verb: 'edit'|'delete', entity: 'client'|'proposal'|'invoice', target: string}|null
     */
    public function parse(string $normalized): ?array
    {
        $text = trim(mb_strtolower($normalized));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        // Strip polite fillers.
        $text = preg_replace('/^(s\'il te plait|s\'il vous plait|please|nova|hey nova)\s+/u', '', $text) ?? $text;

        $verbGroup = '(modifier|editer|éditer|edit|modify|update|changer|supprimer|effacer|delete|remove)';
        $clientGroup = '(?:client|cliente|clients)';
        $proposalGroup = '(?:devis|proposition|propositions|quote|quotes|proposal|proposals)';
        $invoiceGroup = '(?:facture|factures|invoice|invoices)';

        $patterns = [
            ['entity' => 'client', 'group' => $clientGroup],
            ['entity' => 'proposal', 'group' => $proposalGroup],
            ['entity' => 'invoice', 'group' => $invoiceGroup],
        ];

        foreach ($patterns as $row) {
            $regex = '/^'.$verbGroup
                .'(?:\s+(?:le|la|un|une|the|a|an))?'
                .'\s+'.$row['group']
                .'(?:\s+(?:numero|numéro|number|ref|reference|référence|nommé|nommee|named|appelé|appelee))?'
                .'\s+(.+)$/iu';

            if (preg_match($regex, $text, $m) !== 1) {
                continue;
            }

            $verbRaw = mb_strtolower((string) $m[1]);
            $target = trim((string) ($m[2] ?? ''));
            $target = trim($target, " \t\n\r\0\x0B\"'«».,;:?!");
            if ($target === '' || mb_strlen($target) < 2) {
                return null;
            }

            // Bare “supprimer client” without a target is handled by catalog phrases.
            if (in_array($target, ['client', 'cliente', 'clients', 'devis', 'facture', 'invoice', 'quote', 'proposal'], true)) {
                return null;
            }

            $verb = in_array($verbRaw, ['supprimer', 'effacer', 'delete', 'remove'], true)
                ? 'delete'
                : 'edit';

            return [
                'verb' => $verb,
                'entity' => $row['entity'],
                'target' => $target,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveClient(User $user, string $verb, string $target): ?array
    {
        if (! $user->can('workspace.manage_clients') || ! Route::has('clients.edit')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $target) ?? '';
        $query = Client::query()->limit(8);

        $query->where(function ($q) use ($target, $digits): void {
            $q->where('name', 'like', '%'.$target.'%');
            if (strlen($digits) >= 6) {
                $q->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''),' ',''),'-',''),'.',''),'+',''),'(','') LIKE ?",
                    ['%'.$digits.'%'],
                );
            }
            if (str_contains($target, '@')) {
                $q->orWhere('email', 'like', '%'.$target.'%');
            }
        });

        $matches = $query->get();
        if ($matches->isEmpty()) {
            return null;
        }

        $client = $this->pickBestClient($matches, $target, $digits);
        if ($client === null) {
            return null;
        }

        $id = $verb === 'delete' ? 'clients.delete' : 'clients.edit';
        $label = $verb === 'delete'
            ? __('nova_voice_label_delete_client_named', ['name' => $client->name])
            : __('nova_voice_label_edit_client_named', ['name' => $client->name]);

        if ($verb === 'delete') {
            $confirm = $this->workflows->startDeleteConfirm($user, 'client', (string) $client->id, (string) $client->name);

            return [
                'type' => ($confirm['done'] ?? false) ? 'speak' : 'workflow',
                'id' => $id,
                'label' => $label,
                'url' => null,
                'action' => null,
                'confidence' => 0.97,
                'target' => $target,
                'reply' => (string) ($confirm['reply'] ?? ''),
                'done' => (bool) ($confirm['done'] ?? false),
                'workflow' => 'delete_record',
            ];
        }

        $url = route('clients.edit', $client);

        return [
            'type' => 'command',
            'id' => $id,
            'label' => $label,
            'url' => $url,
            'action' => null,
            'confidence' => 0.97,
            'target' => $target,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Client>  $matches
     */
    private function pickBestClient($matches, string $target, string $digits): ?Client
    {
        if ($matches->count() === 1) {
            return $matches->first();
        }

        $exact = $matches->first(fn (Client $c) => mb_strtolower(trim((string) $c->name)) === $target);
        if ($exact) {
            return $exact;
        }

        if (strlen($digits) >= 6) {
            $byPhone = $matches->first(function (Client $c) use ($digits) {
                $phone = preg_replace('/\D+/', '', (string) $c->phone) ?? '';

                return $phone !== '' && (str_contains($phone, $digits) || str_contains($digits, $phone));
            });
            if ($byPhone) {
                return $byPhone;
            }
        }

        // Prefer starts-with name match over loose contains.
        $starts = $matches->first(fn (Client $c) => str_starts_with(mb_strtolower((string) $c->name), $target));

        return $starts ?: $matches->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProposal(User $user, string $verb, string $target): ?array
    {
        if (! $user->can('workspace.manage_invoices') || ! Route::has('proposals.edit')) {
            return null;
        }

        $proposal = $this->references->findProposal($user, $target);
        if (! $proposal) {
            return null;
        }

        $ref = (string) ($proposal->number ?: $proposal->name ?: $proposal->id);
        $id = $verb === 'delete' ? 'proposals.delete' : 'proposals.edit';
        $label = $verb === 'delete'
            ? __('nova_voice_label_delete_quote_ref', ['ref' => $ref])
            : __('nova_voice_label_edit_quote_ref', ['ref' => $ref]);

        if ($verb === 'delete') {
            $confirm = $this->workflows->startDeleteConfirm($user, 'proposal', (string) $proposal->id, $ref);

            return [
                'type' => ($confirm['done'] ?? false) ? 'speak' : 'workflow',
                'id' => $id,
                'label' => $label,
                'url' => null,
                'action' => null,
                'confidence' => 0.96,
                'target' => $target,
                'reply' => (string) ($confirm['reply'] ?? ''),
                'done' => (bool) ($confirm['done'] ?? false),
                'workflow' => 'delete_record',
            ];
        }

        return [
            'type' => 'command',
            'id' => $id,
            'label' => $label,
            'url' => route('proposals.edit', $proposal),
            'action' => null,
            'confidence' => 0.96,
            'target' => $target,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveInvoice(User $user, string $verb, string $target): ?array
    {
        if (! $user->can('workspace.manage_invoices') || ! Route::has('invoices.edit')) {
            return null;
        }

        $invoice = $this->references->findInvoice($user, $target);
        if (! $invoice) {
            return null;
        }

        $ref = (string) ($invoice->number ?: $invoice->id);
        $id = $verb === 'delete' ? 'invoices.delete' : 'invoices.edit';
        $label = $verb === 'delete'
            ? __('nova_voice_label_delete_invoice_ref', ['ref' => $ref])
            : __('nova_voice_label_edit_invoice_ref', ['ref' => $ref]);

        if ($verb === 'delete') {
            $confirm = $this->workflows->startDeleteConfirm($user, 'invoice', (string) $invoice->id, $ref);

            return [
                'type' => ($confirm['done'] ?? false) ? 'speak' : 'workflow',
                'id' => $id,
                'label' => $label,
                'url' => null,
                'action' => null,
                'confidence' => 0.96,
                'target' => $target,
                'reply' => (string) ($confirm['reply'] ?? ''),
                'done' => (bool) ($confirm['done'] ?? false),
                'workflow' => 'delete_record',
            ];
        }

        return [
            'type' => 'command',
            'id' => $id,
            'label' => $label,
            'url' => route('invoices.edit', $invoice),
            'action' => null,
            'confidence' => 0.96,
            'target' => $target,
        ];
    }
}
