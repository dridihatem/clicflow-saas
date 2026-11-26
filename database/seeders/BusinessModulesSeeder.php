<?php

namespace Database\Seeders;

use App\Enums\MarketplaceModuleBillingPeriod;
use App\Enums\MarketplaceModuleCategory;
use App\Models\MarketplaceModule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BusinessModulesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'slug' => 'ecommerce',
                'name' => 'E-commerce',
                'description' => 'All-in-one pack: products & variants, inventory, orders, deliveries, and professional fullscreen POS.',
                'detail_content' => "First-party FlowDesk E-commerce pack (products + inventory + POS).\n\n"
                    ."• Product catalog with color/size variants\n"
                    ."• Stock movements and low-stock awareness\n"
                    ."• Internal orders, deliveries, and invoices linked to revenue\n"
                    ."• Professional fullscreen retail POS (cash/card sessions)\n\n"
                    ."Purchase unlocks Products, Inventory, and POS in your workspace nav. Companion zip downloads as one package.",
                'category' => MarketplaceModuleCategory::Ecommerce,
                'price_minor' => 4900,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'doc',
                'feature_bullets' => [
                    'Products & variants',
                    'Inventory / stock tracking',
                    'Fullscreen retail POS',
                    'Orders, deliveries & invoices',
                    'One marketplace pack + companion zip',
                ],
                'sort_order' => 5,
                'is_published' => true,
            ],
            [
                'slug' => 'retail-pos',
                'name' => 'Retail POS',
                'description' => 'Deprecated standalone listing — included in the E-commerce pack.',
                'detail_content' => "Retail POS is now part of the E-commerce marketplace pack (products, inventory, and POS together).\n\nExisting purchasers keep access; new customers should buy E-commerce.",
                'category' => MarketplaceModuleCategory::Pos,
                'price_minor' => 3900,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'coins',
                'feature_bullets' => ['Included in E-commerce pack'],
                'sort_order' => 99,
                'is_published' => false,
            ],
            [
                'slug' => 'shopify-sync',
                'name' => 'Shopify sync',
                'description' => 'Connect Shopify and sync products, prices, and inventory both ways.',
                'detail_content' => "Shopify connector for FlowDesk commerce.\n\nPull products from Shopify, push price and stock updates when you change them in FlowDesk, and accept inbound webhooks.\n\nRequires the E-commerce pack for the shared product catalog.",
                'category' => MarketplaceModuleCategory::Saas,
                'price_minor' => 1900,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'puzzle',
                'feature_bullets' => ['Product pull', 'Price push', 'Inventory sync', 'Webhooks', 'Installable zip companion'],
                'sort_order' => 7,
                'is_published' => true,
            ],
            [
                'slug' => 'woocommerce-sync',
                'name' => 'WooCommerce sync',
                'description' => 'Connect WooCommerce and sync products, prices, and inventory.',
                'detail_content' => "WooCommerce REST connector.\n\nPull products and variations, push price/stock changes, and process inbound webhooks.\n\nRequires the E-commerce pack for the shared product catalog.",
                'category' => MarketplaceModuleCategory::Saas,
                'price_minor' => 1900,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'puzzle',
                'feature_bullets' => ['REST API connect', 'Variation support', 'Price & stock push', 'Webhooks', 'Installable zip companion'],
                'sort_order' => 8,
                'is_published' => true,
            ],
            [
                'slug' => 'real-estate',
                'name' => 'Real estate',
                'description' => 'Offers, demands, images, property types, and contracts (sale, rent, vacation).',
                'detail_content' => "Real estate workspace for agencies.\n\nManage offers (residence, duplex, triplex, terrain, bureau), transactions (achat, vente, location, location vacance), client demands, gallery images, and contracts.\n\nCompanion zip installs a hub under Modules.",
                'category' => MarketplaceModuleCategory::RealEstate,
                'price_minor' => 4900,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::Monthly,
                'icon' => 'building',
                'feature_bullets' => ['Offers & demands', 'Image gallery', 'Property & deal types', 'Contracts', 'Installable zip companion'],
                'sort_order' => 4,
                'is_published' => true,
            ],
            [
                'slug' => 'odoo-sync',
                'name' => 'Odoo sync',
                'description' => 'Connect Odoo and sync clients, quotes, invoices, and projects — free for every workspace.',
                'detail_content' => "Free first-party Odoo connector for ClicFlow.\n\nActivate the module, then open Settings → Odoo to connect your database via JSON-RPC and synchronize clients, quotes, invoices, and projects both ways.\n\nNo marketplace payment required.",
                'category' => MarketplaceModuleCategory::Saas,
                'price_minor' => 0,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
                'icon' => 'puzzle',
                'feature_bullets' => [
                    'Free activation',
                    'Clients, quotes, invoices & projects',
                    'JSON-RPC connection test',
                    'Bidirectional sync',
                    'Settings → Odoo after activate',
                ],
                'sort_order' => 3,
                'is_published' => true,
            ],
            [
                'slug' => 'comptabilite',
                'name' => 'Comptabilité',
                'description' => 'Standard international accounting: chart of accounts, journals, entries, and reports.',
                'detail_content' => "Installable ClicFlow accounting module for every country.\n\n• Chart of accounts (assets, liabilities, equity, revenue, expenses)\n• Journals and balanced double-entry postings\n• Trial balance, P&L and balance sheet summaries\n\nDownload the zip from the purchase, or install storage/stubs/installable/zips/comptabilite.zip under Settings → Modules.\n\nÉtude de projet is a built-in workspace feature (sidebar, next to Contracts), not part of this module.",
                'category' => MarketplaceModuleCategory::Finance,
                'price_minor' => 0,
                'currency' => 'USD',
                'billing_period' => MarketplaceModuleBillingPeriod::OneTime,
                'icon' => 'coins',
                'feature_bullets' => [
                    'International chart of accounts',
                    'Journals & balanced entries',
                    'Financial reports',
                ],
                'sort_order' => 2,
                'is_published' => true,
            ],
        ];

        foreach ($rows as $row) {
            $module = MarketplaceModule::query()->updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'target_countries' => null,
                ]),
            );

            $this->attachCompanionZip($module);
        }
    }

    private function attachCompanionZip(MarketplaceModule $module): void
    {
        $candidates = [
            base_path('storage/stubs/business/zips/'.$module->slug.'.zip'),
            base_path('storage/stubs/installable/zips/'.$module->slug.'.zip'),
        ];
        $source = null;
        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $source = $candidate;
                break;
            }
        }
        if ($source === null) {
            return;
        }

        $relative = 'marketplace-modules/'.$module->id.'/package.zip';
        $absolute = Storage::disk('local')->path($relative);
        File::ensureDirectoryExists(dirname($absolute));
        File::copy($source, $absolute);

        $module->forceFill(['zip_path' => $relative])->save();
    }
}
