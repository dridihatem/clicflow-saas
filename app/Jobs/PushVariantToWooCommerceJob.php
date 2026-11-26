<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\ProductVariant;
use App\Services\Commerce\WooCommerceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PushVariantToWooCommerceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $companyId,
        public string $variantId,
    ) {}

    public function handle(WooCommerceSyncService $woo): void
    {
        $company = Company::query()->find($this->companyId);
        $variant = ProductVariant::query()->withoutGlobalScopes()->with('product')->find($this->variantId);
        if (! $company || ! $variant) {
            return;
        }

        $woo->pushVariant($company, $variant);
    }
}
