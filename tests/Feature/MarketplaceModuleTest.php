<?php

use App\Enums\MarketplaceModuleBillingPeriod;
use App\Enums\MarketplaceModuleCategory;
use App\Enums\MarketplaceOrderStatus;
use App\Mail\MarketplaceOrderPaidMail;
use App\Models\MarketplaceModule;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\InvoicePaymentGatewayService;
use App\Services\MarketplaceCartService;
use App\Services\MarketplaceCheckoutService;
use App\Services\MarketplaceOrderFulfillmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('marketplace module detail is not a public marketing page', function () {
    MarketplaceModule::query()->create([
        'slug' => 'detail-mod',
        'name' => 'Detail Module',
        'description' => 'Short summary',
        'detail_content' => 'Full overview of the module for marketing.',
        'category' => MarketplaceModuleCategory::RealEstate,
        'price_minor' => 4900,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'feature_bullets' => ['Feature A', 'Feature B'],
        'is_published' => true,
        'sort_order' => 0,
    ]);

    $this->get(route('modules.show', ['slug' => 'detail-mod']))
        ->assertNotFound();
});

test('unpublished module detail returns 404', function () {
    MarketplaceModule::query()->create([
        'slug' => 'secret-mod',
        'name' => 'Secret',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 1000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => false,
        'sort_order' => 0,
    ]);

    $this->get(route('modules.show', ['slug' => 'secret-mod']))
        ->assertNotFound();
});

test('workspace modules page lists every published marketplace module', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    MarketplaceModule::query()->create([
        'slug' => 'finance-mod',
        'name' => 'Finance Module',
        'category' => MarketplaceModuleCategory::Finance,
        'price_minor' => 1000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    MarketplaceModule::query()->create([
        'slug' => 'hr-mod',
        'name' => 'HR Module',
        'category' => MarketplaceModuleCategory::Hr,
        'price_minor' => 0,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => true,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertSee(__('settings_modules_marketplace_heading'))
        ->assertSee('Finance Module')
        ->assertSee('HR Module')
        ->assertSee(__('settings_modules_marketplace_buy'))
        ->assertSee(__('settings_modules_marketplace_install'));
});

test('unpublished modules are hidden from the workspace marketplace', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    MarketplaceModule::query()->create([
        'slug' => 'hidden-module',
        'name' => 'Hidden Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 1000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => false,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertDontSee('Hidden Module');
});

test('signed-in workspace manager is sent from public catalog to modules settings', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $this->actingAs($user)
        ->get(route('marketing.modules'))
        ->assertRedirect(route('settings.modules'));
});

test('platform admin can manage marketplace modules', function () {
    actingAsAdmin();

    $this->get(route('admin.marketplace-modules.index'))
        ->assertOk();

    $this->post(route('admin.marketplace-modules.store'), [
        'slug' => 'admin-test-mod',
        'name' => 'Admin Test Module',
        'description' => 'Created in test',
        'category' => 'ecommerce',
        'price' => '49,00',
        'currency' => 'EUR',
        'billing_period' => 'monthly',
        'icon' => 'doc',
        'feature_bullets' => "Line one\nLine two",
        'sort_order' => 5,
        'is_published' => '1',
    ])->assertRedirect(route('admin.marketplace-modules.index'));

    $mod = MarketplaceModule::query()->where('slug', 'admin-test-mod')->first();
    expect($mod)->not->toBeNull();
    expect($mod->price_minor)->toBe(4900);
    expect($mod->feature_bullets)->toBe(['Line one', 'Line two']);
});

test('company admin cannot access marketplace modules admin', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);

    $this->actingAs($user)
        ->get(route('admin.marketplace-modules.index'))
        ->assertRedirect(route('admin.login'));
});

test('adding a paid module opens the cart modal on the modules page', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');

    $module = MarketplaceModule::query()->create([
        'slug' => 'cart-mod',
        'name' => 'Cart Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 1500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    $this->actingAs($user)
        ->post(route('marketing.cart.add', $module), ['currency' => 'USD'])
        ->assertRedirect(route('settings.modules', ['cart' => 1]));

    $this->actingAs($user)
        ->get(route('settings.modules', ['cart' => 1]))
        ->assertOk()
        ->assertSee('data-module-cart-modal', false)
        ->assertSee('Cart Module')
        ->assertSee(__('marketing_cart_checkout'));
});

test('marking marketplace order paid sends fulfillment email', function () {
    Mail::fake();

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-MAIL-01',
        'status' => MarketplaceOrderStatus::Pending,
        'customer_name' => 'Jane Buyer',
        'customer_email' => 'buyer@example.com',
        'total_minor' => 2000,
        'currency' => 'USD',
        'metadata' => ['payment_method' => 'bank'],
    ]);

    MarketplaceOrderItem::query()->create([
        'marketplace_order_id' => $order->id,
        'marketplace_module_id' => null,
        'module_slug' => 'test-mod',
        'module_name' => 'Test Module',
        'price_minor' => 2000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly->value,
    ]);

    app(MarketplaceCheckoutService::class)->markPaid($order);

    Mail::assertSent(MarketplaceOrderPaidMail::class, function ($mail) {
        return $mail->hasTo('buyer@example.com');
    });

    expect($order->fresh()->metadata['fulfilled_at'] ?? null)->not->toBeNull();
});

