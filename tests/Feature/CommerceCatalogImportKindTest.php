<?php

use App\Enums\ProductKind;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Models\Product;
use App\Services\Commerce\ShopifySyncService;
use App\Services\Commerce\WooCommerceSyncService;
use App\Services\DataImportService;
use Illuminate\Support\Facades\Http;

test('woocommerce pull stores products and services with matching kinds', function () {
    $company = Company::factory()->create(['default_currency' => 'USD']);
    CommerceIntegration::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'provider' => 'woocommerce',
        'credentials' => [
            'store_url' => 'https://shop.example.test',
            'consumer_key' => 'ck',
            'consumer_secret' => 'cs',
        ],
        'auto_push' => false,
    ]);

    Http::fake([
        'https://shop.example.test/wp-json/wc/v3/products*' => Http::response([
            [
                'id' => 11,
                'name' => 'Cotton T-shirt',
                'type' => 'simple',
                'virtual' => false,
                'sku' => 'TEE-W',
                'price' => '25.00',
                'status' => 'publish',
                'manage_stock' => true,
                'variations' => [],
            ],
            [
                'id' => 12,
                'name' => 'Monthly website maintenance',
                'type' => 'service',
                'virtual' => true,
                'sku' => 'MAINT-W',
                'price' => '80.00',
                'status' => 'publish',
                'manage_stock' => false,
                'variations' => [],
            ],
        ]),
    ]);

    $count = app(WooCommerceSyncService::class)->pullProducts($company);

    expect($count)->toBe(2);

    $tee = Product::query()->withoutGlobalScopes()->where('sku', 'TEE-W')->first();
    $maint = Product::query()->withoutGlobalScopes()->where('sku', 'MAINT-W')->first();

    expect($tee)->not->toBeNull()
        ->and($tee->kind)->toBe(ProductKind::Product)
        ->and($tee->unit)->toBe('pcs')
        ->and($tee->track_stock)->toBeTrue();

    expect($maint)->not->toBeNull()
        ->and($maint->kind)->toBe(ProductKind::Service)
        ->and($maint->unit)->toBe('hour')
        ->and($maint->track_stock)->toBeFalse();
});

test('shopify pull classifies service product types', function () {
    $company = Company::factory()->create(['default_currency' => 'USD']);
    CommerceIntegration::query()->withoutGlobalScopes()->create([
        'company_id' => $company->id,
        'provider' => 'shopify',
        'credentials' => [
            'shop_domain' => 'acme.myshopify.com',
            'access_token' => 'shpat-test',
        ],
        'auto_push' => false,
    ]);

    Http::fake([
        'https://acme.myshopify.com/admin/api/2024-01/products.json*' => Http::response([
            'products' => [[
                'id' => 88,
                'title' => 'Logo design',
                'body_html' => '<p>Brand identity</p>',
                'product_type' => 'Service',
                'status' => 'active',
                'tags' => 'design',
                'variants' => [[
                    'id' => 99,
                    'sku' => 'LOGO-S',
                    'price' => '450.00',
                    'requires_shipping' => false,
                    'inventory_quantity' => 0,
                ]],
            ]],
        ]),
    ]);

    app(ShopifySyncService::class)->pullProducts($company);

    $logo = Product::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->where('external_shopify_id', '88')
        ->first();

    expect($logo)->not->toBeNull()
        ->and($logo->kind)->toBe(ProductKind::Service)
        ->and($logo->track_stock)->toBeFalse();
});

test('data import persists explicit service kind onto the catalog', function () {
    $company = Company::factory()->create(['default_currency' => 'USD']);

    $result = app(DataImportService::class)->importProducts($company, [[
        'name' => 'Audit mission',
        'sku' => 'AUD-IMP',
        'price' => '1200',
        'is_service' => true,
        'unit' => 'mission',
    ]]);

    expect($result['created'])->toBe(1);

    $product = Product::query()->withoutGlobalScopes()->where('sku', 'AUD-IMP')->first();
    expect($product)->not->toBeNull()
        ->and($product->kind)->toBe(ProductKind::Service)
        ->and($product->unit)->toBe('mission')
        ->and($product->track_stock)->toBeFalse();
});
