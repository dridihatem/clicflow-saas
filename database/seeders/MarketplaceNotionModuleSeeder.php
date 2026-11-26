<?php

namespace Database\Seeders;

use App\Enums\MarketplaceModuleBillingPeriod;
use App\Enums\MarketplaceModuleCategory;
use App\Models\MarketplaceModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class MarketplaceNotionModuleSeeder extends Seeder
{
    public function run(): void
    {
        if (! Schema::hasTable('marketplace_modules')) {
            return;
        }

        MarketplaceModule::query()->updateOrCreate(
            ['slug' => 'notion-sync'],
            [
                'name' => 'Notion Sync',
                'description' => 'Sync ClicFlow projects to a Notion database.',
                'detail_content' => 'Install the Notion Sync module to push project titles and status to your Notion workspace.',
                'category' => MarketplaceModuleCategory::General,
                'price_minor' => 0,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'documents',
                'feature_bullets' => ['Project push to Notion', 'Token-based API', 'Per-workspace settings'],
                'sort_order' => 45,
                'is_published' => true,
            ],
        );
    }
}
