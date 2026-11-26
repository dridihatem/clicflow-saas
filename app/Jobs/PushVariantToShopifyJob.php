<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\ProductVariant;
use App\Services\Commerce\ShopifySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushVariantToShopifyJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyId,
        public string $variantId,
    ) {}

    public function handle(ShopifySyncService $shopify): void
    {
        $company = Company::query()->find($this->companyId);
        $variant = ProductVariant::query()->withoutGlobalScopes()->with('product')->find($this->variantId);
        if (! $company || ! $variant) {
            return;
        }

        $shopify->pushVariant($company, $variant);
    }
}
