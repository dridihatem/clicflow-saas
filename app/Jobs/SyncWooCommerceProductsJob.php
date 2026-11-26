<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\Commerce\WooCommerceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncWooCommerceProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $companyId) {}

    public function handle(WooCommerceSyncService $woo): void
    {
        $company = Company::query()->find($this->companyId);
        if (! $company) {
            return;
        }

        $woo->pullAll($company);
    }
}
