<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Commerce\ShopifySyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncShopifyProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $companyId) {}

    public function handle(ShopifySyncService $shopify): void
    {
        $company = Company::query()->find($this->companyId);
        if (! $company) {
            return;
        }

        $shopify->pullAll($company);
    }
}
