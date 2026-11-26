<?php

use App\Enums\ProductKind;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('invoice and quote forms expose active products and services catalog', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    app(\App\Services\SubscriptionBootstrapService::class)->ensureDefaultSubscription($user->company);

    $product = Product::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Catalog Widget',
        'kind' => ProductKind::Product,
        'sku' => 'WGT-1',
        'description' => 'Blue desktop widget',
        'base_price_minor' => 2500,
        'currency' => 'USD',
        'unit' => 'pcs',
        'track_stock' => true,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'company_id' => $user->company_id,
        'product_id' => $product->id,
        'sku' => 'WGT-1',
        'stock_qty' => 10,
    ]);

    $service = Product::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Setup Service',
        'kind' => ProductKind::Service,
        'sku' => 'SVC-SETUP',
        'description' => 'Onboarding help',
        'base_price_minor' => 15000,
        'currency' => 'USD',
        'unit' => 'hour',
        'track_stock' => false,
        'is_active' => true,
    ]);
    ProductVariant::query()->create([
        'company_id' => $user->company_id,
        'product_id' => $service->id,
        'sku' => 'SVC-SETUP',
        'stock_qty' => 0,
    ]);

    $catalog = flowdesk_document_catalog_for_js(
        Product::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->with('variants')
            ->orderBy('name')
            ->get()
    );

    expect($catalog)->toHaveCount(2)
        ->and(collect($catalog)->pluck('name')->all())->toContain('Catalog Widget', 'Setup Service')
        ->and(collect($catalog)->firstWhere('name', 'Catalog Widget')['description'])->toBe('Blue desktop widget')
        ->and(collect($catalog)->firstWhere('name', 'Setup Service')['kind'])->toBe('service');

    $this->actingAs($user)
        ->get(route('invoices.create'))
        ->assertOk()
        ->assertSee('Catalog Widget', false)
        ->assertSee('Setup Service', false)
        ->assertSee(__('Product / service'), false);

    $this->actingAs($user)
        ->get(route('proposals.create'))
        ->assertOk()
        ->assertSee('Catalog Widget', false)
        ->assertSee('Setup Service', false)
        ->assertSee(__('Product / service'), false);
});
