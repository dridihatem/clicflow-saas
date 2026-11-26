<?php

namespace App\Services\Commerce;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deletes catalog items (products and services) for one company.
 *
 * An item still referenced by an invoice line, a quote line or a commerce order line
 * is never deleted (documents keep their link and history); it is skipped and reported.
 * Unused items are deleted with their variants (and those variants' stock movements).
 */
class ProductDeletionService
{
    public const REASON_INVOICES = 'invoices';

    public const REASON_QUOTES = 'quotes';

    public const REASON_ORDERS = 'orders';

    /**
     * @param  list<string>  $ids
     * @return array{deleted: list<string>, skipped: array<string, list<string>>, not_found: int}
     *                                                                                             skipped = reason => product names
     */
    public function deleteMany(Company $company, array $ids, ?int $userId = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));

        /** @var Collection<int, Product> $products */
        $products = Product::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->with('variants:id,product_id')
            ->get();

        $result = ['deleted' => [], 'skipped' => [], 'not_found' => count($ids) - $products->count()];

        foreach ($products as $product) {
            $reason = $this->blockingReason($product);
            if ($reason !== null) {
                $result['skipped'][$reason][] = (string) $product->name;

                continue;
            }

            DB::transaction(function () use ($product) {
                ProductVariant::query()->withoutGlobalScopes()->where('product_id', $product->id)->get()->each->delete();
                $product->delete();
            });

            app(AuditLogService::class)->record('product.deleted', null, [
                'product_id' => (string) $product->id,
                'name' => (string) $product->name,
                'kind' => $product->kind?->value,
            ], (string) $company->id, $userId);

            $result['deleted'][] = (string) $product->name;
        }

        return $result;
    }

    public function blockingReason(Product $product): ?string
    {
        $variantIds = $product->relationLoaded('variants')
            ? $product->variants->pluck('id')->all()
            : ProductVariant::query()->withoutGlobalScopes()->where('product_id', $product->id)->pluck('id')->all();

        $usedIn = function (string $table) use ($product, $variantIds): bool {
            return DB::table($table)
                ->where(function ($q) use ($product, $variantIds) {
                    $q->where('product_id', $product->id);
                    if ($variantIds !== []) {
                        $q->orWhereIn('product_variant_id', $variantIds);
                    }
                })
                ->exists();
        };

        if ($usedIn('invoice_items')) {
            return self::REASON_INVOICES;
        }
        if ($usedIn('proposal_items')) {
            return self::REASON_QUOTES;
        }
        if ($variantIds !== [] && DB::table('commerce_order_items')->whereIn('product_variant_id', $variantIds)->exists()) {
            return self::REASON_ORDERS;
        }

        return null;
    }

    /**
     * French/English flash message, e.g. « 5 supprimé(s), 2 ignoré(s) car utilisés dans des factures ».
     *
     * @param  array{deleted: list<string>, skipped: array<string, list<string>>, not_found: int}  $result
     */
    public function summary(array $result): string
    {
        $parts = [__('commerce_bulk_deleted_count', ['count' => count($result['deleted'])])];

        foreach ($result['skipped'] as $reason => $names) {
            $list = implode(', ', array_slice($names, 0, 5)).(count($names) > 5 ? '…' : '');
            $parts[] = __('commerce_bulk_skipped_'.$reason, ['count' => count($names), 'names' => $list]);
        }

        return implode(' · ', $parts);
    }
}
