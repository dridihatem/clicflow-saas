<?php

use App\Enums\MarketplaceModuleBillingPeriod;
use App\Enums\MarketplaceModuleCategory;
use App\Enums\MarketplaceOrderStatus;
use App\Enums\RealEstatePropertyType;
use App\Enums\RealEstateTransactionType;
use App\Models\MarketplaceModule;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RealEstateOffer;
use App\Models\User;
use App\Services\MarketplaceOrderFulfillmentService;
use App\Services\ModuleEntitlementService;
use App\Services\PlanLimitService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);
});

function grantModule(User $user, string $key): void
{
    app(ModuleEntitlementService::class)->grant($user->company, $key, 'marketplace');
}

test('marketplace purchase grants first-party ecommerce entitlement', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $module = MarketplaceModule::query()->create([
        'slug' => 'ecommerce',
        'name' => 'E-commerce',
        'description' => 'Test',
        'category' => MarketplaceModuleCategory::Ecommerce,
        'price_minor' => 4900,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => true,
        'sort_order' => 1,
    ]);

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-ECOM-01',
        'status' => MarketplaceOrderStatus::Paid,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'user_id' => $user->id,
        'company_id' => $user->company_id,
        'total_minor' => 4900,
        'currency' => 'USD',
        'paid_at' => now(),
        'metadata' => [],
    ]);

    MarketplaceOrderItem::query()->create([
        'marketplace_order_id' => $order->id,
        'marketplace_module_id' => $module->id,
        'module_slug' => $module->slug,
        'module_name' => $module->name,
        'price_minor' => 4900,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly->value,
    ]);

    app(MarketplaceOrderFulfillmentService::class)->fulfill($order->fresh(['items.module', 'user.company', 'company']));

    expect(app(ModuleEntitlementService::class)->isEntitled($user->company, 'ecommerce'))->toBeTrue()
        ->and(app(ModuleEntitlementService::class)->isEntitled($user->company, 'pos'))->toBeTrue()
        ->and(app(PlanLimitService::class)->isFeatureEnabled($user->company->fresh(), 'ecommerce'))->toBeTrue()
        ->and(app(PlanLimitService::class)->isFeatureEnabled($user->company->fresh(), 'pos'))->toBeTrue();

    $this->actingAs($user)
        ->get(route('commerce.products.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('pos.index'))
        ->assertOk()
        ->assertSee(__('Register'));
});

test('pos requires ecommerce products module before selling', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    grantModule($user, 'pos');

    $plan = \App\Models\Plan::query()->firstOrFail();
    \App\Models\Subscription::factory()->create([
        'company_id' => $user->company_id,
        'plan_id' => $plan->id,
        'status' => 'active',
    ]);

    expect(app(PlanLimitService::class)->isFeatureEnabled($user->company->fresh(), 'ecommerce'))->toBeFalse();

    $this->actingAs($user)
        ->get(route('pos.index'))
        ->assertOk()
        ->assertSee(__('Products module required'))
        ->assertSee(__('Get E-commerce pack'));

    $this->actingAs($user)
        ->post(route('pos.open'), ['opening_cash' => '10'])
        ->assertSessionHasErrors('pos');
});

test('pos sale decrements stock and can create invoice', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    grantModule($user, 'pos');
    grantModule($user, 'ecommerce');

    $product = Product::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Tee',
        'base_price_minor' => 2500,
        'currency' => 'USD',
        'track_stock' => true,
        'is_active' => true,
    ]);

    $variant = ProductVariant::query()->create([
        'company_id' => $user->company_id,
        'product_id' => $product->id,
        'color' => 'Red',
        'size' => 'M',
        'stock_qty' => 5,
    ]);

    $this->actingAs($user)
        ->post(route('pos.open'), ['opening_cash' => '50'])
        ->assertRedirect();

    $this->actingAs($user)
        ->post(route('pos.sell'), [
            'lines' => [
                ['variant_id' => $variant->id, 'quantity' => 2],
            ],
            'payment_method' => 'cash',
            'create_invoice' => '1',
        ])
        ->assertRedirect();

    expect($variant->fresh()->stock_qty)->toBe(3)
        ->and(\App\Models\CommerceOrder::query()->where('company_id', $user->company_id)->where('source', 'pos')->exists())->toBeTrue()
        ->and(\App\Models\CommerceOrder::query()->where('company_id', $user->company_id)->whereNotNull('invoice_id')->exists())->toBeTrue();
});

