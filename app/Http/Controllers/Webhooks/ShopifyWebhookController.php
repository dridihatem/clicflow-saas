<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Commerce\StockService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ShopifyWebhookController extends Controller
{
    public function __invoke(Request $request, StockService $stock): Response
    {
        $payload = $request->all();
        $externalId = (string) (data_get($payload, 'id') ?? '');
        if ($externalId === '') {
            return response('OK', 200);
        }

        $variant = ProductVariant::query()->withoutGlobalScopes()
            ->where('external_shopify_id', $externalId)
            ->first();
        if (! $variant) {
            return response('OK', 200);
        }

        $price = data_get($payload, 'price');
        if ($price !== null) {
            $variant->forceFill(['price_minor' => (int) round(((float) $price) * 100)])->save();
        }

        $qty = data_get($payload, 'inventory_quantity');
        if ($qty !== null) {
            $delta = (int) $qty - (int) $variant->stock_qty;
            if ($delta !== 0) {
                $company = $variant->company;
                if ($company) {
                    try {
                        $stock->adjust($company, $variant, $delta, 'sync', 'shopify', $externalId);
                    } catch (\Throwable) {
                        // ignore oversell from remote
                    }
                }
            }
        }

        return response('OK', 200);
    }
}