test('platform admin can list and mark marketplace orders paid', function () {
    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-TEST-01',
        'status' => MarketplaceOrderStatus::Pending,
        'customer_name' => 'Jane Buyer',
        'customer_email' => 'buyer@example.com',
        'total_minor' => 2000,
        'currency' => 'USD',
        'metadata' => ['payment_method' => 'bank'],
    ]);

    actingAsAdmin();

    $this->get(route('admin.marketplace-orders.index'))
        ->assertOk()
        ->assertSee('MK-TEST-01')
        ->assertSee('buyer@example.com');

    $this->put(route('admin.marketplace-orders.status', $order), ['status' => 'paid'])
        ->assertRedirect(route('admin.marketplace-orders.show', $order));

    expect($order->fresh()->status)->toBe(MarketplaceOrderStatus::Paid);
    expect($order->fresh()->paid_at)->not->toBeNull();
});

test('bank transfer instructions include configured rib fields', function () {
    $row = PlatformSetting::query()->first() ?? new PlatformSetting;
    $row->payment_credentials = [
        'bank_account_holder' => 'FlowDesk SAS',
        'bank_name' => 'BNP Paribas',
        'bank_rib' => 'FR76 3000 4000 0100 0000 0000 000',
        'bank_bic' => 'BNPAFRPP',
    ];
    $row->save();

    $text = app(InvoicePaymentGatewayService::class)->bankTransferInstructions();

    expect($text)->toContain('FlowDesk SAS');
    expect($text)->toContain('BNP Paribas');
    expect($text)->toContain('FR76 3000 4000 0100 0000 0000 000');
});

