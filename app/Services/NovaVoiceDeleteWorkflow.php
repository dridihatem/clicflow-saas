<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectStudy;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Conversational Nova delete flow: clarify entity → resolve record → confirm → delete.
 */
class NovaVoiceDeleteWorkflow
{
    public function __construct(
        private NovaVoiceDocumentReferenceMatcher $references,
        private ClientDeletionService $clientDeletion,
    ) {}

    /**
     * @param  array{entity?: string, record_id?: string, record_label?: string}  $seed
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    public function start(User $user, array $seed = []): array
    {
        $entity = isset($seed['entity']) && is_string($seed['entity']) ? $this->normalizeEntity($seed['entity']) : null;
        $recordId = isset($seed['record_id']) && is_string($seed['record_id']) ? $seed['record_id'] : null;

        if ($entity !== null && $recordId !== null) {
            return $this->beginConfirmOrBlock($user, $entity, $recordId);
        }

        if ($entity !== null) {
            return [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => $this->askTargetPrompt($entity),
                'done' => false,
                'redirect_url' => null,
                'step' => 'awaiting_target',
                'data' => ['entity' => $entity],
            ];
        }

        return [
            'active' => true,
            'workflow' => 'delete_record',
            'reply' => __('nova_workflow_delete_prompt'),
            'done' => false,
            'redirect_url' => null,
            'step' => 'awaiting_entity',
            'data' => [],
        ];
    }

    /**
     * @param  array{workflow: string, step: string, data: array<string, mixed>}  $session
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    public function advance(User $user, array $session, string $input): array
    {
        $step = (string) ($session['step'] ?? 'awaiting_entity');
        $data = is_array($session['data'] ?? null) ? $session['data'] : [];

        return match ($step) {
            'awaiting_entity' => $this->advanceEntity($user, $data, $input),
            'awaiting_target' => $this->advanceTarget($user, $data, $input),
            'awaiting_confirm' => $this->advanceConfirm($user, $data, $input),
            default => [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => __('nova_workflow_empty_input'),
                'done' => false,
                'redirect_url' => null,
                'step' => $step,
                'data' => $data,
            ],
        };
    }

    /**
     * Prime confirm/block after a targeted utterance like "delete client Marie".
     *
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    public function beginFromRecord(User $user, string $entity, string $recordId, string $label): array
    {
        $entity = $this->normalizeEntity($entity) ?? $entity;
        $block = $this->blockedReason($user, $entity, $recordId);
        if ($block !== null) {
            return [
                'active' => false,
                'workflow' => 'delete_record',
                'reply' => $block,
                'done' => true,
                'redirect_url' => null,
            ];
        }

        return [
            'active' => true,
            'workflow' => 'delete_record',
            'reply' => __('nova_workflow_delete_confirm', ['label' => $label]),
            'done' => false,
            'redirect_url' => null,
            'step' => 'awaiting_confirm',
            'data' => [
                'entity' => $entity,
                'record_id' => $recordId,
                'record_label' => $label,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    private function advanceEntity(User $user, array $data, string $input): array
    {
        $parsed = $this->parseEntityAndOptionalTarget($input);
        if ($parsed === null) {
            return [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => __('nova_workflow_delete_prompt'),
                'done' => false,
                'redirect_url' => null,
                'step' => 'awaiting_entity',
                'data' => $data,
            ];
        }

        $data['entity'] = $parsed['entity'];
        if ($parsed['target'] !== null) {
            return $this->advanceTarget($user, $data, $parsed['target']);
        }

        return [
            'active' => true,
            'workflow' => 'delete_record',
            'reply' => $this->askTargetPrompt($parsed['entity']),
            'done' => false,
            'redirect_url' => null,
            'step' => 'awaiting_target',
            'data' => $data,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    private function advanceTarget(User $user, array $data, string $input): array
    {
        $entity = $this->normalizeEntity((string) ($data['entity'] ?? ''));
        if ($entity === null) {
            return $this->advanceEntity($user, [], $input);
        }

        $found = $this->findRecord($user, $entity, $input);
        if ($found === null) {
            return [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => __('nova_workflow_delete_not_found'),
                'done' => false,
                'redirect_url' => null,
                'step' => 'awaiting_target',
                'data' => ['entity' => $entity],
            ];
        }

        return $this->beginFromRecord($user, $entity, $found['id'], $found['label']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{active: bool, workflow: ?string, reply: string, done: bool, redirect_url: ?string}
     */
    private function advanceConfirm(User $user, array $data, string $input): array
    {
        $yesNo = NovaVoiceInputParser::parseYesNo($input);
        if ($yesNo === false) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_cancelled'),
                'done' => true,
                'redirect_url' => null,
            ];
        }
        if ($yesNo !== true) {
            return [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => __('nova_workflow_delete_confirm', [
                    'label' => (string) ($data['record_label'] ?? __('this record')),
                ]),
                'done' => false,
                'redirect_url' => null,
                'step' => 'awaiting_confirm',
                'data' => $data,
            ];
        }

        $entity = $this->normalizeEntity((string) ($data['entity'] ?? ''));
        $recordId = (string) ($data['record_id'] ?? '');
        $label = (string) ($data['record_label'] ?? $recordId);

        if ($entity === null || $recordId === '') {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_error'),
                'done' => true,
                'redirect_url' => null,
            ];
        }

        $block = $this->blockedReason($user, $entity, $recordId);
        if ($block !== null) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => $block,
                'done' => true,
                'redirect_url' => null,
            ];
        }

        $redirect = $this->performDelete($user, $entity, $recordId);
        if ($redirect === null) {
            return [
                'active' => false,
                'workflow' => null,
                'reply' => __('nova_workflow_error'),
                'done' => true,
                'redirect_url' => null,
            ];
        }

        return [
            'active' => false,
            'workflow' => null,
            'reply' => __('nova_workflow_delete_success', ['label' => $label]),
            'done' => true,
            'redirect_url' => $redirect,
        ];
    }

    /**
     * @return array{active: bool, workflow: string, reply: string, done: bool, redirect_url: ?string, step?: string, data?: array<string, mixed>}
     */
    private function beginConfirmOrBlock(User $user, string $entity, string $recordId): array
    {
        $found = $this->findRecordById($user, $entity, $recordId);
        if ($found === null) {
            return [
                'active' => true,
                'workflow' => 'delete_record',
                'reply' => $this->askTargetPrompt($entity),
                'done' => false,
                'redirect_url' => null,
                'step' => 'awaiting_target',
                'data' => ['entity' => $entity],
            ];
        }

        return $this->beginFromRecord($user, $entity, $found['id'], $found['label']);
    }

    private function askTargetPrompt(string $entity): string
    {
        return match ($entity) {
            'client' => __('nova_workflow_delete_ask_client'),
            'invoice' => __('nova_workflow_delete_ask_invoice'),
            'proposal' => __('nova_workflow_delete_ask_quote'),
            'project' => __('nova_workflow_delete_ask_project'),
            'project_study' => __('nova_workflow_delete_ask_project_study'),
            default => __('nova_workflow_delete_prompt'),
        };
    }

    /**
     * @return array{entity: string, target: ?string}|null
     */
    private function parseEntityAndOptionalTarget(string $input): ?array
    {
        $text = mb_strtolower(trim($input));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = preg_replace('/^(supprimer|effacer|delete|remove|je veux supprimer|i want to delete)\s+/u', '', $text) ?? $text;

        $map = [
            'client' => '(?:client|cliente|clients)',
            'invoice' => '(?:facture|factures|invoice|invoices)',
            'proposal' => '(?:devis|proposition|propositions|quote|quotes|proposal|proposals)',
            'project_study' => '(?:etude de projet|étude de projet|etudes de projet|études de projet|project study|project studies)',
            'project' => '(?:projet|projets|project|projects)',
        ];

        foreach ($map as $entity => $group) {
            if (preg_match('/^'.$group.'(?:\s+(.+))?$/iu', $text, $m) === 1) {
                $target = isset($m[1]) ? trim((string) $m[1]) : '';
                $target = trim($target, " \t\n\r\0\x0B\"'«».,;:?!");
                if ($target !== '' && in_array($target, ['client', 'clients', 'facture', 'invoice', 'devis', 'quote', 'projet', 'project'], true)) {
                    $target = '';
                }

                return [
                    'entity' => $entity,
                    'target' => $target !== '' ? $target : null,
                ];
            }
        }

        return null;
    }

    private function normalizeEntity(string $entity): ?string
    {
        $entity = mb_strtolower(trim($entity));

        return match ($entity) {
            'client', 'clients', 'cliente' => 'client',
            'invoice', 'invoices', 'facture', 'factures' => 'invoice',
            'proposal', 'proposals', 'quote', 'quotes', 'devis', 'proposition' => 'proposal',
            'project', 'projects', 'projet', 'projets' => 'project',
            'project_study', 'project_studies', 'etude', 'étude' => 'project_study',
            default => null,
        };
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findRecord(User $user, string $entity, string $target): ?array
    {
        $target = trim(mb_strtolower($target));
        if ($target === '' || mb_strlen($target) < 2) {
            return null;
        }

        return match ($entity) {
            'client' => $this->findClient($user, $target),
            'invoice' => $this->findInvoice($user, $target),
            'proposal' => $this->findProposal($user, $target),
            'project' => $this->findProject($user, $target),
            'project_study' => $this->findProjectStudy($user, $target),
            default => null,
        };
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findRecordById(User $user, string $entity, string $recordId): ?array
    {
        $companyId = (string) $user->company_id;

        if ($entity === 'client') {
            $client = Client::query()->where('company_id', $companyId)->find($recordId);

            return $client ? ['id' => (string) $client->id, 'label' => (string) $client->name] : null;
        }
        if ($entity === 'invoice') {
            $invoice = Invoice::query()->where('company_id', $companyId)->find($recordId);

            return $invoice ? ['id' => (string) $invoice->id, 'label' => (string) ($invoice->number ?: $invoice->id)] : null;
        }
        if ($entity === 'proposal') {
            $proposal = Proposal::query()->where('company_id', $companyId)->find($recordId);

            return $proposal
                ? ['id' => (string) $proposal->id, 'label' => (string) ($proposal->number ?: $proposal->name ?: $proposal->id)]
                : null;
        }
        if ($entity === 'project') {
            $project = Project::query()->where('company_id', $companyId)->find($recordId);

            return $project ? ['id' => (string) $project->id, 'label' => (string) ($project->title ?: $project->id)] : null;
        }
        if ($entity === 'project_study') {
            $study = ProjectStudy::query()->where('company_id', $companyId)->find($recordId);

            return $study ? ['id' => (string) $study->id, 'label' => (string) ($study->title ?: $study->id)] : null;
        }

        return null;
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findClient(User $user, string $target): ?array
    {
        if (! $user->can('workspace.manage_clients')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $target) ?? '';
        $matches = Client::query()
            ->where('company_id', $user->company_id)
            ->where(function ($q) use ($target, $digits): void {
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
            })
            ->limit(8)
            ->get();

        if ($matches->isEmpty()) {
            return null;
        }

        $client = $matches->count() === 1
            ? $matches->first()
            : ($matches->first(fn (Client $c) => mb_strtolower(trim((string) $c->name)) === $target)
                ?: $matches->first(fn (Client $c) => str_starts_with(mb_strtolower((string) $c->name), $target))
                ?: $matches->first());

        if (! $client) {
            return null;
        }

        return ['id' => (string) $client->id, 'label' => (string) $client->name];
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findInvoice(User $user, string $target): ?array
    {
        if (! $user->can('workspace.manage_invoices')) {
            return null;
        }

        $invoice = $this->references->findInvoice($user, $target);
        if (! $invoice) {
            return null;
        }

        return ['id' => (string) $invoice->id, 'label' => (string) ($invoice->number ?: $invoice->id)];
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findProposal(User $user, string $target): ?array
    {
        if (! $user->can('workspace.manage_invoices')) {
            return null;
        }

        $proposal = $this->references->findProposal($user, $target);
        if (! $proposal) {
            return null;
        }

        return [
            'id' => (string) $proposal->id,
            'label' => (string) ($proposal->number ?: $proposal->name ?: $proposal->id),
        ];
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findProject(User $user, string $target): ?array
    {
        if (! $user->can('workspace.manage_projects')) {
            return null;
        }

        $project = Project::query()
            ->where('company_id', $user->company_id)
            ->where('title', 'like', '%'.$target.'%')
            ->orderByRaw('CASE WHEN LOWER(title) = ? THEN 0 WHEN LOWER(title) LIKE ? THEN 1 ELSE 2 END', [
                $target,
                $target.'%',
            ])
            ->first();

        if (! $project) {
            return null;
        }

        return ['id' => (string) $project->id, 'label' => (string) ($project->title ?: $project->id)];
    }

    /**
     * @return array{id: string, label: string}|null
     */
    private function findProjectStudy(User $user, string $target): ?array
    {
        if (! $user->can('workspace.manage_clients')) {
            return null;
        }

        $study = ProjectStudy::query()
            ->where('company_id', $user->company_id)
            ->where('title', 'like', '%'.$target.'%')
            ->orderByRaw('CASE WHEN LOWER(title) = ? THEN 0 WHEN LOWER(title) LIKE ? THEN 1 ELSE 2 END', [
                $target,
                $target.'%',
            ])
            ->first();

        if (! $study) {
            return null;
        }

        return ['id' => (string) $study->id, 'label' => (string) ($study->title ?: $study->id)];
    }

    private function blockedReason(User $user, string $entity, string $recordId): ?string
    {
        if ($entity === 'client') {
            $client = Client::query()->where('company_id', $user->company_id)->find($recordId);
            if (! $client) {
                return __('nova_workflow_delete_not_found');
            }
            if ($client->invoices()->exists()) {
                return __('nova_workflow_delete_client_locked', [
                    'name' => $client->name,
                    'relations' => __('invoices'),
                ]);
            }
        }

        if ($entity === 'proposal') {
            $proposal = Proposal::query()->where('company_id', $user->company_id)->find($recordId);
            if (! $proposal) {
                return __('nova_workflow_delete_not_found');
            }
            if ($proposal->invoices()->exists()) {
                $ref = (string) ($proposal->number ?: $proposal->name ?: $proposal->id);

                return __('nova_workflow_delete_quote_locked', ['ref' => $ref]);
            }
        }

        return null;
    }

    private function performDelete(User $user, string $entity, string $recordId): ?string
    {
        return match ($entity) {
            'client' => $this->deleteClient($user, $recordId),
            'invoice' => $this->deleteInvoice($user, $recordId),
            'proposal' => $this->deleteProposal($user, $recordId),
            'project' => $this->deleteProject($user, $recordId),
            'project_study' => $this->deleteProjectStudy($user, $recordId),
            default => null,
        };
    }

    private function deleteClient(User $user, string $id): ?string
    {
        if (! $user->can('workspace.manage_clients') || ! Route::has('clients.index')) {
            return null;
        }
        $client = Client::query()->where('company_id', $user->company_id)->find($id);
        if (! $client) {
            return null;
        }
        try {
            $this->clientDeletion->delete($client);
        } catch (\RuntimeException) {
            return null;
        }

        return route('clients.index');
    }

    private function deleteInvoice(User $user, string $id): ?string
    {
        if (! $user->can('workspace.manage_invoices') || ! Route::has('invoices.index')) {
            return null;
        }
        $invoice = Invoice::query()->where('company_id', $user->company_id)->find($id);
        if (! $invoice || $user->hasRole('business_provider')) {
            return null;
        }
        $invoice->delete();

        return route('invoices.index');
    }

    private function deleteProposal(User $user, string $id): ?string
    {
        if (! $user->can('workspace.manage_invoices') || ! Route::has('proposals.index')) {
            return null;
        }
        $proposal = Proposal::query()->where('company_id', $user->company_id)->find($id);
        if (! $proposal || $proposal->invoices()->exists()) {
            return null;
        }
        $proposal->delete();

        return route('proposals.index');
    }

    private function deleteProject(User $user, string $id): ?string
    {
        if (! $user->can('workspace.manage_projects') || ! Route::has('projects.index')) {
            return null;
        }
        $project = Project::query()->where('company_id', $user->company_id)->find($id);
        if (! $project) {
            return null;
        }
        $project->delete();

        return route('projects.index');
    }

    private function deleteProjectStudy(User $user, string $id): ?string
    {
        if (! $user->can('workspace.manage_clients') || ! Route::has('project-studies.index')) {
            return null;
        }
        $study = ProjectStudy::query()->where('company_id', $user->company_id)->find($id);
        if (! $study) {
            return null;
        }
        $study->delete();

        return route('project-studies.index');
    }
}
