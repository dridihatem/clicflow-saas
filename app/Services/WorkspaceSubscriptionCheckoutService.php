<?php

namespace App\Services;

use App\Enums\WorkspaceSubscriptionOrderStatus;
use App\Models\Company;
use App\Models\Plan;
use App\Models\User;
use App\Models\WorkspaceSubscriptionOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class WorkspaceSubscriptionCheckoutService
{
    public function __construct(
        private InvoicePaymentGatewayService $gateways,
        private PlanPricingService $pricing,
        private WorkspacePlanActivationService $activation,
    ) {}

    /**
     * @return list<array{value: string, id: string, label: string, icon: string, description: string}>
     */
    public function availablePaymentMethods(): array
    {
        $creds = $this->gateways->platformCredentials();
        $methods = [];

        if ($this->gateways->stripeReady($creds)) {
            $methods[] = $this->methodRow('stripe', InvoicePaymentGatewayService::GATEWAY_STRIPE);
        }
        if ($this->gateways->paypalReady($creds)) {
            $methods[] = $this->methodRow('paypal', InvoicePaymentGatewayService::GATEWAY_PAYPAL);
        }
        if ($this->gateways->flouciReady($creds)) {
            $methods[] = $this->methodRow('flouci', InvoicePaymentGatewayService::GATEWAY_FLOUCI);
        }
        if ($this->gateways->bankTransferReady($creds)) {
            $methods[] = $this->methodRow('bank', InvoicePaymentGatewayService::GATEWAY_BANK);
        }

        return $methods;
    }

    /**
     * @return array{amount_minor: int, currency: string, formatted: string}
     */
    public function amountMinorFor(Plan $plan, int $periodMonths, string $displayCurrency): array
    {
        $plan->loadMissing('periodPrices');
        $rows = $this->pricing->buildPlanRows(collect([$plan]), $displayCurrency);
        $row = $rows[0] ?? null;
        $period = $row['periods'][$periodMonths] ?? null;
        $minor = is_array($period) ? (int) ($period['minor'] ?? 0) : 0;

        if ($minor <= 0) {
            $monthlyMinor = (int) round(((float) $plan->price_monthly) * 100) * max(1, $periodMonths);
            $minor = app(CurrencyConversionService::class)->convertMinor($monthlyMinor, $plan->currency, $displayCurrency);
        }

        return [
            'amount_minor' => max(0, $minor),
            'currency' => strtoupper($displayCurrency),
            'formatted' => flowdesk_format_minor(max(0, $minor), $displayCurrency).' '.strtoupper($displayCurrency),
        ];
    }

    public function planRequiresPayment(Plan $plan, int $periodMonths, string $displayCurrency): bool
    {
        return $this->amountMinorFor($plan, $periodMonths, $displayCurrency)['amount_minor'] > 0;
    }

    public function createOrder(
        Company $company,
        User $user,
        Plan $plan,
        int $periodMonths,
        string $displayCurrency,
    ): WorkspaceSubscriptionOrder {
        $pricing = $this->amountMinorFor($plan, $periodMonths, $displayCurrency);

        if ($pricing['amount_minor'] <= 0) {
            throw new RuntimeException(__('billing_plan_free_use_activate'));
        }

        return WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => WorkspaceSubscriptionOrderStatus::Pending,
            'period_months' => $periodMonths,
            'amount_minor' => $pricing['amount_minor'],
            'currency' => $pricing['currency'],
            'metadata' => [
                'plan_slug' => $plan->slug,
                'plan_name' => $plan->name,
                'display_currency' => $pricing['currency'],
            ],
        ]);
    }

    public function createStripeCheckoutSession(WorkspaceSubscriptionOrder $order): string
    {
        $secret = $this->gateways->platformCredentials()['stripe_secret_key'] ?? null;
        if (! is_string($secret) || $secret === '') {
            throw new RuntimeException(__('Stripe is not configured.'));
        }

        Stripe::setApiKey($secret);

        $session = StripeCheckoutSession::create([
            'mode' => 'payment',
            'success_url' => route('billing.checkout.success', $order).'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('billing.checkout.show', $order),
            'customer_email' => $order->user?->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($order->currency),
                    'unit_amount' => (int) $order->amount_minor,
                    'product_data' => [
                        'name' => ($order->plan?->name ?? 'Plan').' ('.$order->period_months.' mo)',
                        'description' => __('billing_subscription_checkout_item'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'workspace_subscription_order_id' => (string) $order->id,
                'company_id' => (string) $order->company_id,
                'plan_id' => (string) $order->plan_id,
            ],
        ]);

        $order->forceFill([
            'payment_method' => 'stripe',
            'external_reference' => (string) $session->id,
            'metadata' => array_merge(is_array($order->metadata) ? $order->metadata : [], [
                'stripe_session_id' => $session->id,
            ]),
        ])->save();

        return (string) $session->url;
    }

    public function completeFromStripeSession(string $sessionId): ?WorkspaceSubscriptionOrder
    {
        $secret = $this->gateways->platformCredentials()['stripe_secret_key'] ?? null;
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        Stripe::setApiKey($secret);

        try {
            $session = StripeCheckoutSession::retrieve($sessionId);
        } catch (\Throwable) {
            return null;
        }

        $orderId = $session->metadata->workspace_subscription_order_id ?? null;
        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $order = WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()->find($orderId);
        if (! $order) {
            return null;
        }

        if (($session->payment_status ?? '') === 'paid' && ! $order->isPaid()) {
            $this->markPaid(
                $order,
                'stripe',
                is_string($session->payment_intent ?? null) ? (string) $session->payment_intent : $sessionId,
            );
        }

        return $order->fresh();
    }

    /**
     * @return array{approve_url: string, order_id: string}
     */
    public function createPayPalOrder(WorkspaceSubscriptionOrder $order): array
    {
        $creds = $this->gateways->platformCredentials();
        $token = $this->paypalAccessToken($creds);
        if ($token === null) {
            throw new RuntimeException(__('PayPal is not configured.'));
        }

        $base = $this->paypalApiBase($creds);
        $amount = number_format($order->amount_minor / 100, 2, '.', '');

        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post($base.'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => (string) $order->id,
                    'custom_id' => (string) $order->id,
                    'amount' => [
                        'currency_code' => strtoupper($order->currency),
                        'value' => $amount,
                    ],
                    'description' => ($order->plan?->name ?? 'Plan').' subscription',
                ]],
                'application_context' => [
                    'return_url' => route('billing.checkout.paypal.return', $order),
                    'cancel_url' => route('billing.checkout.show', $order),
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if (! $res->successful()) {
            throw new RuntimeException(__('PayPal checkout failed.'));
        }

        $paypalOrderId = (string) data_get($res->json(), 'id', '');
        $approveUrl = collect(data_get($res->json(), 'links', []))
            ->firstWhere('rel', 'approve')['href'] ?? null;

        if ($paypalOrderId === '' || ! is_string($approveUrl) || $approveUrl === '') {
            throw new RuntimeException(__('PayPal checkout failed.'));
        }

        $order->forceFill([
            'payment_method' => 'paypal',
            'external_reference' => $paypalOrderId,
            'metadata' => array_merge(is_array($order->metadata) ? $order->metadata : [], [
                'paypal_order_id' => $paypalOrderId,
            ]),
        ])->save();

        return ['approve_url' => $approveUrl, 'order_id' => $paypalOrderId];
    }

    public function capturePayPalOrder(WorkspaceSubscriptionOrder $order, string $paypalOrderId): void
    {
        $creds = $this->gateways->platformCredentials();
        $token = $this->paypalAccessToken($creds);
        if ($token === null) {
            throw new RuntimeException(__('PayPal is not configured.'));
        }

        $base = $this->paypalApiBase($creds);
        $res = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post($base.'/v2/checkout/orders/'.$paypalOrderId.'/capture');

        if (! $res->successful()) {
            throw new RuntimeException(__('PayPal capture failed.'));
        }

        $status = (string) data_get($res->json(), 'status', '');
        if (! in_array($status, ['COMPLETED', 'APPROVED'], true)) {
            throw new RuntimeException(__('PayPal payment was not completed.'));
        }

        $this->markPaid($order, 'paypal', $paypalOrderId);
    }

    /**
     * @return array{payment_id: string|null, link: string}
     */
    public function createFlouciPayment(WorkspaceSubscriptionOrder $order): array
    {
        $creds = $this->gateways->platformCredentials();
        $public = $creds['flouci_public_key'] ?? null;
        $private = $creds['flouci_secret_key'] ?? null;

        if (! $public || ! $private) {
            throw new RuntimeException(__('Flouci is not configured.'));
        }

        $base = $creds['flouci_api_base'] ?? 'https://developers.flouci.com/api/v2/generate_payment';

        $res = Http::withHeaders([
            'Authorization' => 'Bearer '.$public.':'.$private,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->post($base, [
                'amount' => (int) $order->amount_minor,
                'developer_tracking_id' => 'wso:'.$order->id,
                'success_link' => route('billing.checkout.flouci.return', $order).'?flouci=success',
                'fail_link' => route('billing.checkout.show', $order).'?flouci=fail',
                'webhook' => route('webhooks.flouci', absolute: true),
                'client_id' => $order->user?->email ?? (string) $order->id,
                'accept_card' => true,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException(__('Flouci payment link failed.'));
        }

        $paymentId = data_get($res->json(), 'result.payment_id');
        $link = data_get($res->json(), 'result.link');

        if (! is_string($link) || $link === '') {
            throw new RuntimeException(__('Flouci payment link failed.'));
        }

        $order->forceFill([
            'payment_method' => 'flouci',
            'external_reference' => is_string($paymentId) ? $paymentId : $order->external_reference,
            'metadata' => array_merge(is_array($order->metadata) ? $order->metadata : [], [
                'flouci_payment_id' => $paymentId,
            ]),
        ])->save();

        return [
            'payment_id' => is_string($paymentId) ? $paymentId : null,
            'link' => $link,
        ];
    }

    public function markPaid(WorkspaceSubscriptionOrder $order, string $paymentMethod, ?string $externalReference = null): void
    {
        if ($order->isPaid()) {
            return;
        }

        DB::transaction(function () use ($order, $paymentMethod, $externalReference) {
            $locked = WorkspaceSubscriptionOrder::query()
                ->withoutGlobalScopes()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked || $locked->isPaid()) {
                return;
            }

            $locked->forceFill([
                'status' => WorkspaceSubscriptionOrderStatus::Paid,
                'payment_method' => $paymentMethod,
                'external_reference' => $externalReference ?? $locked->external_reference,
                'paid_at' => now(),
            ])->save();

            $locked->loadMissing(['company', 'plan']);
            if ($locked->company && $locked->plan) {
                $this->activation->activate($locked->company, $locked->plan, (int) $locked->period_months);
            }
        });
    }

    public function markBankPending(WorkspaceSubscriptionOrder $order): void
    {
        $order->forceFill([
            'payment_method' => 'bank',
            'metadata' => array_merge(is_array($order->metadata) ? $order->metadata : [], [
                'payment_method' => 'bank',
                'awaiting_confirmation' => true,
            ]),
        ])->save();
    }

    /**
     * @return array{value: string, id: string, label: string, icon: string, description: string}
     */
    private function methodRow(string $value, string $gatewayId): array
    {
        return [
            'value' => $value,
            'id' => $gatewayId,
            'label' => $this->gateways->gatewayLabel($gatewayId),
            'icon' => $this->gateways->gatewayIcon($gatewayId),
            'description' => $this->gateways->gatewayDescription($gatewayId),
        ];
    }

    /**
     * @param  array<string, mixed>  $creds
     */
    private function paypalAccessToken(array $creds): ?string
    {
        $clientId = $creds['paypal_client_id'] ?? null;
        $secret = $creds['paypal_secret'] ?? null;
        if (! $clientId || ! $secret) {
            return null;
        }

        $res = Http::asForm()
            ->withBasicAuth((string) $clientId, (string) $secret)
            ->timeout(30)
            ->post($this->paypalApiBase($creds).'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (! $res->successful()) {
            return null;
        }

        $token = data_get($res->json(), 'access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * @param  array<string, mixed>  $creds
     */
    private function paypalApiBase(array $creds): string
    {
        return ($creds['paypal_mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function generateOrderNumber(): string
    {
        do {
            $number = 'SUB-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (WorkspaceSubscriptionOrder::query()->withoutGlobalScopes()->where('order_number', $number)->exists());

        return $number;
    }
}
