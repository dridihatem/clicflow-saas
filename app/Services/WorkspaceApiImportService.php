<?php

namespace App\Services;

use App\Enums\ClientSource;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\ProposalItem;
use Illuminate\Support\Facades\DB;

class WorkspaceApiImportService
{
    public function __construct(
        private ClientCodeService $clientCodes,
        private PlanLimitService $planLimits,
        private InvoiceTotalsService $totals,
        private ProposalReferenceService $proposalRefs,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{clients: list<array<string, mixed>>, projects: list<array<string, mixed>>, proposals: list<array<string, mixed>>, products: list<array<string, mixed>>}
     */
    public function import(Company $company, array $payload): array
    {
        return DB::transaction(function () use ($company, $payload) {
            $refToClientId = [];
            $createdClients = [];

            foreach ($payload['clients'] ?? [] as $row) {
                $client = Client::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => $row['name'],
                    'email' => $row['email'] ?? null,
                    'phone' => $row['phone'] ?? null,
                    'source' => ClientSource::Other,
                ]);
                $this->clientCodes->assignIfMissing($client);

                $ref = isset($row['ref']) && is_string($row['ref']) ? trim($row['ref']) : '';
                if ($ref !== '') {
                    $refToClientId[$ref] = $client->id;
                }

                $createdClients[] = [
                    'id' => $client->id,
                    'ref' => $ref !== '' ? $ref : null,
                    'name' => $client->name,
                    'code' => $client->code,
                ];
            }

            $createdProjects = [];
            foreach ($payload['projects'] ?? [] as $row) {
                $this->planLimits->assertAllows($company, 'projects');

                $clientId = $row['client_id'] ?? null;
                if ($clientId === null && ! empty($row['client_ref'])) {
                    $clientId = $refToClientId[$row['client_ref']] ?? null;
                }
                if ($clientId !== null) {
                    $exists = Client::query()->withoutGlobalScopes()
                        ->where('company_id', $company->id)
                        ->whereKey($clientId)
                        ->exists();
                    abort_unless($exists, 422, __('Client not found in this workspace.'));
                }

                $status = ProjectStatus::Draft;
                if (! empty($row['status'])) {
                    $status = ProjectStatus::tryFrom((string) $row['status']) ?? ProjectStatus::Draft;
                }

                $project = Project::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'client_id' => $clientId,
                    'title' => $row['title'],
                    'description' => $row['description'] ?? null,
                    'status' => $status,
                    'source' => ProjectSource::Internal,
                    'created_by' => null,
                ]);

                $createdProjects[] = [
                    'id' => $project->id,
                    'title' => $project->title,
                    'client_id' => $project->client_id,
                ];
            }

            $createdProposals = [];
            foreach ($payload['proposals'] ?? [] as $row) {
                abort_if(empty($row['client_id']) && empty($row['client_ref']), 422, __('Client is required for proposals.'));
                $clientId = $row['client_id'] ?? null;
                if ($clientId === null && ! empty($row['client_ref'])) {
                    $clientId = $refToClientId[$row['client_ref']] ?? null;
                }
                abort_if($clientId === null, 422, __('Client not found in this workspace.'));

                $currency = strtoupper((string) ($row['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
                $items = $row['items'] ?? [];
                abort_if($items === [], 422, __('At least one line item is required.'));

                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += ((int) $item['quantity']) * ((int) $item['unit_amount']);
                }
                $taxes = $this->totals->fromSubtotalMinor($subtotal, $company);

                $proposal = Proposal::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'client_id' => $clientId,
                    'project_id' => $row['project_id'] ?? null,
                    'name' => $row['name'] ?? __('Quote'),
                    'status' => ProposalStatus::Draft,
                    'subtotal_amount' => $taxes['subtotal'],
                    'vat_amount' => $taxes['vat'],
                    'fiscal_stamp_amount' => $taxes['stamp'],
                    'amount' => $taxes['total'],
                    'currency' => $currency,
                ]);

                foreach ($items as $item) {
                    $qty = (int) $item['quantity'];
                    $unit = (int) $item['unit_amount'];
                    ProposalItem::query()->withoutGlobalScopes()->create([
                        'company_id' => $company->id,
                        'proposal_id' => $proposal->id,
                        'title' => flowdesk_line_title($item['title'] ?? null),
                        'description' => (string) ($item['description'] ?? ''),
                        'quantity' => $qty,
                        'unit_amount' => $unit,
                        'total_amount' => $qty * $unit,
                    ]);
                }

                $this->proposalRefs->assignNextNumber($proposal, $company);

                $createdProposals[] = [
                    'id' => $proposal->id,
                    'name' => $proposal->name,
                    'client_id' => $proposal->client_id,
                ];
            }

            $createdProducts = [];
            foreach ($payload['products'] ?? [] as $row) {
                $currency = strtoupper((string) ($row['currency'] ?? $company->default_currency ?? flowdesk_default_currency()));
                $priceMinor = isset($row['price_minor'])
                    ? (int) $row['price_minor']
                    : flowdesk_decimal_to_minor((string) ($row['price'] ?? 0), $currency);
                $trackStock = ! (bool) ($row['is_service'] ?? false);

                $product = Product::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'name' => $row['name'],
                    'sku' => $row['sku'] ?? null,
                    'description' => $row['description'] ?? null,
                    'base_price_minor' => $priceMinor,
                    'currency' => $currency,
                    'track_stock' => $trackStock,
                    'is_active' => true,
                ]);

                ProductVariant::query()->create([
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                    'sku' => $row['sku'] ?? null,
                    'stock_qty' => (int) ($row['stock_qty'] ?? 0),
                ]);

                $createdProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                ];
            }

            return [
                'clients' => $createdClients,
                'projects' => $createdProjects,
                'proposals' => $createdProposals,
                'products' => $createdProducts,
            ];
        });
    }
}