test('real estate offer can be created when entitled', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    grantModule($user, 'real_estate');

    $this->actingAs($user)
        ->post(route('real-estate.offers.store'), [
            'title' => 'Duplex Lac',
            'property_type' => RealEstatePropertyType::Duplex->value,
            'transaction_type' => RealEstateTransactionType::Vente->value,
            'price' => '250000',
            'currency' => 'USD',
            'address' => 'Tunis',
            'city' => 'Tunis',
            'status' => 'published',
            'description' => 'Nice duplex',
        ])
        ->assertRedirect();

    expect(RealEstateOffer::query()->where('company_id', $user->company_id)->where('title', 'Duplex Lac')->exists())->toBeTrue();
});

test('shopify push logs skipped when not configured', function () {
    Http::fake();

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    grantModule($user, 'shopify');
    grantModule($user, 'ecommerce');

    $product = Product::query()->create([
        'company_id' => $user->company_id,
        'name' => 'Hat',
        'base_price_minor' => 1500,
        'currency' => 'USD',
        'track_stock' => true,
        'is_active' => true,
    ]);
    $variant = ProductVariant::query()->create([
        'company_id' => $user->company_id,
        'product_id' => $product->id,
        'stock_qty' => 3,
        'external_shopify_id' => '99',
    ]);

    $ok = app(\App\Services\Commerce\ShopifySyncService::class)->pushVariant($user->company, $variant);
    expect($ok)->toBeFalse()
        ->and(\App\Models\CommerceSyncLog::query()->where('company_id', $user->company_id)->where('provider', 'shopify')->exists())->toBeTrue();
});

test('business companion zip installs but stays hidden from modules features UI and nav', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $zip = base_path('storage/stubs/business/zips/real-estate.zip');
    expect(is_file($zip))->toBeTrue();

    $installed = app(\App\Services\ModuleInstallerService::class)
        ->installFromStoredZip($user->company, $zip);

    expect($installed->slug)->toBe('real-estate')
        ->and(app(ModuleEntitlementService::class)->isCoreBundledSlug('real-estate'))->toBeTrue()
        ->and(\App\Models\InstalledModule::query()
            ->where('company_id', $user->company_id)
            ->where('slug', 'real-estate')
            ->where('is_enabled', true)
            ->exists())->toBeTrue();

    $navSlugs = collect(app(\App\Services\ModuleRegistry::class)->navItemsFor($user->company))->pluck('slug')->all();
    expect($navSlugs)->not->toContain('real-estate');

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertSee(__('settings_modules_empty'))
        ->assertDontSee('real-estate · v');
});

test('marketplace purchase of core module grants entitlement without listing as CRM module', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $module = MarketplaceModule::query()->create([
        'slug' => 'shopify-sync',
        'name' => 'Shopify Sync Pack',
        'description' => 'Test',
        'category' => MarketplaceModuleCategory::Ecommerce,
        'price_minor' => 1900,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 2,
    ]);

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-SHOP-01',
        'status' => MarketplaceOrderStatus::Paid,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'user_id' => $user->id,
        'company_id' => $user->company_id,
        'total_minor' => 1900,
        'currency' => 'USD',
        'paid_at' => now(),
        'metadata' => [],
    ]);

    MarketplaceOrderItem::query()->create([
        'marketplace_order_id' => $order->id,
        'marketplace_module_id' => $module->id,
        'module_slug' => $module->slug,
        'module_name' => $module->name,
        'price_minor' => 1900,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime->value,
    ]);

    app(MarketplaceOrderFulfillmentService::class)->fulfill($order->fresh(['items.module', 'user.company', 'company']));

    expect(app(ModuleEntitlementService::class)->isEntitled($user->company, 'shopify'))->toBeTrue()
        ->and(\App\Models\InstalledModule::query()
            ->where('company_id', $user->company_id)
            ->where('slug', 'shopify-sync')
            ->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertSee('Shopify Sync Pack')
        ->assertSee(__('settings_modules_purchased_core_badge'));
});
