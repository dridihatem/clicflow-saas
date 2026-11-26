<?php

namespace App\Http\Controllers;

use App\Models\WorkspaceSubscriptionOrder;
use App\Services\InvoicePaymentGatewayService;
use App\Services\PlatformAdminNotifier;
use App\Services\WorkspaceSubscriptionCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class BillingCheckoutController extends Controller
{
    public function show(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
        InvoicePaymentGatewayService $gateways,
    ): View|RedirectResponse {
        $this->authorizeOrder($request, $order);

        if ($order->isPaid()) {
            return redirect()
                ->route('billing.index')
                ->with('status', __('billing_plan_activated', ['plan' => $order->plan?->name ?? '']));
        }

        $order->loadMissing(['plan', 'company']);
        $paymentMethods = $checkout->availablePaymentMethods();

        return view('billing.checkout', [
            'order' => $order,
            'paymentMethods' => $paymentMethods,
            'defaultPaymentMethod' => old('payment_method', $paymentMethods[0]['value'] ?? 'bank'),
            'bankDetails' => $gateways->bankTransferDetails(),
            'bankInstructions' => $gateways->bankTransferInstructions(),
        ]);
    }

    public function pay(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);
        abort_unless($order->isPending(), 404);

        $allowed = collect($checkout->availablePaymentMethods())->pluck('value')->all();
        if ($allowed === []) {
            return back()->withErrors(['checkout' => __('billing_checkout_no_payment_methods')]);
        }

        $data = $request->validate([
            'payment_method' => ['required', 'string', Rule::in($allowed)],
        ]);

        try {
            return match ($data['payment_method']) {
                'stripe' => redirect()->away($checkout->createStripeCheckoutSession($order)),
                'paypal' => redirect()->away($checkout->createPayPalOrder($order)['approve_url']),
                'flouci' => redirect()->away($checkout->createFlouciPayment($order)['link']),
                'bank' => $this->submitBank($order, $checkout),
                default => back()->withErrors(['checkout' => __('billing_checkout_no_payment_methods')]),
            };
        } catch (RuntimeException $e) {
            return back()->withErrors(['checkout' => $e->getMessage()]);
        }
    }

    public function notifyAdmin(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
        PlatformAdminNotifier $notifier,
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);
        abort_unless($order->isPending(), 404);

        if ($checkout->availablePaymentMethods() !== []) {
            return redirect()->route('billing.checkout.show', $order);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        $order->loadMissing(['plan', 'company']);

        $order->forceFill([
            'payment_method' => 'admin_request',
            'metadata' => array_merge(is_array($order->metadata) ? $order->metadata : [], [
                'payment_method' => 'admin_request',
                'customer_message' => $data['message'],
                'admin_notified_at' => now()->toIso8601String(),
            ]),
        ])->save();

        $notifier->notifyCheckoutRequest(
            'billing',
            (string) ($user?->name ?? ''),
            (string) ($user?->email ?? ''),
            $data['message'],
            [
                'company' => $order->company?->name,
                'order_number' => $order->order_number,
                'plan' => $order->plan?->name,
                'period_months' => $order->period_months,
                'total' => flowdesk_format_minor((int) $order->amount_minor, (string) $order->currency).' '.$order->currency,
                'admin_url' => route('admin.subscription-orders.show', $order),
            ],
        );

        return redirect()
            ->route('billing.checkout.pending', $order)
            ->with('status', __('billing_checkout_admin_notified'));
    }

    public function success(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);

        $sessionId = (string) $request->query('session_id', '');
        if ($sessionId !== '') {
            $checkout->completeFromStripeSession($sessionId);
        }

        $order->refresh();
        if ($order->isPaid()) {
            return redirect()
                ->route('billing.index')
                ->with('status', __('billing_plan_activated', ['plan' => $order->plan?->name ?? '']));
        }

        return redirect()
            ->route('billing.checkout.show', $order)
            ->with('status', __('billing_checkout_processing'));
    }

    public function paypalReturn(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);

        $token = (string) $request->query('token', $order->external_reference ?? '');
        if ($token === '') {
            return redirect()
                ->route('billing.checkout.show', $order)
                ->withErrors(['checkout' => __('PayPal payment was not completed.')]);
        }

        try {
            $checkout->capturePayPalOrder($order, $token);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('billing.checkout.show', $order)
                ->withErrors(['checkout' => $e->getMessage()]);
        }

        return redirect()
            ->route('billing.index')
            ->with('status', __('billing_plan_activated', ['plan' => $order->fresh()->plan?->name ?? '']));
    }

    public function flouciReturn(
        Request $request,
        WorkspaceSubscriptionOrder $order,
    ): RedirectResponse {
        $this->authorizeOrder($request, $order);

        if ($order->isPaid()) {
            return redirect()
                ->route('billing.index')
                ->with('status', __('billing_plan_activated', ['plan' => $order->plan?->name ?? '']));
        }

        return redirect()
            ->route('billing.checkout.show', $order)
            ->with('status', __('billing_checkout_processing'));
    }

    public function pending(
        Request $request,
        WorkspaceSubscriptionOrder $order,
        InvoicePaymentGatewayService $gateways,
    ): View|RedirectResponse {
        $this->authorizeOrder($request, $order);

        if ($order->isPaid()) {
            return redirect()->route('billing.index');
        }

        return view('billing.checkout-pending', [
            'order' => $order->loadMissing('plan'),
            'bankDetails' => $gateways->bankTransferDetails(),
            'bankInstructions' => $gateways->bankTransferInstructions(),
        ]);
    }

    private function submitBank(
        WorkspaceSubscriptionOrder $order,
        WorkspaceSubscriptionCheckoutService $checkout,
    ): RedirectResponse {
        $checkout->markBankPending($order);

        return redirect()
            ->route('billing.checkout.pending', $order)
            ->with('status', __('billing_checkout_bank_pending'));
    }

    private function authorizeOrder(Request $request, WorkspaceSubscriptionOrder $order): void
    {
        $user = $request->user();
        abort_if(! $user?->hasRole('company_admin'), 403);
        abort_if((string) $order->company_id !== (string) $user->company_id, 403);
    }
}
