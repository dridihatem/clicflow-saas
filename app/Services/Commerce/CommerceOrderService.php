<?php

namespace App\Services\Commerce;

use App\Models\CommerceOrder;
use App\Models\CommerceOrderItem;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\PosSession;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommerceOrderService
{
    public function __construct(
        private StockService $stock,
        private CommerceInvoiceService $invoices,
        private CommerceSyncDispatcher $sync,
    ) {}

    /**
     * @param  list<array{variant_id: string, quantity: int}>  $lines
     */
    public function createSale(
        Company $company,
        User $user,
        array $lines,
        string $source = 'manual',
        ?string $clientId = null,
        ?string $paymentMethod = null,
        ?PosSession $session = null,
        bool $createInvoice = false,
        bool $createDelivery = false,
    ): CommerceOrder {
        if ($lines === []) {
            throw new InvalidArgumentException(__('Add at least one product line.'));
        }

        return DB::transaction(function () use ($company, $lines, $source, $clientId, $paymentMethod, $session, $createInvoice, $createDelivery) {
            $currency = strtoupper((string) ($company->default_currency ?: flowdesk_default_currency()));
            $order = CommerceOrder::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'client_id' => $clientId,
                'pos_session_id' => $session?->id,
                'source' => $source,
                'status' => 'completed',
                'currency' => $currency,
                'subtotal_minor' => 0,
                'total_minor' => 0,
                'payment_method' => $paymentMethod,
            ]);

            $subtotal = 0;

            foreach ($lines as $line) {
                $variant = ProductVariant::query()
                    ->withoutGlobalScopes()
                    ->with('product')
                    ->where('company_id', $company->id)
                    ->whereKey($line['variant_id'])
                    ->firstOrFail();

                $qty = max(1, (int) $line['quantity']);
                $unit = $variant->unitPriceMinor();
                $total = $unit * $qty;
                $subtotal += $total;

                CommerceOrderItem::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'commerce_order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                    'description' => $variant->label(),
                    'quantity' => $qty,
                    'unit_price_minor' => $unit,
                    'total_minor' => $total,
                ]);

                if ($variant->product?->track_stock) {
                    $this->stock->adjust(
                        $company,
                        $variant,
                        -$qty,
                        'sale',
                        CommerceOrder::class,
                        (string) $order->id,
                    );
                    $this->sync->pushVariantIfNeeded($company, $variant->fresh());
                }
            }

            $order->forceFill([
                'subtotal_minor' => $subtotal,
                'total_minor' => $subtotal,
            ])->save();

            if ($session && $session->isOpen()) {
                if ($paymentMethod === 'cash') {
                    $session->increment('cash_sales_minor', $subtotal);
                } else {
                    $session->increment('card_sales_minor', $subtotal);
                }
            }

            if ($createDelivery) {
                Delivery::query()->withoutGlobalScopes()->create([
                    'company_id' => $company->id,
                    'commerce_order_id' => $order->id,
                    'status' => 'pending',
                ]);
            }

            if ($createInvoice) {
                $this->invoices->createFromOrder($order->fresh('items'));
            }

            return $order->fresh(['items', 'invoice', 'delivery']);
        });
    }
}
