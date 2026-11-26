<?php

namespace App\Services\Mcp;

use App\Enums\ClientSource;
use App\Enums\InvoiceStatus;
use App\Enums\ProjectSource;
use App\Enums\ProjectStatus;
use App\Enums\ProposalStatus;
use App\Enums\ProspectSource;
use App\Enums\ProspectStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\TaskScope;
use App\Enums\TaskPriceMode;
use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Company;
use App\Models\EmailMarketingCampaign;
use App\Models\EmailMarketingRecipientDelivery;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\McpToken;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Prospect;
use App\Models\SupportTicket;
use App\Models\WorkspaceCalendarEvent;
use App\Services\ClientCodeService;
use App\Services\InvoiceReferenceService;
use App\Services\InvoiceTotalsService;
use App\Services\ProposalReferenceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyMcpTools
{
    public function __construct(
        private ClientCodeService $clientCodes,
        private InvoiceTotalsService $invoiceTotals,
        private InvoiceReferenceService $invoiceRefs,
        private ProposalReferenceService $proposalRefs,
    ) {}

    /**
     * @return list<array{name:string,description:string,inputSchema:array<string,mixed>,scope:string}>
     */
    public function list(): array
    {
        return array_map(function (array $d) {
            return [
                'name' => $d['name'],
                'description' => $d['description'],
                'inputSchema' => $d['inputSchema'],
                // MCP tool hints (ChatGPT / Claude use them for confirmations and read-only plans).
                'annotations' => [
                    'title' => ucfirst(str_replace('_', ' ', $d['name'])),
                    'readOnlyHint' => ($d['scope'] ?? 'read') === 'read',
                    'destructiveHint' => false,
                    'idempotentHint' => ($d['scope'] ?? 'read') === 'read',
                    'openWorldHint' => false,
                ],
            ];
        }, $this->catalog());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function definition(string $name): ?array
    {
        foreach ($this->catalog() as $d) {
            if ($d['name'] === $name) {
                return $d;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function call(string $name, array $arguments, McpToken $token, ?Company $company): mixed
    {
        if (! $company instanceof Company) {
            return ['error' => true, 'message' => 'Workspace not found.'];
        }

        $def = $this->definition($name);
        if ($def === null) {
            return ['error' => true, 'message' => 'Unknown tool.'];
        }

        $handler = $def['handler'];

        return $this->{$handler}($company, $arguments);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function catalog(): array
    {
        $page = [
            'q' => ['type' => 'string', 'description' => 'Search text (name, email, phone).'],
            'page' => ['type' => 'integer', 'description' => 'Page number, starting at 1.', 'default' => 1],
            'per_page' => ['type' => 'integer', 'description' => 'Results per page (max 100).', 'default' => 25],
        ];

        return [
            ['name' => 'list_clients', 'description' => 'List or search clients in this workspace.', 'scope' => 'read', 'handler' => 'listClients', 'inputSchema' => $this->object($page + ['status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'potential']], 'category' => ['type' => 'string']])],
            ['name' => 'list_vault_files', 'description' => 'List Coffre-fort (vault) files metadata: name, size, project, client, visibility. Read-only, no file contents.', 'scope' => 'read', 'handler' => 'listVaultFiles', 'inputSchema' => $this->object($page + ['project_id' => ['type' => 'string'], 'client_id' => ['type' => 'string'], 'folder' => ['type' => 'string']])],
            ['name' => 'list_products', 'description' => 'List or search products and services of the catalog with price, currency and tax rates (tax_rate, tax_rate_2 in %).', 'scope' => 'read', 'handler' => 'listProducts', 'inputSchema' => $this->object($page + ['kind' => ['type' => 'string', 'enum' => ['product', 'service']]])],
            ['name' => 'create_product', 'description' => 'Create a product or service in the catalog. price is in major units (e.g. 150.00), tax_rate/tax_rate_2 in percent (e.g. 20).', 'scope' => 'write', 'handler' => 'createProduct', 'inputSchema' => $this->object([
                'name' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'enum' => ['product', 'service']],
                'price' => ['type' => 'number'],
                'currency' => ['type' => 'string', 'description' => 'ISO code, defaults to the workspace currency.'],
                'tax_rate' => ['type' => 'number'],
                'tax_rate_2' => ['type' => 'number'],
                'unit' => ['type' => 'string'],
                'sku' => ['type' => 'string'],
                'category' => ['type' => 'string'],
                'description' => ['type' => 'string'],
            ], ['name'])],
            ['name' => 'search_clients', 'description' => 'Search clients by name, company, main contact, email or phone.', 'scope' => 'read', 'handler' => 'listClients', 'inputSchema' => $this->object($page, ['q'])],
            ['name' => 'create_client', 'description' => 'Create a client.', 'scope' => 'write', 'handler' => 'createClient', 'inputSchema' => $this->object([
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'company_name' => ['type' => 'string', 'description' => 'Company (société).'],
                'contact_name' => ['type' => 'string', 'description' => 'Main contact full name.'],
                'category' => ['type' => 'string', 'description' => 'Free-text category.'],
                'status' => ['type' => 'string', 'enum' => ['active', 'inactive', 'potential'], 'default' => 'active'],
                'source' => ['type' => 'string'],
                'address_line1' => ['type' => 'string'],
                'address_postal_code' => ['type' => 'string', 'description' => 'Postal / ZIP code (code postal).'],
                'address_city' => ['type' => 'string'],
                'address_country' => ['type' => 'string'],
            ], ['name'])],
            ['name' => 'list_prospects', 'description' => 'List or search prospects (leads) in this workspace.', 'scope' => 'read', 'handler' => 'listProspects', 'inputSchema' => $this->object($page + ['status' => ['type' => 'string']])],
            ['name' => 'search_prospects', 'description' => 'Search prospects by name, email or company.', 'scope' => 'read', 'handler' => 'listProspects', 'inputSchema' => $this->object($page, ['q'])],
            ['name' => 'create_prospect', 'description' => 'Create a prospect (lead).', 'scope' => 'write', 'handler' => 'createProspect', 'inputSchema' => $this->object([
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'company_name' => ['type' => 'string'],
                'job_title' => ['type' => 'string'],
                'notes' => ['type' => 'string'],
                'source' => ['type' => 'string'],
            ], ['name'])],
            ['name' => 'list_projects', 'description' => 'List projects, optionally filtered by client or status.', 'scope' => 'read', 'handler' => 'listProjects', 'inputSchema' => $this->object($page + ['client_id' => ['type' => 'string'], 'status' => ['type' => 'string']])],
            ['name' => 'create_project', 'description' => 'Create a project. Use title (not name).', 'scope' => 'write', 'handler' => 'createProject', 'inputSchema' => $this->object([
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'description' => 'draft, pending, approved, in_progress, completed'],
                'final_deadline' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
            ], ['title'])],
            ['name' => 'list_tasks', 'description' => 'List project tasks, optionally by project or status.', 'scope' => 'read', 'handler' => 'listTasks', 'inputSchema' => $this->object($page + ['project_id' => ['type' => 'string'], 'status' => ['type' => 'string']])],
            ['name' => 'create_task', 'description' => 'Create a task on a project.', 'scope' => 'write', 'handler' => 'createTask', 'inputSchema' => $this->object([
                'project_id' => ['type' => 'string'],
                'title' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'status' => ['type' => 'string', 'description' => 'todo, in_progress, review, done'],
            ], ['project_id', 'title'])],
            ['name' => 'update_task_status', 'description' => 'Update the status of a task.', 'scope' => 'write', 'handler' => 'updateTaskStatus', 'inputSchema' => $this->object([
                'task_id' => ['type' => 'string'],
                'status' => ['type' => 'string', 'description' => 'todo, in_progress, review, done'],
            ], ['task_id', 'status'])],
            ['name' => 'list_quotes', 'description' => 'List quotes (proposals).', 'scope' => 'read', 'handler' => 'listQuotes', 'inputSchema' => $this->object($page + ['client_id' => ['type' => 'string'], 'status' => ['type' => 'string']])],
            ['name' => 'create_quote', 'description' => 'Create a draft quote (proposal) with line items. Amounts are in minor units (cents).', 'scope' => 'write', 'handler' => 'createQuote', 'inputSchema' => $this->object([
                'client_id' => ['type' => 'string'],
                'name' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'description' => ['type' => 'string'],
                    'quantity' => ['type' => 'integer'],
                    'unit_amount' => ['type' => 'integer', 'description' => 'Unit price in cents'],
                    'vat_rate' => ['type' => 'number', 'description' => 'Line VAT % (e.g. 20, 0). Defaults to the workspace VAT rate.'],
                ]]],
            ], ['client_id', 'name', 'items'])],
            ['name' => 'list_invoices', 'description' => 'List invoices, optionally by status or client.', 'scope' => 'read', 'handler' => 'listInvoices', 'inputSchema' => $this->object($page + ['client_id' => ['type' => 'string'], 'status' => ['type' => 'string']])],
            ['name' => 'create_invoice', 'description' => 'Create a DRAFT invoice with line items. Amounts are in minor units (cents). Status is always draft.', 'scope' => 'write', 'handler' => 'createInvoice', 'inputSchema' => $this->object([
                'client_id' => ['type' => 'string'],
                'currency' => ['type' => 'string'],
                'due_date' => ['type' => 'string'],
                'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                    'description' => ['type' => 'string'],
                    'quantity' => ['type' => 'integer'],
                    'unit_amount' => ['type' => 'integer'],
                    'vat_rate' => ['type' => 'number', 'description' => 'Line VAT % (e.g. 20, 0). Defaults to the workspace VAT rate.'],
                ]]],
            ], ['client_id', 'items'])],
            ['name' => 'list_tickets', 'description' => 'List support tickets.', 'scope' => 'read', 'handler' => 'listTickets', 'inputSchema' => $this->object($page + ['status' => ['type' => 'string'], 'client_id' => ['type' => 'string']])],
            ['name' => 'create_ticket', 'description' => 'Create a support ticket.', 'scope' => 'write', 'handler' => 'createTicket', 'inputSchema' => $this->object([
                'subject' => ['type' => 'string'],
                'message' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
            ], ['subject', 'message'])],
            ['name' => 'list_appointments', 'description' => 'List calendar events and appointments.', 'scope' => 'read', 'handler' => 'listAppointments', 'inputSchema' => $this->object($page + [
                'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'client_id' => ['type' => 'string'],
            ])],
            ['name' => 'create_appointment', 'description' => 'Create a calendar appointment.', 'scope' => 'write', 'handler' => 'createAppointment', 'inputSchema' => $this->object([
                'title' => ['type' => 'string'],
                'date' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                'end_date' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'client_id' => ['type' => 'string'],
                'kind' => ['type' => 'string', 'description' => 'meeting, appointment, or custom'],
            ], ['title', 'date'])],
            ['name' => 'campaigns_stats', 'description' => 'Email marketing campaign statistics for this workspace.', 'scope' => 'read', 'handler' => 'campaignsStats', 'inputSchema' => $this->object(['campaign_id' => ['type' => 'string']])],
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function object(array $properties, array $required = []): array
    {
        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    private function scoped(Builder $query, Company $company): Builder
    {
        return $query->withoutGlobalScopes()->where('company_id', $company->id);
    }

    /**
     * @return array{page:int,per_page:int}
     */
    private function page(array $args): array
    {
        return [
            'page' => max(1, (int) ($args['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($args['per_page'] ?? 25))),
        ];
    }

    /**
     * @param  callable(\Illuminate\Database\Eloquent\Model): array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function paginate(Builder $query, array $args, callable $map): array
    {
        $p = $this->page($args);
        $paginator = $query->paginate($p['per_page'], ['*'], 'page', $p['page']);

        return [
            'data' => $paginator->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function listClients(Company $company, array $args): array
    {
        $q = $this->scoped(Client::query(), $company)->latest();
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term)
                    ->orWhere('company_name', 'like', $term)->orWhere('contact_name', 'like', $term);
            });
        }
        if (! empty($args['status']) && in_array((string) $args['status'], \App\Enums\ClientStatus::values(), true)) {
            $q->where('status', (string) $args['status']);
        }
        if (! empty($args['category'])) {
            $q->where('category', trim((string) $args['category']));
        }

        return $this->paginate($q, $args, fn (Client $c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'email' => $c->email,
            'phone' => $c->phone,
            'company_name' => $c->company_name,
            'contact_name' => $c->contact_name,
            'category' => $c->category,
            'status' => $c->status?->value ?? $c->status,
            'created_at' => $c->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function listVaultFiles(Company $company, array $args): array
    {
        $q = \App\Models\VaultFile::query()->withoutGlobalScopes()->where('company_id', $company->id)->whereNull('deleted_at')->latest();
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(fn ($sub) => $sub->where('original_name', 'like', $term)->orWhere('description', 'like', $term)->orWhere('folder', 'like', $term));
        }
        foreach (['project_id', 'client_id', 'folder'] as $key) {
            if (! empty($args[$key])) {
                $q->where($key, trim((string) $args[$key]));
            }
        }

        return $this->paginate($q, $args, fn (\App\Models\VaultFile $f) => [
            'id' => $f->id,
            'name' => $f->original_name,
            'extension' => $f->extension,
            'size' => $f->size,
            'folder' => $f->folder,
            'project_id' => $f->project_id,
            'client_id' => $f->client_id,
            'uploader_type' => $f->uploader_type,
            'visibility' => app(\App\Services\Vault\VaultAccessService::class)->badges($f),
            'created_at' => $f->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function listProducts(Company $company, array $args): array
    {
        $q = \App\Models\Product::query()->withoutGlobalScopes()->where('company_id', $company->id)->orderBy('name');
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('category', 'like', $term));
        }
        if (! empty($args['kind'])) {
            $q->where('kind', (string) $args['kind']);
        }

        return $this->paginate($q, $args, fn (\App\Models\Product $p) => $this->productPayload($p));
    }

    /** @param  array<string, mixed>  $args */
    private function createProduct(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'name' => ['required', 'string', 'max:255'],
            'kind' => ['nullable', 'string', Rule::in(['product', 'service'])],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_rate_2' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'unit' => ['nullable', 'string', 'max:32'],
            'sku' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
        ])->validate();

        $kind = $data['kind'] ?? 'service';
        $currency = flowdesk_normalize_currency_code($data['currency'] ?? $company->default_currency ?? flowdesk_default_currency());
        $product = \App\Models\Product::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'kind' => $kind,
            'sku' => $data['sku'] ?? null,
            'description' => $data['description'] ?? null,
            'base_price_minor' => flowdesk_decimal_to_minor((string) ($data['price'] ?? 0), $currency),
            'currency' => $currency,
            'unit' => \App\Support\CatalogUnits::isKnown((string) ($data['unit'] ?? '')) ? $data['unit'] : null,
            'track_stock' => $kind === 'product',
            'tax_rate' => isset($data['tax_rate']) ? round((float) $data['tax_rate'], 2) : null,
            'tax_rate_2' => isset($data['tax_rate_2']) ? round((float) $data['tax_rate_2'], 2) : null,
            'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
            'is_active' => true,
        ]);
        \App\Models\ProductVariant::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'product_id' => $product->id,
            'sku' => $data['sku'] ?? null,
            'price_minor' => null,
            'stock_qty' => 0,
        ]);

        return ['created' => true, 'product' => $this->productPayload($product->fresh())];
    }

    /** @return array<string, mixed> */
    private function productPayload(\App\Models\Product $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'kind' => $p->kind?->value ?? $p->kind,
            'sku' => $p->sku,
            'price' => flowdesk_major_amount_for_input((int) $p->base_price_minor, (string) $p->currency),
            'currency' => $p->currency,
            'tax_rate' => $p->tax_rate !== null ? (float) $p->tax_rate : null,
            'tax_rate_2' => $p->tax_rate_2 !== null ? (float) $p->tax_rate_2 : null,
            'unit' => $p->unit,
            'category' => $p->category,
            'is_active' => (bool) $p->is_active,
            'description' => $p->description,
        ];
    }

    /** @param  array<string, mixed>  $args */
    private function createClient(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(\App\Enums\ClientStatus::values())],
            'source' => ['nullable', 'string', Rule::in(ClientSource::values())],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_city' => ['nullable', 'string', 'max:120'],
            'address_postal_code' => ['nullable', 'string', 'max:20'],
            'address_country' => ['nullable', 'string', 'max:8'],
        ])->validate();

        $address = array_filter([
            'line1' => $data['address_line1'] ?? null,
            'postal_code' => isset($data['address_postal_code']) ? trim((string) $data['address_postal_code']) : null,
            'city' => $data['address_city'] ?? null,
            'country' => $data['address_country'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $client = Client::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'company_name' => filled($data['company_name'] ?? null) ? trim((string) $data['company_name']) : null,
            'contact_name' => filled($data['contact_name'] ?? null) ? trim((string) $data['contact_name']) : null,
            'category' => filled($data['category'] ?? null) ? trim((string) $data['category']) : null,
            'status' => $data['status'] ?? \App\Enums\ClientStatus::Active->value,
            'source' => $data['source'] ?? ClientSource::Other->value,
            'address' => $address === [] ? null : $address,
        ]);
        $this->clientCodes->assignIfMissing($client);

        return ['created' => true, 'client' => ['id' => $client->id, 'code' => $client->fresh()?->code, 'name' => $client->name, 'email' => $client->email, 'company_name' => $client->company_name, 'contact_name' => $client->contact_name, 'category' => $client->category, 'status' => $client->status?->value ?? $client->status]];
    }

    /** @param  array<string, mixed>  $args */
    private function listProspects(Company $company, array $args): array
    {
        $q = $this->scoped(Prospect::query(), $company)->latest();
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('company_name', 'like', $term);
            });
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }

        return $this->paginate($q, $args, fn (Prospect $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'email' => $p->email,
            'phone' => $p->phone,
            'company_name' => $p->company_name,
            'status' => $p->status?->value ?? $p->status,
            'created_at' => $p->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createProspect(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'source' => ['nullable', 'string'],
        ])->validate();

        $prospect = Prospect::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'job_title' => $data['job_title'] ?? null,
            'notes' => $data['notes'] ?? null,
            'source' => $data['source'] ?? ProspectSource::Manual->value,
            'status' => ProspectStatus::New,
        ]);

        return ['created' => true, 'prospect' => ['id' => $prospect->id, 'name' => $prospect->name, 'email' => $prospect->email, 'status' => $prospect->status?->value]];
    }

    /** @param  array<string, mixed>  $args */
    private function listProjects(Company $company, array $args): array
    {
        $q = $this->scoped(Project::query(), $company)->with('client:id,name')->latest();
        if (! empty($args['client_id'])) {
            $q->where('client_id', $args['client_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }
        if (! empty($args['q'])) {
            $term = '%'.trim((string) $args['q']).'%';
            $q->where(function ($sub) use ($term): void {
                $sub->where('title', 'like', $term)->orWhere('description', 'like', $term);
            });
        }

        return $this->paginate($q, $args, fn (Project $p) => [
            'id' => $p->id,
            'title' => $p->title,
            'status' => $p->status?->value ?? $p->status,
            'client_id' => $p->client_id,
            'client_name' => $p->client?->name,
            'final_deadline' => $p->final_deadline?->format('Y-m-d'),
            'created_at' => $p->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createProject(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'client_id' => ['nullable', 'string', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
            'status' => ['nullable', 'string', Rule::enum(ProjectStatus::class)],
            'final_deadline' => ['nullable', 'date'],
        ])->validate();

        $project = Project::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'client_id' => $data['client_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? ProjectStatus::Draft,
            'source' => ProjectSource::Internal,
            'final_deadline' => $data['final_deadline'] ?? null,
            'created_by' => null,
        ]);

        return ['created' => true, 'project' => ['id' => $project->id, 'title' => $project->title, 'status' => $project->status?->value]];
    }

    /** @param  array<string, mixed>  $args */
    private function listTasks(Company $company, array $args): array
    {
        $q = $this->scoped(ProjectTask::query(), $company)->with('project:id,title')->latest();
        if (! empty($args['project_id'])) {
            $q->where('project_id', $args['project_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }

        return $this->paginate($q, $args, fn (ProjectTask $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status?->value ?? $t->status,
            'project_id' => $t->project_id,
            'project_title' => $t->project?->title,
            'created_at' => $t->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createTask(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'project_id' => ['required', 'string', Rule::exists('projects', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::enum(TaskStatus::class)],
        ])->validate();

        $maxOrder = (int) ProjectTask::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('project_id', $data['project_id'])
            ->max('sort_order');

        $task = ProjectTask::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'project_id' => $data['project_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? TaskStatus::Todo,
            'sort_order' => $maxOrder + 1,
            'scope' => TaskScope::Core,
            'price_mode' => TaskPriceMode::Bundled,
            'billable' => false,
        ]);

        return ['created' => true, 'task' => ['id' => $task->id, 'title' => $task->title, 'status' => $task->status?->value]];
    }

    /** @param  array<string, mixed>  $args */
    private function updateTaskStatus(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'task_id' => ['required', 'string'],
            'status' => ['required', 'string', Rule::enum(TaskStatus::class)],
        ])->validate();

        $task = ProjectTask::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereKey($data['task_id'])
            ->first();
        if (! $task) {
            return ['error' => true, 'message' => 'Task not found in this workspace.'];
        }
        $task->update(['status' => $data['status']]);

        return ['updated' => true, 'task' => ['id' => $task->id, 'title' => $task->title, 'status' => $task->status?->value]];
    }

    /** @param  array<string, mixed>  $args */
    private function listQuotes(Company $company, array $args): array
    {
        $q = $this->scoped(Proposal::query(), $company)->with('client:id,name')->latest();
        if (! empty($args['client_id'])) {
            $q->where('client_id', $args['client_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }

        return $this->paginate($q, $args, fn (Proposal $p) => [
            'id' => $p->id,
            'name' => $p->name,
            'number' => $p->number,
            'status' => $p->status?->value ?? $p->status,
            'amount' => (int) $p->amount,
            'currency' => $p->currency,
            'client_id' => $p->client_id,
            'client_name' => $p->client?->name,
            'created_at' => $p->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createQuote(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'client_id' => ['required', 'string', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
            'name' => ['required', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'required_without:items.*.title', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'integer', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])->validate();

        $defaultVat = $this->invoiceTotals->companyVatPercent($company);
        foreach ($data['items'] as $k => $row) {
            $data['items'][$k]['vat_rate'] = InvoiceTotalsService::normalizeRate($row['vat_rate'] ?? null, $defaultVat);
        }
        $taxes = $this->invoiceTotals->fromItemsMinor(InvoiceTotalsService::rowsFromFormItems($data['items']), $company);
        $currency = strtoupper($data['currency'] ?? $company->default_currency ?? 'EUR');

        $proposal = DB::transaction(function () use ($data, $company, $taxes, $currency) {
            $proposal = Proposal::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'name' => $data['name'],
                'number' => null,
                'status' => ProposalStatus::Draft,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
                'currency' => $currency,
            ]);
            foreach ($data['items'] as $row) {
                $line = $row['quantity'] * $row['unit_amount'];
                ProposalItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'proposal_id' => $proposal->id,
                    'title' => flowdesk_line_title($row['title'] ?? null),
                    'description' => (string) ($row['description'] ?? ''),
                    'quantity' => $row['quantity'],
                    'unit_amount' => $row['unit_amount'],
                    'total_amount' => $line,
                    'vat_rate' => $row['vat_rate'],
                ]);
            }
            $this->proposalRefs->assignNextNumber($proposal, $company);

            return $proposal;
        });

        return ['created' => true, 'quote' => ['id' => $proposal->id, 'name' => $proposal->name, 'number' => $proposal->fresh()?->number, 'status' => 'draft', 'amount' => (int) $proposal->amount, 'currency' => $proposal->currency]];
    }

    /** @param  array<string, mixed>  $args */
    private function listInvoices(Company $company, array $args): array
    {
        $q = $this->scoped(Invoice::query(), $company)->with('client:id,name')->latest();
        if (! empty($args['client_id'])) {
            $q->where('client_id', $args['client_id']);
        }
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }

        return $this->paginate($q, $args, fn (Invoice $i) => [
            'id' => $i->id,
            'number' => $i->number,
            'status' => $i->status?->value ?? $i->status,
            'amount' => (int) $i->amount,
            'currency' => $i->currency,
            'client_id' => $i->client_id,
            'client_name' => $i->client?->name,
            'due_date' => $i->due_date?->format('Y-m-d'),
            'created_at' => $i->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createInvoice(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'client_id' => ['required', 'string', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
            'currency' => ['nullable', 'string', 'size:3'],
            'due_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'required_without:items.*.title', 'string', 'max:5000'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_amount' => ['required', 'integer', 'min:0'],
            'items.*.vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ])->validate();

        $defaultVat = $this->invoiceTotals->companyVatPercent($company);
        foreach ($data['items'] as $k => $row) {
            $data['items'][$k]['vat_rate'] = InvoiceTotalsService::normalizeRate($row['vat_rate'] ?? null, $defaultVat);
        }
        $taxes = $this->invoiceTotals->fromItemsMinor(InvoiceTotalsService::rowsFromFormItems($data['items']), $company);
        $currency = strtoupper($data['currency'] ?? $company->default_currency ?? 'EUR');

        $invoice = DB::transaction(function () use ($data, $company, $taxes, $currency) {
            $inv = Invoice::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $data['client_id'],
                'number' => null,
                'currency' => $currency,
                'status' => InvoiceStatus::Draft,
                'due_date' => $data['due_date'] ?? null,
                'subtotal_amount' => $taxes['subtotal'],
                'vat_amount' => $taxes['vat'],
                'fiscal_stamp_amount' => $taxes['stamp'],
                'amount' => $taxes['total'],
            ]);
            foreach ($data['items'] as $row) {
                $line = $row['quantity'] * $row['unit_amount'];
                InvoiceItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'invoice_id' => $inv->id,
                    'title' => flowdesk_line_title($row['title'] ?? null),
                    'description' => (string) ($row['description'] ?? ''),
                    'quantity' => $row['quantity'],
                    'unit_amount' => $row['unit_amount'],
                    'total_amount' => $line,
                    'vat_rate' => $row['vat_rate'],
                ]);
            }
            $this->invoiceRefs->assignNextNumber($inv, $company);

            return $inv;
        });

        return ['created' => true, 'invoice' => ['id' => $invoice->id, 'number' => $invoice->fresh()?->number, 'status' => 'draft', 'amount' => (int) $invoice->amount, 'currency' => $invoice->currency]];
    }

    /** @param  array<string, mixed>  $args */
    private function listTickets(Company $company, array $args): array
    {
        $q = $this->scoped(SupportTicket::query(), $company)->latest();
        if (! empty($args['status'])) {
            $q->where('status', $args['status']);
        }
        if (! empty($args['client_id'])) {
            $q->where('client_id', $args['client_id']);
        }

        return $this->paginate($q, $args, fn (SupportTicket $t) => [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status?->value ?? $t->status,
            'client_id' => $t->client_id,
            'created_at' => $t->created_at?->toIso8601String(),
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createTicket(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
            'client_id' => ['nullable', 'string', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
        ])->validate();

        $ticket = SupportTicket::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'title' => $data['subject'],
            'description' => $data['message'],
            'status' => SupportTicketStatus::Open,
            'client_id' => $data['client_id'] ?? null,
        ]);

        return ['created' => true, 'ticket' => ['id' => $ticket->id, 'title' => $ticket->title, 'status' => $ticket->status?->value]];
    }

    /** @param  array<string, mixed>  $args */
    private function listAppointments(Company $company, array $args): array
    {
        $q = $this->scoped(WorkspaceCalendarEvent::query(), $company)->orderBy('starts_on');
        if (! empty($args['from'])) {
            $q->whereDate('starts_on', '>=', $args['from']);
        }
        if (! empty($args['to'])) {
            $q->whereDate('starts_on', '<=', $args['to']);
        }
        if (! empty($args['client_id'])) {
            $q->where('client_id', $args['client_id']);
        }

        return $this->paginate($q, $args, fn (WorkspaceCalendarEvent $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'kind' => $e->kind,
            'starts_on' => $e->starts_on?->format('Y-m-d'),
            'ends_on' => $e->ends_on?->format('Y-m-d'),
            'client_id' => $e->client_id,
            'meeting_url' => $e->meeting_url ?? null,
        ]);
    }

    /** @param  array<string, mixed>  $args */
    private function createAppointment(Company $company, array $args): array
    {
        $data = Validator::make($args, [
            'title' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'end_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:5000'],
            'client_id' => ['nullable', 'string', Rule::exists('clients', 'id')->where(fn ($q) => $q->where('company_id', $company->id))],
            'kind' => ['nullable', 'string', 'max:32'],
        ])->validate();

        $event = WorkspaceCalendarEvent::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'created_by' => null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'starts_on' => $data['date'],
            'ends_on' => $data['end_date'] ?? null,
            'kind' => $data['kind'] ?? 'appointment',
            'client_id' => $data['client_id'] ?? null,
        ]);

        return ['created' => true, 'appointment' => ['id' => $event->id, 'title' => $event->title, 'starts_on' => $event->starts_on?->format('Y-m-d'), 'kind' => $event->kind]];
    }

    /** @param  array<string, mixed>  $args */
    private function campaignsStats(Company $company, array $args): array
    {
        $campaigns = $this->scoped(EmailMarketingCampaign::query(), $company)
            ->withCount('recipientDeliveries')
            ->latest()
            ->limit(50);

        if (! empty($args['campaign_id'])) {
            $campaigns->whereKey($args['campaign_id']);
        }

        $rows = $campaigns->get()->map(function (EmailMarketingCampaign $c) use ($company) {
            $deliveries = EmailMarketingRecipientDelivery::query()->withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('email_marketing_campaign_id', $c->id);

            $total = (clone $deliveries)->count();
            $sent = (clone $deliveries)->whereNotNull('sent_at')->count();
            $opened = (clone $deliveries)->whereNotNull('first_opened_at')->count();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'status' => $c->status,
                'subject' => $c->subject,
                'sent_at' => $c->sent_at?->toIso8601String(),
                'recipients' => $total,
                'sent' => $sent,
                'opened' => $opened,
                'open_rate' => $sent > 0 ? round($opened / $sent, 4) : 0,
            ];
        })->values();

        return [
            'campaigns' => $rows,
            'totals' => [
                'campaigns' => $rows->count(),
                'sent' => $rows->sum('sent'),
                'opened' => $rows->sum('opened'),
            ],
        ];
    }
}