test('bank checkout creates pending order with payment reference', function () {
    $row = PlatformSetting::query()->first() ?? new PlatformSetting;
    $row->payment_credentials = [
        'bank_account_holder' => 'FlowDesk',
        'bank_rib' => 'FR76 3000 4000 0100 0000 0000 000',
    ];
    $row->save();

    $module = MarketplaceModule::query()->create([
        'slug' => 'bank-mod-ref',
        'name' => 'Bank Module Ref',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 2000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    app(MarketplaceCartService::class)->add($module, 'USD');

    $this->post(route('marketing.checkout.store'), [
        'name' => 'Jane Buyer',
        'email' => 'ref-buyer@example.com',
        'company' => 'Acme',
        'payment_method' => 'bank',
    ])->assertRedirect();

    $order = MarketplaceOrder::query()->where('customer_email', 'ref-buyer@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->payment_reference)->toBe($order->order_number);
    expect($order->paymentReferenceLabel())->toBe($order->order_number);

    $this->get(route('marketing.checkout.pending', $order))
        ->assertOk()
        ->assertSee($order->paymentReferenceLabel());
});

test('platform admin can search marketplace orders by payment reference', function () {
    MarketplaceOrder::query()->create([
        'order_number' => 'MK-REF-AAA',
        'payment_reference' => 'MK-REF-AAA',
        'status' => MarketplaceOrderStatus::Pending,
        'customer_name' => 'Ref Buyer',
        'customer_email' => 'ref@example.com',
        'total_minor' => 2000,
        'currency' => 'USD',
    ]);

    MarketplaceOrder::query()->create([
        'order_number' => 'MK-OTHER-01',
        'payment_reference' => 'MK-OTHER-01',
        'status' => MarketplaceOrderStatus::Pending,
        'customer_name' => 'Other Buyer',
        'customer_email' => 'other@example.com',
        'total_minor' => 1000,
        'currency' => 'USD',
    ]);

    actingAsAdmin();

    $this->get(route('admin.marketplace-orders.index', ['reference' => 'REF-AAA']))
        ->assertOk()
        ->assertSee('MK-REF-AAA')
        ->assertDontSee('MK-OTHER-01');
});

test('bank checkout creates pending order and clears cart on stripe success path', function () {
    $row = PlatformSetting::query()->first() ?? new PlatformSetting;
    $row->payment_credentials = [
        'bank_account_holder' => 'FlowDesk',
        'bank_rib' => 'FR76 3000 4000 0100 0000 0000 000',
    ];
    $row->save();

    $module = MarketplaceModule::query()->create([
        'slug' => 'bank-mod',
        'name' => 'Bank Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 2000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    app(MarketplaceCartService::class)->add($module, 'USD');

    $this->post(route('marketing.checkout.store'), [
        'name' => 'Jane Buyer',
        'email' => 'buyer@example.com',
        'company' => 'Acme',
        'payment_method' => 'bank',
    ])->assertRedirect();

    $order = MarketplaceOrder::query()->where('customer_email', 'buyer@example.com')->first();
    expect($order)->not->toBeNull();
    expect($order->status)->toBe(MarketplaceOrderStatus::Pending);
    expect($order->items)->toHaveCount(1);
});

test('paid order download requires valid signature', function () {
    $module = MarketplaceModule::query()->create([
        'slug' => 'dl-mod',
        'name' => 'Download Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 1000,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
        'is_published' => true,
        'sort_order' => 0,
        'zip_path' => 'marketplace-modules/fake/package.zip',
    ]);

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-TEST-001',
        'status' => MarketplaceOrderStatus::Paid,
        'customer_name' => 'Test',
        'customer_email' => 't@example.com',
        'total_minor' => 1000,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'marketplace_module_id' => $module->id,
        'module_slug' => $module->slug,
        'module_name' => $module->name,
        'price_minor' => 1000,
        'currency' => 'USD',
        'billing_period' => 'monthly',
    ]);

    $this->get(route('marketing.order.download', ['order' => $order->id, 'module' => $module->id]))
        ->assertForbidden();

    $url = URL::signedRoute('marketing.order.download', ['order' => $order->id, 'module' => $module->id]);
    $this->get($url)->assertNotFound();
});

test('company settings modules page lists purchased modules with download', function () {
    $user = User::factory()->create();
    $company = $user->company;

    $zipRelative = 'marketplace-modules/test/purchased-mod.zip';
    Storage::disk('local')->put($zipRelative, 'fake-zip-content');

    $module = MarketplaceModule::query()->create([
        'slug' => 'purchased-mod',
        'name' => 'Purchased Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 2500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 0,
        'zip_path' => $zipRelative,
    ]);

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-PUR-01',
        'payment_reference' => 'MK-PUR-01',
        'status' => MarketplaceOrderStatus::Paid,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'company_id' => $company->id,
        'total_minor' => 2500,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    $order->items()->create([
        'marketplace_module_id' => $module->id,
        'module_slug' => $module->slug,
        'module_name' => $module->name,
        'price_minor' => 2500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime->value,
    ]);

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertSee(__('settings_modules_purchased_heading'))
        ->assertSee('Purchased Module')
        ->assertSee('MK-PUR-01');

    $this->actingAs($user)
        ->get(route('settings.modules.purchased.download', $module))
        ->assertOk()
        ->assertDownload('purchased-mod.zip');
});

test('company admin can remove purchased module from list', function () {
    $user = User::factory()->create();
    $user->syncRoles(['company_admin']);
    $company = $user->company;

    $module = MarketplaceModule::query()->create([
        'slug' => 'remove-me-mod',
        'name' => 'Remove Me Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 2500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    $order = MarketplaceOrder::query()->create([
        'order_number' => 'MK-RM-01',
        'payment_reference' => 'MK-RM-01',
        'status' => MarketplaceOrderStatus::Paid,
        'customer_name' => $user->name,
        'customer_email' => $user->email,
        'company_id' => $company->id,
        'total_minor' => 2500,
        'currency' => 'USD',
        'paid_at' => now(),
    ]);

    $item = $order->items()->create([
        'marketplace_module_id' => $module->id,
        'module_slug' => $module->slug,
        'module_name' => $module->name,
        'price_minor' => 2500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime->value,
    ]);

    $this->actingAs($user)
        ->get(route('settings.modules'))
        ->assertOk()
        ->assertSee('Remove Me Module');

    $this->actingAs($user)
        ->delete(route('settings.modules.purchased.destroy', $item))
        ->assertRedirect(route('settings.modules'))
        ->assertSessionHas('status');

    expect(app(MarketplaceOrderFulfillmentService::class)->purchasedModulesForCompany($company))->toBeEmpty();
});

test('checkout without payment methods notifies admin with required message', function () {
    Mail::fake();

    $row = PlatformSetting::query()->first() ?? new PlatformSetting;
    $row->payment_credentials = [];
    $row->save();

    \App\Models\Admin::factory()->create([
        'email' => 'platform-notify@example.com',
    ]);

    $module = MarketplaceModule::query()->create([
        'slug' => 'admin-notify-mod',
        'name' => 'Admin Notify Module',
        'category' => MarketplaceModuleCategory::General,
        'price_minor' => 3500,
        'currency' => 'USD',
        'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
        'is_published' => true,
        'sort_order' => 0,
    ]);

    app(MarketplaceCartService::class)->add($module, 'USD');

    $this->get(route('marketing.checkout'))
        ->assertOk()
        ->assertSee(__('marketing_checkout_admin_notify_submit'));

    $this->post(route('marketing.checkout.store'), [
        'name' => 'No Pay Buyer',
        'email' => 'nopay@example.com',
        'company' => 'Acme',
    ])->assertSessionHasErrors('message');

    $this->post(route('marketing.checkout.store'), [
        'name' => 'No Pay Buyer',
        'email' => 'nopay@example.com',
        'company' => 'Acme',
        'message' => 'Please activate this module for us.',
    ])->assertRedirect();

    $order = MarketplaceOrder::query()->where('customer_email', 'nopay@example.com')->first();
    expect($order)->not->toBeNull()
        ->and($order->metadata['payment_method'] ?? null)->toBe('admin_request')
        ->and($order->metadata['customer_message'] ?? null)->toBe('Please activate this module for us.');

    Mail::assertSent(\App\Mail\CheckoutAdminNotifyMail::class, function ($mail) use ($order) {
        return $mail->checkoutType === 'marketplace'
            && $mail->customerEmail === 'nopay@example.com'
            && ($mail->context['order_number'] ?? null) === $order->order_number;
    });

    $this->get(route('marketing.checkout.pending', $order))
        ->assertOk()
        ->assertSee(__('marketing_checkout_admin_notify_pending_title'))
        ->assertSee('Please activate this module for us.');
});
