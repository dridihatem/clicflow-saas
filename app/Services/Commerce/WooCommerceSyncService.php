<?php

namespace App\Services\Commerce;

use App\Enums\ProductKind;
use App\Models\CommerceIntegration;
use App\Models\CommerceSyncLog;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Integrations\ThirdPartyRecordImporter;
use App\Support\CatalogKindMatcher;
use Illuminate\Support\Facades\Http;

class WooCommerceSyncService
{
    public function __construct(private ThirdPartyRecordImporter $importer) {}

    public function upsertIntegration(Company $company, array $credentials, bool $autoPush = true): CommerceIntegration
    {
        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => 'woocommerce'],
            [
                'credentials' => [
                    'store_url' => rtrim(trim((string) ($credentials['store_url'] ?? '')), '/'),
                    'consumer_key' => trim((string) ($credentials['consumer_key'] ?? '')),
                    'consumer_secret' => trim((string) ($credentials['consumer_secret'] ?? '')),
                ],
                'auto_push' => $autoPush,
            ],
        );
    }

    public function pullProducts(Company $company): int
    {
        $integration = $this->integration($company);
        if (! $integration) {
            $this->log($company, 'pull', 'skipped', 'WooCommerce not configured');

            return 0;
        }

        $creds = $integration->credentials ?? [];
        $base = (string) ($creds['store_url'] ?? '');
        $key = (string) ($creds['consumer_key'] ?? '');
        $secret = (string) ($creds['consumer_secret'] ?? '');
        if ($base === '' || $key === '' || $secret === '') {
            $this->log($company, 'pull', 'failed', 'Missing WooCommerce credentials');

            return 0;
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->get($base.'/wp-json/wc/v3/products', ['per_page' => 50]);

        if (! $response->successful()) {
            $this->log($company, 'pull', 'failed', 'WooCommerce pull failed', $response->json());

            return 0;
        }

        $count = 0;
        foreach ((array) $response->json() as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $classified = CatalogKindMatcher::fromWooCommerce($remote);
            $product = Product::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'external_woo_id' => (string) data_get($remote, 'id'),
                ],
                [
                    'name' => (string) data_get($remote, 'name', 'Woo product'),
                    'kind' => $classified['kind'] === ProductKind::Service->value
                        ? ProductKind::Service
                        : ProductKind::Product,
                    'unit' => $classified['unit'],
                    'description' => strip_tags((string) data_get($remote, 'description', '')),
                    'sku' => data_get($remote, 'sku') ?: null,
                    'currency' => strtoupper((string) ($company->default_currency ?: flowdesk_default_currency())),
                    'is_active' => data_get($remote, 'status') === 'publish',
                    'track_stock' => $classified['track_stock'],
                    'base_price_minor' => (int) round(((float) data_get($remote, 'price', 0)) * 100),
                ],
            );

            $variations = (array) data_get($remote, 'variations', []);
            if ($variations === []) {
                ProductVariant::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'product_id' => $product->id,
                        'external_woo_id' => (string) data_get($remote, 'id'),
                    ],
                    [
                        'sku' => data_get($remote, 'sku') ?: null,
                        'price_minor' => (int) round(((float) data_get($remote, 'price', 0)) * 100),
                        'stock_qty' => (int) data_get($remote, 'stock_quantity', 0),
                    ],
                );
                $count++;
            } else {
                foreach ($variations as $variationId) {
                    $varRes = Http::withBasicAuth($key, $secret)
                        ->acceptJson()
                        ->timeout(30)
                        ->get($base.'/wp-json/wc/v3/products/'.$remote['id'].'/variations/'.$variationId);

                    if (! $varRes->successful()) {
                        continue;
                    }

                    $remoteVariant = $varRes->json();
                    $attrs = collect((array) data_get($remoteVariant, 'attributes', []));
                    ProductVariant::query()->withoutGlobalScopes()->updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'external_woo_id' => (string) data_get($remoteVariant, 'id'),
                        ],
                        [
                            'product_id' => $product->id,
                            'sku' => data_get($remoteVariant, 'sku') ?: null,
                            'color' => optional($attrs->firstWhere('name', 'Color'))['option'] ?? optional($attrs->first())['option'] ?? null,
                            'size' => optional($attrs->firstWhere('name', 'Size'))['option'] ?? null,
                            'price_minor' => (int) round(((float) data_get($remoteVariant, 'price', 0)) * 100),
                            'stock_qty' => (int) data_get($remoteVariant, 'stock_quantity', 0),
                        ],
                    );
                    $count++;
                }
            }
        }

        $integration->forceFill(['last_synced_at' => now()])->save();
        $this->log($company, 'pull', 'ok', "Pulled {$count} WooCommerce variants");

        return $count;
    }

    /**
     * @return array{products: int, clients: array{created: int, updated: int, errors: list<string>}}
     */
    public function pullAll(Company $company): array
    {
        $products = $this->pullProducts($company);
        $clients = $this->pullCustomers($company);

        return [
            'products' => $products,
            'clients' => $clients,
        ];
    }

    /**
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function pullCustomers(Company $company): array
    {
        $integration = $this->integration($company);
        if (! $integration) {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        $creds = $integration->credentials ?? [];
        $base = (string) ($creds['store_url'] ?? '');
        $key = (string) ($creds['consumer_key'] ?? '');
        $secret = (string) ($creds['consumer_secret'] ?? '');
        if ($base === '' || $key === '' || $secret === '') {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->get($base.'/wp-json/wc/v3/customers', ['per_page' => 50]);

        if (! $response->successful()) {
            $this->log($company, 'pull', 'failed', 'WooCommerce customer pull failed', $response->json());

            return ['created' => 0, 'updated' => 0, 'errors' => [__('woocommerce_customers_failed')]];
        }

        $rows = [];
        foreach ((array) $response->json() as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $name = trim(trim((string) data_get($remote, 'first_name', '')).' '.trim((string) data_get($remote, 'last_name', '')));
            $companyName = trim((string) data_get($remote, 'billing.company', ''));
            $email = trim((string) data_get($remote, 'email', ''));
            if ($name === '') {
                $name = $companyName !== '' ? $companyName : $email;
            }
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[] = [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'phone' => data_get($remote, 'billing.phone', data_get($remote, 'phone')),
            ];
        }

        $result = $this->importer->importClients($company, $rows);
        $this->log($company, 'pull', 'ok', 'Pulled '.($result['created'] + $result['updated']).' WooCommerce customers');

        return $result;
    }

    public function pushVariant(Company $company, ProductVariant $variant): bool
    {
        $integration = $this->integration($company);
        if (! $integration) {
            $this->log($company, 'push', 'skipped', 'WooCommerce not configured');

            return false;
        }

        $creds = $integration->credentials ?? [];
        $base = (string) ($creds['store_url'] ?? '');
        $key = (string) ($creds['consumer_key'] ?? '');
        $secret = (string) ($creds['consumer_secret'] ?? '');
        $externalId = $variant->external_woo_id;
        $productExternal = $variant->product?->external_woo_id;

        if ($base === '' || $key === '' || $secret === '' || ! $externalId) {
            $this->log($company, 'push', 'skipped', 'Variant has no WooCommerce id');

            return false;
        }

        $payload = [
            'regular_price' => number_format($variant->unitPriceMinor() / 100, 2, '.', ''),
            'sku' => $variant->sku,
            'stock_quantity' => $variant->stock_qty,
            'manage_stock' => true,
        ];

        if ($productExternal && $productExternal !== $externalId) {
            $url = $base.'/wp-json/wc/v3/products/'.$productExternal.'/variations/'.$externalId;
        } else {
            $url = $base.'/wp-json/wc/v3/products/'.$externalId;
        }

        $response = Http::withBasicAuth($key, $secret)
            ->acceptJson()
            ->timeout(30)
            ->put($url, $payload);

        $ok = $response->successful();
        $this->log(
            $company,
            'push',
            $ok ? 'ok' : 'failed',
            $ok ? 'Pushed variant '.$variant->id : 'Push failed',
            $response->json(),
        );

        return $ok;
    }

    private function integration(Company $company): ?CommerceIntegration
    {
        return CommerceIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('provider', 'woocommerce')
            ->first();
    }

    private function log(Company $company, string $direction, string $status, string $summary, mixed $payload = null): void
    {
        CommerceSyncLog::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'woocommerce',
            'direction' => $direction,
            'status' => $status,
            'summary' => $summary,
            'payload' => is_array($payload) ? $payload : null,
        ]);
    }
}
