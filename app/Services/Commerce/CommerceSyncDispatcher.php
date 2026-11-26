<?php

namespace App\Services\Commerce;

use App\Jobs\PushVariantToShopifyJob;
use App\Jobs\PushVariantToWooCommerceJob;
use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Models\ProductVariant;

class CommerceSyncDispatcher
{
    public function pushVariantIfNeeded(Company $company, ProductVariant $variant): void
    {
        $integrations = CommerceIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('auto_push', true)
            ->get()
            ->keyBy('provider');

        if ($integrations->has('shopify')) {
            PushVariantToShopifyJob::dispatch((string) $company->id, (string) $variant->id);
        }

        if ($integrations->has('woocommerce')) {
            PushVariantToWooCommerceJob::dispatch((string) $company->id, (string) $variant->id);
        }
    }
}
