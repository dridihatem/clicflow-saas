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
use Illuminate\Support\Str;

class ShopifySyncService
{
    public function __construct(private ThirdPartyRecordImporter $importer) {}

    public function upsertIntegration(Company $company, array $credentials, bool $autoPush = true): CommerceIntegration
    {
        return CommerceIntegration::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $company->id, 'provider' => 'shopify'],
            [
                'credentials' => [
                    'shop_domain' => trim((string) ($credentials['shop_domain'] ?? '')),
                    'access_token' => trim((string) ($credentials['access_token'] ?? '')),
                ],
                'auto_push' => $autoPush,
            ],
        );
    }

    public function pullProducts(Company $company): int
    {
        $integration = $this->integration($company);
        if (! $integration) {
            $this->log($company, 'pull', 'skipped', 'Shopify not configured');

            return 0;
        }

        $creds = $integration->credentials ?? [];
        $domain = rtrim((string) ($creds['shop_domain'] ?? ''), '/');
        $token = (string) ($creds['access_token'] ?? '');
        if ($domain === '' || $token === '') {
            $this->log($company, 'pull', 'failed', 'Missing Shopify credentials');

            return 0;
        }

        $url = (Str::startsWith($domain, 'http') ? $domain : 'https://'.$domain)
            .'/admin/api/2024-01/products.json?limit=50';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            $this->log($company, 'pull', 'failed', 'Shopify pull failed', $response->json());

            return 0;
        }

        $count = 0;
        foreach ((array) data_get($response->json(), 'products', []) as $remote) {
            if (! is_array($remote)) {
                continue;
            }
            $classified = CatalogKindMatcher::fromShopify($remote);
            $product = Product::query()->withoutGlobalScopes()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'external_shopify_id' => (string) data_get($remote, 'id'),
                ],
                [
                    'name' => (string) data_get($remote, 'title', 'Shopify product'),
                    'kind' => $classified['kind'] === ProductKind::Service->value
                        ? ProductKind::Service
                        : ProductKind::Product,
                    'unit' => $classified['unit'],
                    'description' => strip_tags((string) data_get($remote, 'body_html', '')),
                    'currency' => strtoupper((string) ($company->default_currency ?: flowdesk_default_currency())),
                    'is_active' => data_get($remote, 'status') !== 'archived',
                    'track_stock' => $classified['track_stock'],
                    'base_price_minor' => 0,
                ],
            );

            foreach ((array) data_get($remote, 'variants', []) as $remoteVariant) {
                $priceMajor = (float) data_get($remoteVariant, 'price', 0);
                ProductVariant::query()->withoutGlobalScopes()->updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'external_shopify_id' => (string) data_get($remoteVariant, 'id'),
                    ],
                    [
                        'product_id' => $product->id,
                        'sku' => data_get($remoteVariant, 'sku') ?: null,
                        'color' => data_get($remoteVariant, 'option1'),
                        'size' => data_get($remoteVariant, 'option2'),
                        'price_minor' => (int) round($priceMajor * 100),
                        'stock_qty' => (int) data_get($remoteVariant, 'inventory_quantity', 0),
                    ],
                );
                $count++;
            }

            if ($product->base_price_minor === 0) {
                $first = $product->variants()->first();
                if ($first) {
                    $product->forceFill(['base_price_minor' => $first->unitPriceMinor()])->save();
                }
            }
        }

        $integration->forceFill(['last_synced_at' => now()])->save();
        $this->log($company, 'pull', 'ok', "Pulled {$count} Shopify variants");

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
        $domain = rtrim((string) ($creds['shop_domain'] ?? ''), '/');
        $token = (string) ($creds['access_token'] ?? '');
        if ($domain === '' || $token === '') {
            return ['created' => 0, 'updated' => 0, 'errors' => []];
        }

        $url = (Str::startsWith($domain, 'http') ? $domain : 'https://'.$domain)
            .'/admin/api/2024-01/customers.json?limit=50';

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
        ])->timeout(30)->get($url);

        if (! $response->successful()) {
            $this->log($company, 'pull', 'failed', 'Shopify customer pull failed', $response->json());

            return ['created' => 0, 'updated' => 0, 'errors' => [__('shopify_customers_failed')]];
        }

        $rows = [];
        foreach ((array) data_get($response->json(), 'customers', []) as $remote) {
            $name = trim(trim((string) data_get($remote, 'first_name', '')).' '.trim((string) data_get($remote, 'last_name', '')));
            $email = trim((string) data_get($remote, 'email', ''));
            if ($name === '') {
                $name = trim((string) data_get($remote, 'default_address.name', $email));
            }
            if ($name === '' && $email === '') {
                continue;
            }
            $rows[] = [
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'phone' => data_get($remote, 'phone', data_get($remote, 'default_address.phone')),
            ];
        }

        $result = $this->importer->importClients($company, $rows);
        $this->log($company, 'pull', 'ok', 'Pulled '.($result['created'] + $result['updated']).' Shopify customers');

        return $result;
    }

    public function pushVariant(Company $company, ProductVariant $variant): bool
    {
        $integration = $this->integration($company);
        if (! $integration) {
            $this->log($company, 'push', 'skipped', 'Shopify not configured');

            return false;
        }

        $creds = $integration->credentials ?? [];
        $domain = rtrim((string) ($creds['shop_domain'] ?? ''), '/');
        $token = (string) ($creds['access_token'] ?? '');
        $externalId = $variant->external_shopify_id;

        if ($domain === '' || $token === '' || ! $externalId) {
            $this->log($company, 'push', 'skipped', 'Variant has no Shopify id');

            return false;
        }

        $url = (Str::startsWith($domain, 'http') ? $domain : 'https://'.$domain)
            .'/admin/api/2024-01/variants/'.$externalId.'.json';

        $price = number_format($variant->unitPriceMinor() / 100, 2, '.', '');
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->timeout(30)->put($url, [
            'variant' => [
                'id' => $externalId,
                'price' => $price,
                'sku' => $variant->sku,
            ],
        ]);

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
            ->where('provider', 'shopify')
            ->first();
    }

    private function log(Company $company, string $direction, string $status, string $summary, mixed $payload = null): void
    {
        CommerceSyncLog::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'provider' => 'shopify',
            'direction' => $direction,
            'status' => $status,
            'summary' => $summary,
            'payload' => is_array($payload) ? $payload : null,
        ]);
    }
}
