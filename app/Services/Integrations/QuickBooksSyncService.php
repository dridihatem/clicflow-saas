<?php

namespace App\Services\Integrations;

use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class QuickBooksSyncService
{
    public const PROVIDER = 'quickbooks';

    public function __construct(private ThirdPartyRecordImporter $importer) {}

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     */
    public function upsertIntegration(Company $company, array $credentials, array $settings = []): CommerceIntegration
    {
        $existing = $this->integration($company);
        $current = $existing?->credentials ?? [];

        $refresh = trim((string) ($credentials['refresh_token'] ?? ''));
        $access = trim((string) ($credentials['access_token'] ?? ''));
        $secret = trim((string) ($credentials['client_secret'] ?? ''));

        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => self::PROVIDER],
            [
                'credentials' => [
                    'client_id' => trim((string) ($credentials['client_id'] ?? $current['client_id'] ?? '')),
                    'client_secret' => $secret !== '' ? $secret : (string) ($current['client_secret'] ?? ''),
                    'realm_id' => trim((string) ($credentials['realm_id'] ?? $current['realm_id'] ?? '')),
                    'refresh_token' => $refresh !== '' ? $refresh : (string) ($current['refresh_token'] ?? ''),
                    'access_token' => $access !== '' ? $access : (string) ($current['access_token'] ?? ''),
                    'environment' => in_array(($credentials['environment'] ?? 'production'), ['sandbox', 'production'], true)
                        ? (string) ($credentials['environment'] ?? 'production')
                        : 'production',
                ],
                'settings' => [
                    'sync_clients' => (bool) ($settings['sync_clients'] ?? true),
                    'sync_invoices' => (bool) ($settings['sync_invoices'] ?? true),
                    'sync_products' => (bool) ($settings['sync_products'] ?? true),
                    'sync_proposals' => (bool) ($settings['sync_proposals'] ?? true),
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

        return trim((string) ($creds['realm_id'] ?? '')) !== ''
            && (
                trim((string) ($creds['access_token'] ?? '')) !== ''
                || (
                    trim((string) ($creds['refresh_token'] ?? '')) !== ''
                    && trim((string) ($creds['client_id'] ?? '')) !== ''
                    && trim((string) ($creds['client_secret'] ?? '')) !== ''
                )
            );
    }

    public function testConnection(Company $company): string
    {
        $response = $this->query($company, 'select * from CompanyInfo');
        if (! $response->successful()) {
            throw new RuntimeException(__('quickbooks_connection_failed'));
        }

        $name = (string) data_get($response->json(), 'QueryResponse.CompanyInfo.0.CompanyName', 'QuickBooks');

        return $name !== '' ? $name : 'QuickBooks';
    }

    /**
     * @return array{clients: array{created: int, updated: int, errors: list<string>}, invoices: array{created: int, updated: int, errors: list<string>}, products: array{created: int, updated: int, errors: list<string>}, proposals: array{created: int, updated: int, errors: list<string>}}
     */
    public function pull(Company $company, User $user): array
    {
        unset($user);

        if (! $this->isConfigured($company)) {
            $this->log($company, 'pull', 'skipped', __('quickbooks_not_configured'));

            throw new RuntimeException(__('quickbooks_not_configured'));
        }

        $settings = $this->integration($company)?->settings ?? [];
        $result = [
            'clients' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'invoices' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'products' => ['created' => 0, 'updated' => 0, 'errors' => []],
            'proposals' => ['created' => 0, 'updated' => 0, 'errors' => []],
        ];

        try {
            if ($settings['sync_clients'] ?? true) {
                $result['clients'] = $this->importer->importClients($company, $this->clientRows($company));
            }
            if ($settings['sync_products'] ?? true) {
                $result['products'] = $this->importer->importProducts($company, $this->productRows($company));
            }
            if ($settings['sync_invoices'] ?? true) {
                $result['invoices'] = $this->importer->importInvoices($company, $this->invoiceRows($company));
            }
            if ($settings['sync_proposals'] ?? true) {
                $result['proposals'] = $this->importer->importProposals($company, $this->estimateRows($company));
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
    private function clientRows(Company $company): array
    {
        $rows = [];
        foreach ($this->entities($company, 'Customer') as $remote) {
            $name = trim((string) data_get($remote, 'DisplayName', data_get($remote, 'CompanyName', '')));
            $email = trim((string) data_get($remote, 'PrimaryEmailAddr.Address', ''));
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[] = [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'phone' => data_get($remote, 'PrimaryPhone.FreeFormNumber', data_get($remote, 'Mobile.FreeFormNumber')),
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
        foreach ($this->entities($company, 'Item') as $remote) {
            $name = trim((string) data_get($remote, 'Name', ''));
            if ($name === '') {
                continue;
            }
            $type = strtolower((string) data_get($remote, 'Type', ''));
            $rows[] = [
                'name' => $name,
                'sku' => data_get($remote, 'Sku'),
                'price' => data_get($remote, 'UnitPrice', 0),
                'description' => data_get($remote, 'Description'),
                'is_service' => in_array($type, ['service', 'noninventory'], true),
                'track_stock' => $type === 'inventory',
                'stock_qty' => data_get($remote, 'QtyOnHand', 0),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceRows(Company $company): array
    {
        return $this->documentRows($company, 'Invoice', 'invoice');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function estimateRows(Company $company): array
    {
        return $this->documentRows($company, 'Estimate', 'proposal');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function documentRows(Company $company, string $entity, string $kind): array
    {
        $rows = [];
        foreach ($this->entities($company, $entity) as $remote) {
            $ref = trim((string) data_get($remote, 'DocNumber', data_get($remote, 'Id', '')));
            $clientName = trim((string) data_get($remote, 'CustomerRef.name', ''));
            $clientEmail = '';
            $lines = data_get($remote, 'Line', []);
            if (! is_array($lines) || $lines === []) {
                $lines = [[
                    'Description' => $entity,
                    'Amount' => data_get($remote, 'TotalAmt', 0),
                    'Qty' => 1,
                ]];
            }

            foreach ($lines as $index => $line) {
                if (! is_array($line) || (string) data_get($line, 'DetailType') === 'SubTotalLineDetail') {
                    continue;
                }
                $desc = trim((string) data_get($line, 'Description', data_get($line, 'SalesItemLineDetail.ItemRef.name', $entity)));
                if ($desc === '') {
                    continue;
                }
                $qty = (int) data_get($line, 'SalesItemLineDetail.Qty', data_get($line, 'Qty', 1));
                $unit = data_get($line, 'SalesItemLineDetail.UnitPrice');
                if ($unit === null) {
                    $unit = $qty > 0 ? ((float) data_get($line, 'Amount', 0)) / max(1, $qty) : data_get($line, 'Amount', 0);
                }
                $row = [
                    'ref' => $ref !== '' ? $ref : 'qb-'.$kind.'-'.((string) data_get($remote, 'Id', $index)),
                    'client_email' => $clientEmail,
                    'client_name' => $clientName,
                    'currency' => data_get($remote, 'CurrencyRef.value'),
                    'status' => 'draft',
                    'description' => $desc,
                    'quantity' => max(1, $qty),
                    'unit_price' => $unit,
                ];
                if ($kind === 'invoice') {
                    $row['due_date'] = data_get($remote, 'DueDate');
                } else {
                    $row['name'] = (string) data_get($remote, 'DocNumber', __('Quote'));
                    $row['valid_until'] = data_get($remote, 'ExpirationDate');
                }
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function entities(Company $company, string $entity): array
    {
        $response = $this->query($company, 'select * from '.$entity.' maxresults 200');
        if (! $response->successful()) {
            throw new RuntimeException(__('quickbooks_http_error', ['status' => $response->status(), 'entity' => $entity]));
        }

        $list = data_get($response->json(), 'QueryResponse.'.$entity, []);

        return is_array($list) ? array_values(array_filter($list, 'is_array')) : [];
    }

    private function query(Company $company, string $sql): Response
    {
        $token = $this->accessToken($company);
        $creds = $this->integration($company)?->credentials ?? [];
        $realm = (string) ($creds['realm_id'] ?? '');
        $base = (($creds['environment'] ?? 'production') === 'sandbox')
            ? 'https://sandbox-quickbooks.api.intuit.com'
            : 'https://quickbooks.api.intuit.com';

        return Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->get($base.'/v3/company/'.$realm.'/query', ['query' => $sql]);
    }

    private function accessToken(Company $company): string
    {
        $integration = $this->integration($company);
        $creds = $integration?->credentials ?? [];
        $existing = trim((string) ($creds['access_token'] ?? ''));
        $refresh = trim((string) ($creds['refresh_token'] ?? ''));
        $clientId = trim((string) ($creds['client_id'] ?? ''));
        $clientSecret = trim((string) ($creds['client_secret'] ?? ''));

        if ($refresh === '' || $clientId === '' || $clientSecret === '') {
            if ($existing === '') {
                throw new RuntimeException(__('quickbooks_not_configured'));
            }

            return $existing;
        }

        $response = Http::asForm()
            ->withBasicAuth($clientId, $clientSecret)
            ->timeout(20)
            ->post('https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refresh,
            ]);

        if (! $response->successful()) {
            if ($existing !== '') {
                return $existing;
            }

            throw new RuntimeException(__('quickbooks_token_refresh_failed'));
        }

        $access = trim((string) $response->json('access_token'));
        $newRefresh = trim((string) $response->json('refresh_token'));
        if ($access === '') {
            throw new RuntimeException(__('quickbooks_token_refresh_failed'));
        }

        if ($integration) {
            $creds['access_token'] = $access;
            if ($newRefresh !== '') {
                $creds['refresh_token'] = $newRefresh;
            }
            $integration->forceFill(['credentials' => $creds])->save();
        }

        return $access;
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
