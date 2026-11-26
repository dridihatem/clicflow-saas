<?php

namespace App\Services\Integrations;

use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PerfexSyncService
{
    public const PROVIDER = 'perfex';

    public function __construct(private ThirdPartyRecordImporter $importer) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function upsertIntegration(Company $company, array $credentials, array $settings = []): CommerceIntegration
    {
        $existing = $this->integration($company);
        $current = $existing?->credentials ?? [];

        $token = trim((string) ($credentials['api_token'] ?? ''));
        if ($token === '' && is_string($current['api_token'] ?? null)) {
            $token = (string) $current['api_token'];
        }

        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => self::PROVIDER],
            [
                'credentials' => [
                    'base_url' => rtrim(trim((string) ($credentials['base_url'] ?? '')), '/'),
                    'api_token' => $token,
                ],
                'settings' => [
                    'sync_prospects' => (bool) ($settings['sync_prospects'] ?? true),
                    'sync_clients' => (bool) ($settings['sync_clients'] ?? true),
                    'sync_invoices' => (bool) ($settings['sync_invoices'] ?? true),
                    'sync_products' => (bool) ($settings['sync_products'] ?? true),
                ],
                'auto_push' => false,
            ],
        );
    }

    public function disconnect(Company $company): void
    {
        $this->integration($company)?->delete();
    }

    public function isConfigured(Company $company): bool
    {
        $creds = $this->integration($company)?->credentials ?? [];

        return trim((string) ($creds['base_url'] ?? '')) !== ''
            && trim((string) ($creds['api_token'] ?? '')) !== '';
    }

    public function testConnection(Company $company): string
    {
        $response = $this->request($company, 'customers', ['limit' => 1]);
        if (! $response->successful()) {
            throw new RuntimeException(__('perfex_connection_failed'));
        }

        return (string) ($response->header('X-API-Version') ?: 'Perfex CRM');
    }

    /**
     * @return array{prospects: array{created: int, updated: int, errors: list<string>}, clients: array{created: int, updated: int, errors: list<string>}, invoices: array{created: int, updated: int, errors: list<string>}, products: array{created: int, updated: int, errors: list<string>}}
     */
    public function pull(Company $company, User $user): array
    {
        if (! $this->isConfigured($company)) {
            $this->log($company, 'pull', 'skipped', __('perfex_not_configured'));

            throw new RuntimeException(__('perfex_not_configured'));
        }

        $settings = $this->integration($company)?->settings ?? [];
        $result = [
            'prospects' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'clients' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'invoices' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'products' => ['created' => 0, 'updated' => 0, 'errors' => []],
        ];

        try {
            if ($settings['sync_clients'] ?? true) {
                $result['clients'] = $this->importer->importClients($company, $this->clientRows($company));
            }
            if ($settings['sync_prospects'] ?? true) {
                $result['prospects'] = $this->importer->importProspects($company, $user, $this->leadRows($company), 'perfex');
            }
            if ($settings['sync_products'] ?? true) {
                $result['products'] = $this->importer->importProducts($company, $this->productRows($company));
            }
            if ($settings['sync_invoices'] ?? true) {
                $result['invoices'] = $this->importer->importInvoices($company, $this->invoiceRows($company));
            }

            $this->integration($company)?->forceFill(['last_synced_at' => now()])->save();
            $this->log($company, 'pull', 'ok', $this->importer->summarize($result));
        } catch (\Throwable $e) {
            $this->log($company, 'pull', 'failed', $e->getMessage());

            throw $e;
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function leadRows(Company $company): array
    {
        $rows = [];
        foreach ($this->list($company, 'leads') as $remote) {
            $name = trim((string) data_get($remote, 'name', data_get($remote, 'lead_name', '')));
            $email = trim((string) data_get($remote, 'email', ''));
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'email' => $email,
                'phone' => data_get($remote, 'phonenumber', data_get($remote, 'phone')),
                'whatsapp' => data_get($remote, 'whatsapp'),
                'company' => data_get($remote, 'company'),
                'job_title' => data_get($remote, 'title'),
                'source' => 'import',
                'notes' => data_get($remote, 'description', data_get($remote, 'notes')),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clientRows(Company $company): array
    {
        $rows = [];
        foreach ($this->list($company, 'customers') as $remote) {
            $name = trim((string) data_get($remote, 'company', data_get($remote, 'name', '')));
            $email = trim((string) data_get($remote, 'email', data_get($remote, 'primary_email', '')));
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[] = [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'phone' => data_get($remote, 'phonenumber', data_get($remote, 'phone')),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productRows(Company $company): array
    {
        $rows = [];
        foreach ($this->list($company, 'items') as $remote) {
            $name = trim((string) data_get($remote, 'description', data_get($remote, 'name', '')));
            if ($name === '') {
                continue;
            }
            $classified = \App\Support\CatalogKindMatcher::classify([
                'name' => $name,
                'description' => data_get($remote, 'long_description', data_get($remote, 'description')),
                'unit' => data_get($remote, 'unit'),
                'type' => data_get($remote, 'group_name', data_get($remote, 'group_id')),
            ]);
            $rows[] = [
                'name' => $name,
                'sku' => data_get($remote, 'sku', data_get($remote, 'item_code')),
                'price' => data_get($remote, 'rate', data_get($remote, 'price', 0)),
                'description' => data_get($remote, 'long_description', data_get($remote, 'description')),
                'unit' => $classified['unit'],
                'kind' => $classified['kind'],
                'is_service' => $classified['is_service'],
                'track_stock' => $classified['track_stock'],
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceRows(Company $company): array
    {
        $rows = [];
        foreach ($this->list($company, 'invoices') as $remote) {
            $ref = trim((string) data_get($remote, 'number', data_get($remote, 'formatted_number', '')));
            $clientEmail = trim((string) data_get($remote, 'email', data_get($remote, 'client.email', '')));
            $clientName = trim((string) data_get($remote, 'client.company', data_get($remote, 'client.companyname', data_get($remote, 'clientid', ''))));
            $items = data_get($remote, 'items', data_get($remote, 'invoice_items', []));
            if (! is_array($items) || $items === []) {
                $items = [[
                    'description' => data_get($remote, 'description', __('Invoice')),
                    'qty' => 1,
                    'rate' => data_get($remote, 'total', data_get($remote, 'subtotal', 0)),
                ]];
            }

            foreach ($items as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $desc = trim((string) data_get($item, 'description', data_get($item, 'name', '')));
                if ($desc === '') {
                    continue;
                }
                $rows[] = [
                    'ref' => $ref !== '' ? $ref : 'perfex-'.((string) data_get($remote, 'id', $index)),
                    'client_email' => $clientEmail,
                    'client_name' => $clientName,
                    'currency' => data_get($remote, 'currency_name', data_get($remote, 'currency')),
                    'status' => 'draft',
                    'due_date' => data_get($remote, 'duedate', data_get($remote, 'due_date')),
                    'description' => $desc,
                    'quantity' => data_get($item, 'qty', data_get($item, 'quantity', 1)),
                    'unit_price' => data_get($item, 'rate', data_get($item, 'unit_price', 0)),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(Company $company, string $path, array $query = []): Response
    {
        $creds = $this->integration($company)?->credentials ?? [];
        $base = rtrim((string) ($creds['base_url'] ?? ''), '/');
        $token = (string) ($creds['api_token'] ?? '');

        return Http::withHeaders([
            'authtoken' => $token,
            'Accept' => 'application/json',
        ])->timeout(30)->get($base.'/api/'.$path, $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function list(Company $company, string $path, array $query = []): array
    {
        $response = $this->request($company, $path, $query + ['limit' => 200]);
        if (! $response->successful()) {
            throw new RuntimeException(__('perfex_http_error', ['status' => $response->status(), 'path' => $path]));
        }

        $json = $response->json();
        if (is_array($json) && array_is_list($json)) {
            return array_values(array_filter($json, 'is_array'));
        }

        foreach (['data', 'customers', 'leads', 'invoices', 'items'] as $key) {
            $nested = data_get($json, $key);
            if (is_array($nested)) {
                return array_values(array_filter($nested, 'is_array'));
            }
        }

        return [];
    }

    private function integration(Company $company): ?CommerceIntegration
    {
        return CommerceIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', self::PROVIDER)
            ->first();
    }

    private function log(Company $company, string $direction, string $status, string $summary, mixed $payload = null): void
    {
        CommerceSyncLog::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => self::PROVIDER,
            'direction' => $direction,
            'status' => $status,
            'summary' => $summary,
            'payload' => is_array($payload) ? $payload : null,
        ]);
    }
}
