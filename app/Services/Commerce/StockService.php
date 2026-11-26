<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StockService
{
    public function adjust(
        Company $company,
        ProductVariant $variant,
        int $delta,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        ?string $notes = null,
    ): StockMovement {
        if ((string) $variant->company_id !== (string) $company->id) {
            throw new InvalidArgumentException('Variant does not belong to company.');
        }

        return DB::transaction(function () use ($company, $variant, $delta, $reason, $referenceType, $referenceId, $notes) {
            $locked = ProductVariant::query()
                ->withoutGlobalScopes()
                ->with('product')
                ->whereKey($variant->id)
                ->lockForUpdate()
                ->firstOrFail();

            $next = (int) $locked->stock_qty + $delta;
            if ($locked->product?->track_stock && $next < 0) {
                throw new InvalidArgumentException(__('Insufficient stock for :item.', ['item' => $locked->label()]));
            }

            $locked->forceFill(['stock_qty' => $next])->save();

            return StockMovement::query()->withoutGlobalScopes()->create([
                'company_id' => $company->id,
                'product_variant_id' => $locked->id,
                'delta' => $delta,
                'reason' => $reason,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }
}
