<?php

use App\Enums\ProductKind;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use App\Services\PlanLimitService;
use App\Services\PlatformLlmRouter;
use App\Services\WorkspaceNavigationService;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('commerce products index uses products and services label and filter', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    Product::query()->create([
        'company_id' => $company->id,
        'name' => 'Notebook',
        'kind' => ProductKind::Product,
        'sku' => 'NB-1',
        'base_price_minor' => 1000,
        'currency' => 'USD',
        'track_stock' => true,
        'is_active' => true,
    ]);
    Product::query()->create([
        'company_id' => $company->id,
        'name' => 'Consulting',
        'kind' => ProductKind::Service,
        'sku' => 'SVC-1',
        'base_price_minor' => 50000,
        'currency' => 'USD',
        'track_stock' => false,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('commerce.products.index'))
        ->assertOk()
        ->assertSee(__('Products & services'))
        ->assertDontSee('E-commerce products', false)
        ->assertSee('Notebook')
        ->assertSee('Consulting')
        ->assertSee(__('catalog_kind_product'))
        ->assertSee(__('catalog_kind_service'));

    $this->actingAs($user)
        ->get(route('commerce.products.index', ['kind' => 'service']))
        ->assertOk()
        ->assertSee('Consulting')
        ->assertDontSee('Notebook');
});

test('sales billing nav lists products and services after invoices', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $sections = app(WorkspaceNavigationService::class)
        ->sectionsFor($user, app(PlanLimitService::class)->featureGates($company));

    $sales = collect($sections)->firstWhere('key', 'sales_billing');
    expect($sales)->not->toBeNull();

    $keys = collect($sales['items'])->pluck('key')->values()->all();
    $invoiceAt = array_search('invoices', $keys, true);
    $productsAt = array_search('commerce_products', $keys, true);

    expect($invoiceAt)->toBeInt()
        ->and($productsAt)->toBeInt()
        ->and($productsAt)->toBe($invoiceAt + 1);
});

test('commerce product create shows unit select and ai generate control', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($company);

    $this->actingAs($user)
        ->get(route('commerce.products.create'))
        ->assertOk()
        ->assertSee(__('catalog_ai_generate'))
        ->assertSee(__('catalog_unit_pcs'))
        ->assertSee(__('catalog_unit_hour'))
        ->assertSee(__('catalog_unit_mission'))
        ->assertSee(__('catalog_unit_custom'))
        ->assertSee('productCatalogForm');
});

test('commerce product store persists service unit', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->post(route('commerce.products.store'), [
            'name' => 'Audit mission',
            'kind' => 'service',
            'sku' => 'AUD-1',
            'base_price' => '1200',
            'currency' => 'USD',
            'unit' => 'mission',
            'description' => 'On-site audit.',
        ])
        ->assertRedirect();

    $product = Product::query()->where('sku', 'AUD-1')->first();
    expect($product)->not->toBeNull()
        ->and($product->kind)->toBe(ProductKind::Service)
        ->and($product->unit)->toBe('mission')
        ->and($product->unitLabel())->toBe(__('catalog_unit_mission'));
});

test('commerce products index shows generate ai button and modal form', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($company);

    $this->actingAs($user)
        ->get(route('commerce.products.index'))
        ->assertOk()
        ->assertSee(__('catalog_ai_generate'))
        ->assertSee(__('catalog_ai_bulk_heading'))
        ->assertSee(__('catalog_ai_bulk_generate'))
        ->assertSee('role="dialog"', false)
        ->assertSee(route('commerce.products.ai-catalog'), false);
});

test('commerce product ai catalog creates products with size color variants and services', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($company);

    $this->mock(PlatformLlmRouter::class, function ($mock) {
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('completeFast')->andReturn([
            'suggestion' => json_encode([
                'items' => [
                    [
                        'kind' => 'product',
                        'name' => 'Cotton T-shirt',
                        'description' => 'Soft everyday tee.',
                        'unit' => 'pcs',
                        'sku' => 'TEE-1',
                        'price_major' => '25.00',
                        'colors' => ['Black', 'White'],
                        'sizes' => ['S', 'M', 'L'],
                        'track_stock' => true,
                    ],
                    [
                        'kind' => 'product',
                        'name' => 'Running shoes',
                        'description' => 'Light trainers for daily runs.',
                        'unit' => 'pair',
                        'sku' => 'SHOE-1',
                        'price_major' => '89.00',
                        'colors' => ['Black', 'Red'],
                        'sizes' => ['40', '41'],
                        'track_stock' => true,
                    ],
                    [
                        'kind' => 'service',
                        'name' => 'Logo design',
                        'description' => 'Brand identity for a new collection.',
                        'unit' => 'project',
                        'sku' => 'LOGO-1',
                        'price_major' => '450.00',
                        'colors' => [],
                        'sizes' => [],
                        'track_stock' => false,
                    ],
                ],
            ]),
            'model' => 'test-model',
            'input_tokens' => 10,
            'output_tokens' => 40,
            'total_tokens' => 50,
        ]);
    });

    $this->actingAs($user)
        ->post(route('commerce.products.ai-catalog'), [
            'brief' => 'I have t-shirts and shoes with size and color, plus logo design as a service.',
        ])
        ->assertRedirect(route('commerce.products.index'))
        ->assertSessionHas('status');

    $tee = Product::query()->where('sku', 'TEE-1')->first();
    $shoes = Product::query()->where('sku', 'SHOE-1')->first();
    $logo = Product::query()->where('sku', 'LOGO-1')->first();

    expect($tee)->not->toBeNull()
        ->and($tee->kind)->toBe(ProductKind::Product)
        ->and($tee->variants)->toHaveCount(6)
        ->and($tee->variants->pluck('color')->unique()->sort()->values()->all())->toBe(['Black', 'White'])
        ->and($tee->variants->pluck('size')->unique()->sort()->values()->all())->toBe(['L', 'M', 'S']);

    expect($shoes)->not->toBeNull()
        ->and($shoes->kind)->toBe(ProductKind::Product)
        ->and($shoes->unit)->toBe('pair')
        ->and($shoes->variants)->toHaveCount(4);

    expect($logo)->not->toBeNull()
        ->and($logo->kind)->toBe(ProductKind::Service)
        ->and($logo->unit)->toBe('project')
        ->and($logo->track_stock)->toBeFalse()
        ->and($logo->variants)->toHaveCount(1)
        ->and($logo->variants->first()->color)->toBeNull()
        ->and($logo->variants->first()->size)->toBeNull();
});

test('commerce product ai copy fills title description and unit', function () {
    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id]);
    $user->assignRole('company_admin');
    seedPaidPremiumTtsForCompany($company);

    $this->mock(PlatformLlmRouter::class, function ($mock) {
        $mock->shouldReceive('isAvailable')->andReturn(true);
        $mock->shouldReceive('completeFast')->andReturn([
            'suggestion' => json_encode([
                'name' => 'Monthly WordPress care',
                'description' => 'Keep your site updated, backed up, and monitored.',
                'unit' => 'month',
            ]),
            'model' => 'test-model',
            'input_tokens' => 10,
            'output_tokens' => 20,
            'total_tokens' => 30,
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('commerce.products.ai-copy'), [
            'brief' => 'Monthly WordPress maintenance for SMEs with backups.',
            'kind' => 'service',
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Monthly WordPress care')
        ->assertJsonPath('description', 'Keep your site updated, backed up, and monitored.')
        ->assertJsonPath('unit', 'month');
});
