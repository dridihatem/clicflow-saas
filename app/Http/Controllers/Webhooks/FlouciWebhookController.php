<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\RemittanceMethod;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\WorkspaceSubscriptionOrder;
use App\Services\InvoiceOnlinePaymentService;
use App\Services\WorkspaceSubscriptionCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FlouciWebhookController extends Controller
{
    public function __invoke(Request $request, InvoiceOnlinePaymentService $onlinePayments): Response
    {
        $payload = $request->all();

        $fid = data_get($payload, 'data.payment_id')
            ?? data_get($payload, 'result.payment_id')
            ?? data_get($payload, 'payment_id');

        $trackingId = data_get($payload, 'data.developer_tracking_id')
            ?? data_get($payload, 'developer_tracking_id')
            ?? data_get($payload, 'data.invoice_id');

        if (! $fid || ! $trackingId) {
            Log::info('flouci.webhook.incomplete', ['payload' => $payload]);

            return response('OK', 200);
        }

        $trackingId = (string) $trackingId;

        if (Str::startsWith($trackingId, 'wso:')) {
            $order = WorkspaceSubscriptionOrder::query()
                ->withoutGlobalScopes()
                ->find(Str::after($trackingId, 'wso:'));

            if ($order && ! $order->isPaid()) {
                app(WorkspaceSubscriptionCheckoutService::class)->markPaid(
                    $order,
                    'flouci',
                    'flouci:'.$fid,
                );
            }

            return response('OK', 200);
        }

        $invoice = Invoice::query()->withoutGlobalScope('tenant')->where('id', $trackingId)->first();
        if ($invoice === null) {
            return response('OK', 200);
        }

        $amount = (int) (data_get($payload, 'data.amount')
            ?? data_get($payload, 'amount')
            ?? $invoice->amount);

        $onlinePayments->recordCompletedPayment(
            $invoice,
            $amount,
            RemittanceMethod::Flouci,
            'flouci',
            'flouci:'.$fid,
            ['payload' => $payload],
        );

        return response('OK', 200);
    }
}
