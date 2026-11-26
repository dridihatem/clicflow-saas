<?php

use App\Enums\WorkspaceSubscriptionOrderStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WorkspaceSubscriptionOrder;
use App\Services\PlanLimitService;
use App\Services\SubscriptionTrialService;
use Database\Seeders\PlanSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PlanSeeder::class);

    $settings = PlatformSetting::query()->first() ?? new PlatformSetting;
    $settings->payment_credentials = [
        'bank_account_holder' => 'ClicFlow',
        'bank_name' => 'BTK',
        'bank_rib' => '12 345 67890',
        'bank_instructions' => 'Use order number as reference',
    ];
    $settings->save();
});

test('expired trial choosing a plan creates checkout order without activating', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    $company->forceFill(['plan_id' => $pro->id])->saveQuietly();
    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    $trials = app(SubscriptionTrialService::class);
    expect($trials->trialExpired($company))->toBeTrue()
        ->and($trials->effectivePlan($company)?->slug)->toBe('starter');

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee(__('billing_continue_to_checkout'));

    $response = $this->actingAs($user)
        ->post(route('billing.plans.store'), [
            'plan_id' => $pro->id,
            'period_months' => 3,
            'currency' => 'USD',
        ]);

    $order = WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->latest('id')
        ->first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(WorkspaceSubscriptionOrderStatus::Pending)
        ->and($order->plan_id)->toBe($pro->id);

    $response->assertRedirect(route('billing.checkout.show', $order));

    $company->refresh();
    expect($trials->trialExpired($company))->toBeTrue()
        ->and($trials->effectivePlan($company)?->slug)->toBe('starter');
});

test('bank checkout keeps plan pending until admin marks paid', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->post(route('billing.plans.store'), [
            'plan_id' => $pro->id,
            'period_months' => 3,
            'currency' => 'USD',
        ]);

    $order = WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('billing.checkout.pay', $order), [
            'payment_method' => 'bank',
        ])
        ->assertRedirect(route('billing.checkout.pending', $order));

    expect($order->fresh()->payment_method)->toBe('bank')
        ->and($order->fresh()->isPaid())->toBeFalse();

    actingAsAdmin();
    $this->put(route('admin.subscription-orders.status', $order), [
        'status' => 'paid',
    ])->assertRedirect(route('admin.subscription-orders.show', $order));

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(app(SubscriptionTrialService::class)->effectivePlan($company->fresh())?->slug)->toBe('pro')
        ->and(app(PlanLimitService::class)->isFeatureEnabled($company->fresh(), 'calendar'))->toBeTrue();
});

test('billing marks current plan for active paid subscription', function () {
    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();

    app(\App\Services\WorkspacePlanActivationService::class)->activate($company, $enterprise, 12);

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertOk()
        ->assertSee(__('billing_current_plan'))
        ->assertSee($enterprise->name);
});

test('billing checkout without payment methods notifies admin with required message', function () {
    \Illuminate\Support\Facades\Mail::fake();

    $settings = PlatformSetting::query()->first() ?? new PlatformSetting;
    $settings->payment_credentials = [];
    $settings->save();

    User::factory()->platformAdmin()->create([
        'email' => 'billing-notify-admin@example.com',
    ]);

    $user = User::factory()->create();
    $user->assignRole('company_admin');
    $company = $user->company;
    $pro = Plan::query()->where('slug', 'pro')->firstOrFail();

    Subscription::factory()->create([
        'company_id' => $company->id,
        'plan_id' => $pro->id,
        'status' => 'trialing',
        'trial_ends_at' => now()->subDay(),
        'current_period_end' => now()->subDay(),
    ]);

    $this->actingAs($user)
        ->post(route('billing.plans.store'), [
            'plan_id' => $pro->id,
            'period_months' => 3,
            'currency' => 'USD',
        ]);

    $order = WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()
        ->where('company_id', $company->id)
        ->firstOrFail();

    $this->actingAs($user)
        ->post(route('billing.checkout.notify', $order), [])
        ->assertSessionHasErrors('message');

    $this->actingAs($user)
        ->post(route('billing.checkout.notify', $order), [
            'message' => 'Please activate Pro for our workspace.',
        ])
        ->assertRedirect(route('billing.checkout.pending', $order));

    $order->refresh();
    expect($order->payment_method)->toBe('admin_request')
        ->and($order->metadata['customer_message'] ?? null)->toBe('Please activate Pro for our workspace.');

    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\CheckoutAdminNotifyMail::class, function ($mail) use ($order) {
        return $mail->checkoutType === 'billing'
            && ($mail->context['order_number'] ?? null) === $order->order_number;
    });
});
