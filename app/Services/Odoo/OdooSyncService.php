<?php

namespace App\Services\Odoo;

use App\Enums\ClientStatus;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OdooSyncMap;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OdooSyncService
{
    public static bool $suppressObservers = false;

    public function __construct(
        private OdooConfigService $config,
        private OdooJsonRpcClient $client,
    ) {}

    /**
     * @return array{pushed: array<string, int>, pulled: array<string, int>, errors: list<string>}
     */
    public function syncCompany(Company $company): array
    {
        if (! $this->config->isConfigured($company)) {
            throw new RuntimeException(__('odoo_not_configured'));
        }

        $cfg = $this->config->getResolved($company);
        $result = [
            'pushed' => ['clients' => 0, 'projects' => 0, 'invoices' => 0, 'proposals' => 0],
            'pulled' => ['clients' => 0, 'projects' => 0, 'invoices' => 0, 'proposals' => 0],
            'errors' => [],
        ];

        try {
            $this->client->authenticate($company, force: true);
        } catch (Throwable $e) {
            $this->config->rememberSyncResult($company, 'failed', $e->getMessage());
            throw $e;
        }

        if ($cfg['sync_clients']) {
            try {
                $result['pulled']['clients'] = $this->pullClients($company);
                $result['pushed']['clients'] = $this->pushClients($company);
            } catch (Throwable $e) {
                $result['errors'][] = 'clients: '.$e->getMessage();
                Log::warning('odoo.sync.clients', ['company_id' => $company->id, 'message' => $e->getMessage()]);
            }
        }

        if ($cfg['sync_projects']) {
            try {
                $result['pulled']['projects'] = $this->pullProjects($company);
                $result['pushed']['projects'] = $this->pushProjects($company);
            } catch (Throwable $e) {
                $result['errors'][] = 'projects: '.$e->getMessage();
                Log::warning('odoo.sync.projects', ['company_id' => $company->id, 'message' => $e->getMessage()]);
            }
        }

        if ($cfg['sync_proposals']) {
            try {
                $result['pulled']['proposals'] = $this->pullProposals($company);
                $result['pushed']['proposals'] = $this->pushProposals($company);
            } catch (Throwable $e) {
                $result['errors'][] = 'proposals: '.$e->getMessage();
                Log::warning('odoo.sync.proposals', ['company_id' => $company->id, 'message' => $e->getMessage()]);
            }
        }

        if ($cfg['sync_invoices']) {
            try {
                $result['pulled']['invoices'] = $this->pullInvoices($company);
                $result['pushed']['invoices'] = $this->pushInvoices($company);
            } catch (Throwable $e) {
                $result['errors'][] = 'invoices: '.$e->getMessage();
                Log::warning('odoo.sync.invoices', ['company_id' => $company->id, 'message' => $e->getMessage()]);
            }
        }

        $status = $result['errors'] === [] ? 'ok' : 'partial';
        $message = __('odoo_sync_summary', [
            'pushed' => array_sum($result['pushed']),
            'pulled' => array_sum($result['pulled']),
            'errors' => count($result['errors']),
        ]);
        if ($result['errors'] !== []) {
            $message .= ' '.implode(' | ', $result['errors']);
        }
        $this->config->rememberSyncResult($company, $status, $message);

        return $result;
    }

    public function pushEntity(Company $company, string $entityType, string $localId): void
    {
        if (! $this->config->isConfigured($company) || self::$suppressObservers) {
            return;
        }

        $cfg = $this->config->getResolved($company);

        match ($entityType) {
            OdooSyncMap::ENTITY_CLIENT => $cfg['sync_clients'] ? $this->pushClientById($company, $localId) : null,
            OdooSyncMap::ENTITY_PROJECT => $cfg['sync_projects'] ? $this->pushProjectById($company, $localId) : null,
            OdooSyncMap::ENTITY_INVOICE => $cfg['sync_invoices'] ? $this->pushInvoiceById($company, $localId) : null,
            OdooSyncMap::ENTITY_PROPOSAL => $cfg['sync_proposals'] ? $this->pushProposalById($company, $localId) : null,
            default => null,
        };
    }

    private function pullClients(Company $company): int
    {
        $rows = $this->client->executeKw($company, 'res.partner', 'search_read', [
            [['customer_rank', '>', 0]],
        ], [
            'fields' => ['id', 'name', 'email', 'phone', 'street', 'city', 'zip', 'country_id', 'write_date'],
            'limit' => 200,
            'order' => 'write_date desc',
        ]);

        if (! is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $odooId = (int) $row['id'];
            $map = $this->findMapByOdoo($company, 'res.partner', $odooId);
            $payload = [
                'company_id' => $company->id,
                'name' => (string) ($row['name'] ?? __('odoo_unnamed_partner')),
                'email' => $this->stringOrNull($row['email'] ?? null),
                'phone' => $this->stringOrNull($row['phone'] ?? null),
                'address' => array_filter([
                    'street' => $this->stringOrNull($row['street'] ?? null),
                    'city' => $this->stringOrNull($row['city'] ?? null),
                    'postal_code' => $this->stringOrNull($row['zip'] ?? null),
                ]),
                'status' => ClientStatus::Active,
            ];

            self::$suppressObservers = true;
            try {
                if ($map) {
                    $client = Client::query()->withoutGlobalScopes()->find($map->local_id);
                    if ($client) {
                        $client->fill($payload)->save();
                    }
                } else {
                    $existing = null;
                    if (! empty($payload['email'])) {
                        $existing = Client::query()->withoutGlobalScopes()
                            ->where('company_id', $company->id)
                            ->where('email', $payload['email'])
                            ->first();
                    }
                    $client = $existing ?: Client::query()->withoutGlobalScopes()->create($payload);
                    if ($existing) {
                        $client->fill($payload)->save();
                    }
                    $this->rememberMap($company, OdooSyncMap::ENTITY_CLIENT, (string) $client->id, 'res.partner', $odooId, 'pull');
                }
            } finally {
                self::$suppressObservers = false;
            }
            $count++;
        }

        return $count;
    }

    private function pushClients(Company $company): int
    {
        $clients = Client::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($clients as $client) {
            if ($this->pushClientModel($company, $client)) {
                $count++;
            }
        }

        return $count;
    }

    private function pushClientById(Company $company, string $localId): void
    {
        $client = Client::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($localId)
            ->first();
        if ($client) {
            $this->pushClientModel($company, $client);
        }
    }

    private function pushClientModel(Company $company, Client $client): bool
    {
        $address = is_array($client->address) ? $client->address : [];
        $values = [
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'street' => $address['street'] ?? false,
            'city' => $address['city'] ?? false,
            'zip' => $address['postal_code'] ?? ($address['zip'] ?? false),
            'customer_rank' => 1,
        ];

        $map = $this->findMapByLocal($company, OdooSyncMap::ENTITY_CLIENT, (string) $client->id);
        if ($map) {
            $this->client->executeKw($company, 'res.partner', 'write', [[$map->odoo_id], $values]);
            $this->touchMap($map, 'push');

            return true;
        }

        $odooId = (int) $this->client->executeKw($company, 'res.partner', 'create', [$values]);
        if ($odooId <= 0) {
            return false;
        }
        $this->rememberMap($company, OdooSyncMap::ENTITY_CLIENT, (string) $client->id, 'res.partner', $odooId, 'push');

        return true;
    }

    private function pullProjects(Company $company): int
    {
        try {
            $rows = $this->client->executeKw($company, 'project.project', 'search_read', [
                [],
            ], [
                'fields' => ['id', 'name', 'partner_id', 'date', 'description', 'write_date'],
                'limit' => 200,
                'order' => 'write_date desc',
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException(__('odoo_projects_unavailable', ['message' => $e->getMessage()]), 0, $e);
        }

        if (! is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $odooId = (int) $row['id'];
            $clientId = $this->localClientIdFromPartner($company, $row['partner_id'] ?? null);
            $payload = [
                'company_id' => $company->id,
                'title' => (string) ($row['name'] ?? __('odoo_unnamed_project')),
                'description' => is_string($row['description'] ?? null) ? $row['description'] : null,
                'final_deadline' => $this->dateOrNull($row['date'] ?? null),
                'client_id' => $clientId,
                'status' => ProjectStatus::InProgress,
                'source' => ProjectSource::Internal,
            ];

            self::$suppressObservers = true;
            try {
                $map = $this->findMapByOdoo($company, 'project.project', $odooId);
                if ($map) {
                    $project = Project::query()->withoutGlobalScopes()->find($map->local_id);
                    if ($project) {
                        $project->fill($payload)->save();
                    }
                } else {
                    $project = Project::query()->withoutGlobalScopes()->create($payload);
                    $this->rememberMap($company, OdooSyncMap::ENTITY_PROJECT, (string) $project->id, 'project.project', $odooId, 'pull');
                }
            } finally {
                self::$suppressObservers = false;
            }
            $count++;
        }

        return $count;
    }

    private function pushProjects(Company $company): int
    {
        $projects = Project::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get();

        $count = 0;
        foreach ($projects as $project) {
            if ($this->pushProjectModel($company, $project)) {
                $count++;
            }
        }

        return $count;
    }

    private function pushProjectById(Company $company, string $localId): void
    {
        $project = Project::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($localId)
            ->first();
        if ($project) {
            $this->pushProjectModel($company, $project);
        }
    }

    private function pushProjectModel(Company $company, Project $project): bool
    {
        $partnerId = $this->odooPartnerIdForClient($company, $project->client_id);
        $values = [
            'name' => $project->title,
            'description' => $project->description ?: false,
            'date' => $project->final_deadline?->format('Y-m-d') ?: false,
            'partner_id' => $partnerId ?: false,
        ];

        $map = $this->findMapByLocal($company, OdooSyncMap::ENTITY_PROJECT, (string) $project->id);
        try {
            if ($map) {
                $this->client->executeKw($company, 'project.project', 'write', [[$map->odoo_id], $values]);
                $this->touchMap($map, 'push');

                return true;
            }
            $odooId = (int) $this->client->executeKw($company, 'project.project', 'create', [$values]);
            if ($odooId <= 0) {
                return false;
            }
            $this->rememberMap($company, OdooSyncMap::ENTITY_PROJECT, (string) $project->id, 'project.project', $odooId, 'push');

            return true;
        } catch (Throwable $e) {
            Log::warning('odoo.push.project', ['project_id' => $project->id, 'message' => $e->getMessage()]);

            return false;
        }
    }

    private function pullProposals(Company $company): int
    {
        $rows = $this->client->executeKw($company, 'sale.order', 'search_read', [
            [['state', 'in', ['draft', 'sent', 'sale', 'cancel']]],
        ], [
            'fields' => ['id', 'name', 'partner_id', 'amount_untaxed', 'amount_tax', 'amount_total', 'date_order', 'validity_date', 'state', 'currency_id', 'write_date'],
            'limit' => 100,
            'order' => 'write_date desc',
        ]);

        if (! is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $odooId = (int) $row['id'];
            $clientId = $this->localClientIdFromPartner($company, $row['partner_id'] ?? null);
            if (! $clientId) {
                continue;
            }

            $currency = $this->currencyFromOdoo($row['currency_id'] ?? null) ?? ($company->default_currency ?? flowdesk_default_currency());
            $subtotal = $this->majorToMinor((float) ($row['amount_untaxed'] ?? 0), $currency);
            $vat = $this->majorToMinor((float) ($row['amount_tax'] ?? 0), $currency);
            $total = $this->majorToMinor((float) ($row['amount_total'] ?? 0), $currency);

            $payload = [
                'company_id' => $company->id,
                'client_id' => $clientId,
                'name' => (string) ($row['name'] ?? __('Quote')),
                'status' => $this->mapSaleStateToProposal((string) ($row['state'] ?? 'draft')),
                'amount' => $total,
                'subtotal_amount' => $subtotal,
                // Odoo totals are already net of discounts: no FlowDesk document discount on synced records.
                'discount_type' => null,
                'discount_value' => null,
                'discount_amount' => 0,
                'vat_amount' => $vat,
                'fiscal_stamp_amount' => 0,
                'currency' => $currency,
                'valid_until' => $this->dateOrNull($row['validity_date'] ?? null),
            ];

            self::$suppressObservers = true;
            try {
                $map = $this->findMapByOdoo($company, 'sale.order', $odooId);
                if ($map) {
                    $proposal = Proposal::query()->withoutGlobalScopes()->find($map->local_id);
                    if ($proposal) {
                        $proposal->fill($payload)->save();
                    }
                } else {
                    $proposal = Proposal::query()->withoutGlobalScopes()->create($payload);
                    $this->rememberMap($company, OdooSyncMap::ENTITY_PROPOSAL, (string) $proposal->id, 'sale.order', $odooId, 'pull');
                }
            } finally {
                self::$suppressObservers = false;
            }
            $count++;
        }

        return $count;
    }

    private function pushProposals(Company $company): int
    {
        $proposals = Proposal::query()->withoutGlobalScopes()
            ->with(['items', 'client'])
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($proposals as $proposal) {
            if ($this->pushProposalModel($company, $proposal)) {
                $count++;
            }
        }

        return $count;
    }

    private function pushProposalById(Company $company, string $localId): void
    {
        $proposal = Proposal::query()->withoutGlobalScopes()
            ->with(['items', 'client'])
            ->where('company_id', $company->id)
            ->whereKey($localId)
            ->first();
        if ($proposal) {
            $this->pushProposalModel($company, $proposal);
        }
    }

    private function pushProposalModel(Company $company, Proposal $proposal): bool
    {
        $partnerId = $this->odooPartnerIdForClient($company, $proposal->client_id);
        if (! $partnerId) {
            if ($proposal->client) {
                $this->pushClientModel($company, $proposal->client);
                $partnerId = $this->odooPartnerIdForClient($company, $proposal->client_id);
            }
        }
        if (! $partnerId) {
            return false;
        }

        $lineCommands = [];
        foreach ($proposal->items as $item) {
            /** @var ProposalItem $item */
            $qty = max(1, (int) $item->quantity);
            $unitMajor = $this->minorToMajor((int) $item->unit_amount, (string) $proposal->currency);
            $lineCommands[] = [0, 0, [
                'name' => trim(implode("\n", array_filter([(string) ($item->title ?? ''), (string) $item->description], fn ($v) => trim($v) !== ''))) ?: __('Line item'),
                'product_uom_qty' => $qty,
                'price_unit' => $unitMajor,
            ]];
        }

        $values = [
            'partner_id' => $partnerId,
            'validity_date' => $proposal->valid_until?->format('Y-m-d') ?: false,
            'note' => $proposal->name,
        ];
        if ($lineCommands !== []) {
            $values['order_line'] = $lineCommands;
        }

        $map = $this->findMapByLocal($company, OdooSyncMap::ENTITY_PROPOSAL, (string) $proposal->id);
        try {
            if ($map) {
                // Avoid duplicating lines on update: write header only.
                unset($values['order_line']);
                $this->client->executeKw($company, 'sale.order', 'write', [[$map->odoo_id], $values]);
                $this->touchMap($map, 'push');

                return true;
            }
            $odooId = (int) $this->client->executeKw($company, 'sale.order', 'create', [$values]);
            if ($odooId <= 0) {
                return false;
            }
            $this->rememberMap($company, OdooSyncMap::ENTITY_PROPOSAL, (string) $proposal->id, 'sale.order', $odooId, 'push');

            return true;
        } catch (Throwable $e) {
            Log::warning('odoo.push.proposal', ['proposal_id' => $proposal->id, 'message' => $e->getMessage()]);

            return false;
        }
    }

    private function pullInvoices(Company $company): int
    {
        $rows = $this->client->executeKw($company, 'account.move', 'search_read', [
            [['move_type', '=', 'out_invoice']],
        ], [
            'fields' => ['id', 'name', 'partner_id', 'amount_untaxed', 'amount_tax', 'amount_total', 'invoice_date_due', 'payment_state', 'state', 'currency_id', 'write_date'],
            'limit' => 100,
            'order' => 'write_date desc',
        ]);

        if (! is_array($rows)) {
            return 0;
        }

        $count = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['id'])) {
                continue;
            }
            $odooId = (int) $row['id'];
            $clientId = $this->localClientIdFromPartner($company, $row['partner_id'] ?? null);
            if (! $clientId) {
                continue;
            }

            $currency = $this->currencyFromOdoo($row['currency_id'] ?? null) ?? ($company->default_currency ?? flowdesk_default_currency());
            $subtotal = $this->majorToMinor((float) ($row['amount_untaxed'] ?? 0), $currency);
            $vat = $this->majorToMinor((float) ($row['amount_tax'] ?? 0), $currency);
            $total = $this->majorToMinor((float) ($row['amount_total'] ?? 0), $currency);

            $payload = [
                'company_id' => $company->id,
                'client_id' => $clientId,
                'number' => (string) ($row['name'] ?? ('INV-'.$odooId)),
                'status' => $this->mapInvoiceState((string) ($row['state'] ?? 'draft'), (string) ($row['payment_state'] ?? '')),
                'amount' => $total,
                'subtotal_amount' => $subtotal,
                // Odoo totals are already net of discounts: no FlowDesk document discount on synced records.
                'discount_type' => null,
                'discount_value' => null,
                'discount_amount' => 0,
                'vat_amount' => $vat,
                'fiscal_stamp_amount' => 0,
                'currency' => $currency,
                'due_date' => $this->dateOrNull($row['invoice_date_due'] ?? null),
            ];

            self::$suppressObservers = true;
            try {
                $map = $this->findMapByOdoo($company, 'account.move', $odooId);
                if ($map) {
                    $invoice = Invoice::query()->withoutGlobalScopes()->find($map->local_id);
                    if ($invoice) {
                        $invoice->fill($payload)->save();
                    }
                } else {
                    $invoice = Invoice::query()->withoutGlobalScopes()->create($payload);
                    $this->rememberMap($company, OdooSyncMap::ENTITY_INVOICE, (string) $invoice->id, 'account.move', $odooId, 'pull');
                }
            } finally {
                self::$suppressObservers = false;
            }
            $count++;
        }

        return $count;
    }

    private function pushInvoices(Company $company): int
    {
        $invoices = Invoice::query()->withoutGlobalScopes()
            ->with(['items', 'client'])
            ->where('company_id', $company->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get();

        $count = 0;
        foreach ($invoices as $invoice) {
            if ($this->pushInvoiceModel($company, $invoice)) {
                $count++;
            }
        }

        return $count;
    }

    private function pushInvoiceById(Company $company, string $localId): void
    {
        $invoice = Invoice::query()->withoutGlobalScopes()
            ->with(['items', 'client'])
            ->where('company_id', $company->id)
            ->whereKey($localId)
            ->first();
        if ($invoice) {
            $this->pushInvoiceModel($company, $invoice);
        }
    }

    private function pushInvoiceModel(Company $company, Invoice $invoice): bool
    {
        $partnerId = $this->odooPartnerIdForClient($company, $invoice->client_id);
        if (! $partnerId && $invoice->client) {
            $this->pushClientModel($company, $invoice->client);
            $partnerId = $this->odooPartnerIdForClient($company, $invoice->client_id);
        }
        if (! $partnerId) {
            return false;
        }

        $lineCommands = [];
        foreach ($invoice->items as $item) {
            /** @var InvoiceItem $item */
            $qty = max(1, (int) $item->quantity);
            $unitMajor = $this->minorToMajor((int) $item->unit_amount, (string) $invoice->currency);
            $lineCommands[] = [0, 0, [
                'name' => trim(implode("\n", array_filter([(string) ($item->title ?? ''), (string) $item->description], fn ($v) => trim($v) !== ''))) ?: __('Line item'),
                'quantity' => $qty,
                'price_unit' => $unitMajor,
            ]];
        }

        $values = [
            'move_type' => 'out_invoice',
            'partner_id' => $partnerId,
            'invoice_date_due' => $invoice->due_date?->format('Y-m-d') ?: false,
            'ref' => $invoice->number,
        ];
        if ($lineCommands !== []) {
            $values['invoice_line_ids'] = $lineCommands;
        }

        $map = $this->findMapByLocal($company, OdooSyncMap::ENTITY_INVOICE, (string) $invoice->id);
        try {
            if ($map) {
                unset($values['invoice_line_ids'], $values['move_type']);
                $this->client->executeKw($company, 'account.move', 'write', [[$map->odoo_id], $values]);
                $this->touchMap($map, 'push');

                return true;
            }
            $odooId = (int) $this->client->executeKw($company, 'account.move', 'create', [$values]);
            if ($odooId <= 0) {
                return false;
            }
            $this->rememberMap($company, OdooSyncMap::ENTITY_INVOICE, (string) $invoice->id, 'account.move', $odooId, 'push');

            return true;
        } catch (Throwable $e) {
            Log::warning('odoo.push.invoice', ['invoice_id' => $invoice->id, 'message' => $e->getMessage()]);

            return false;
        }
    }

    private function findMapByLocal(Company $company, string $entityType, string $localId): ?OdooSyncMap
    {
        return OdooSyncMap::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('entity_type', $entityType)
            ->where('local_id', $localId)
            ->first();
    }

    private function findMapByOdoo(Company $company, string $odooModel, int $odooId): ?OdooSyncMap
    {
        return OdooSyncMap::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('odoo_model', $odooModel)
            ->where('odoo_id', $odooId)
            ->first();
    }

    private function rememberMap(
        Company $company,
        string $entityType,
        string $localId,
        string $odooModel,
        int $odooId,
        string $direction,
    ): void {
        OdooSyncMap::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'entity_type' => $entityType,
                'local_id' => $localId,
            ],
            [
                'odoo_model' => $odooModel,
                'odoo_id' => $odooId,
                'last_synced_at' => now(),
                'last_direction' => $direction,
            ],
        );
    }

    private function touchMap(OdooSyncMap $map, string $direction): void
    {
        $map->forceFill([
            'last_synced_at' => now(),
            'last_direction' => $direction,
        ])->save();
    }

    private function localClientIdFromPartner(Company $company, mixed $partnerField): ?string
    {
        $odooId = $this->many2oneId($partnerField);
        if (! $odooId) {
            return null;
        }
        $map = $this->findMapByOdoo($company, 'res.partner', $odooId);
        if ($map) {
            return (string) $map->local_id;
        }

        return null;
    }

    private function odooPartnerIdForClient(Company $company, mixed $clientId): ?int
    {
        if (! is_string($clientId) && ! is_int($clientId)) {
            return null;
        }
        $map = $this->findMapByLocal($company, OdooSyncMap::ENTITY_CLIENT, (string) $clientId);

        return $map?->odoo_id;
    }

    private function many2oneId(mixed $value): ?int
    {
        if (is_array($value) && isset($value[0]) && is_numeric($value[0])) {
            return (int) $value[0];
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function currencyFromOdoo(mixed $currencyField): ?string
    {
        if (is_array($currencyField) && isset($currencyField[1]) && is_string($currencyField[1])) {
            return strtoupper(trim($currencyField[1]));
        }

        return null;
    }

    private function mapSaleStateToProposal(string $state): ProposalStatus
    {
        return match ($state) {
            'sent' => ProposalStatus::Sent,
            'sale' => ProposalStatus::Accepted,
            'cancel' => ProposalStatus::Rejected,
            default => ProposalStatus::Draft,
        };
    }

    private function mapInvoiceState(string $state, string $paymentState): InvoiceStatus
    {
        if ($state === 'cancel') {
            return InvoiceStatus::Cancelled;
        }
        if (in_array($paymentState, ['paid', 'in_payment'], true)) {
            return InvoiceStatus::Paid;
        }
        if ($state === 'posted') {
            return InvoiceStatus::Sent;
        }

        return InvoiceStatus::Draft;
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === 'false') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value === 'false') {
            return null;
        }

        return $value;
    }

    private function minorToMajor(int $minor, string $currency): float
    {
        $scale = strtoupper($currency) === 'TND' ? 1000 : 100;

        return round($minor / $scale, $scale === 1000 ? 3 : 2);
    }

    private function majorToMinor(float $major, string $currency): int
    {
        $scale = strtoupper($currency) === 'TND' ? 1000 : 100;

        return (int) round($major * $scale);
    }
}
