<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Commerce\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WooCommerceWebhookController extends Controller
{
    public function __invoke(Request $request, StockService $stock): Response
    {
        $payload = $request->all();
        $externalId = (string) (data_get($payload, 'id') ?? '');
        if ($externalId === '') {
            return response('OK', 200);
        }

        $variant = ProductVariant::query()->withoutGlobalScopes()
            ->where('external_woo_id', $externalId)
            ->first();
        if (! $variant) {
            return response('OK', 200);
        }

        $price = data_get($payload, 'price') ?? data_get($payload, 'regular_price');
        if ($price !== null && $price !== '') {
            $variant->forceFill(['price_minor' => (int) round(((float) $price) * 100)])->save();
        }

        $qty = data_get($payload, 'stock_quantity');
        if ($qty !== null) {
            $delta = (int) $qty - (int) $variant->stock_qty;
            if ($delta !== 0 && $variant->company) {
                try {
                    $stock->adjust($variant->company, $variant, $delta, 'sync', 'woocommerce', $externalId);
                } catch (\Throwable) {
                }
            }
        }

        return response('OK', 200);
    }
}
