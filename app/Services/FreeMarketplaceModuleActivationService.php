<?php

namespace App\Services;

use App\Enums\MarketplaceOrderStatus;
use App\Models\Company;
use App\Models\CompanyMarketplaceModuleDismissal;
use App\Models\InstalledModule;
use App\Models\MarketplaceModule;
use App\Models\MarketplaceOrder;
use App\Models\MarketplaceOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FreeMarketplaceModuleActivationService
{
    public function __construct(
        private MarketplaceOrderFulfillmentService $fulfillment,
        private ModuleEntitlementService $entitlements,
        private MarketplaceModuleZipService $zipService,
        private ModuleInstallerService $installer,
    ) {}

    public function isFree(MarketplaceModule $module): bool
    {
        return $module->is_published && (int) $module->price_minor === 0;
    }

    public function isInstalled(Company $company, MarketplaceModule $module): bool
    {
        return InstalledModule::query()
            ->where('company_id', $company->id)
            ->where('slug', $module->slug)
            ->exists();
    }

    /**
     * Whether the free module card should be hidden (already usable / entitled).
     * Installable zip modules can be shown again after uninstall so the user can re-activate.
     */
    public function companyAlreadyOwns(Company $company, MarketplaceModule $module): bool
    {
        $slug = (string) $module->slug;

        if ($this->entitlements->isCoreBundledSlug($slug)) {
            if ($this->fulfillment->companyOwnsPaidModule($company, $module->id)) {
                return true;
            }

            foreach ($this->entitlements->keysForMarketplaceSlug($slug) as $key) {
                if ($this->entitlements->isEntitled($company, $key)) {
                    return true;
                }
            }

            return false;
        }

        // Real installable modules (e.g. comptabilite): "owned" for the free list means installed.
        return $this->isInstalled($company, $module);
    }

    /**
     * Activate a published free marketplace module for a workspace (no payment).
     * If previously activated then uninstalled, reinstalls without creating a duplicate order.
     */
    public function activate(User $user, MarketplaceModule $module): MarketplaceOrder
    {
        if (! $this->isFree($module)) {
            throw new RuntimeException(__('settings_modules_free_activate_not_free'));
        }

        $company = $user->company;
        if ($company === null) {
            throw new RuntimeException(__('settings_modules_free_activate_no_company'));
        }

        $this->clearDismissal($company, $module);

        if ($this->fulfillment->companyOwnsPaidModule($company, $module->id)) {
            return $this->reactivateOwned($company, $module, $user);
        }

        foreach ($this->entitlements->keysForMarketplaceSlug((string) $module->slug) as $key) {
            if ($this->entitlements->isEntitled($company, $key) && $this->entitlements->isCoreBundledSlug((string) $module->slug)) {
                throw new RuntimeException(__('settings_modules_free_activate_already', ['name' => $module->name]));
            }
        }

        $order = DB::transaction(function () use ($user, $company, $module) {
            $orderNumber = 'MK-FREE-'.strtoupper(Str::random(8));

            $order = MarketplaceOrder::query()->create([
                'order_number' => $orderNumber,
                'payment_reference' => $orderNumber,
                'status' => MarketplaceOrderStatus::Paid,
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_company' => $company->name,
                'user_id' => $user->id,
                'company_id' => $company->id,
                'total_minor' => 0,
                'currency' => strtoupper((string) ($module->currency ?: flowdesk_default_currency())),
                'paid_at' => now(),
                'metadata' => [
                    'payment_method' => 'free',
                    'activated_at' => now()->toIso8601String(),
                ],
            ]);

            MarketplaceOrderItem::query()->create([
                'marketplace_order_id' => $order->id,
                'marketplace_module_id' => $module->id,
                'module_slug' => $module->slug,
                'module_name' => $module->name,
                'price_minor' => 0,
                'currency' => $order->currency,
                'billing_period' => $module->billing_period->value,
            ]);

            return $order->load(['items.module', 'user.company', 'company']);
        });

        $this->fulfillment->fulfill($order->fresh(['items.module', 'user.company', 'company']));

        return $order->fresh(['items.module']);
    }

    private function reactivateOwned(Company $company, MarketplaceModule $module, User $user): MarketplaceOrder
    {
        $slug = (string) $module->slug;

        if ($this->entitlements->isCoreBundledSlug($slug)) {
            $this->entitlements->grantFromMarketplaceSlug($company, $slug);

            $order = $this->latestPaidOrder($company, $module);
            if ($order === null) {
                throw new RuntimeException(__('settings_modules_free_activate_already', ['name' => $module->name]));
            }

            return $order;
        }

        if (! $this->isInstalled($company, $module)) {
            $zipPath = $this->resolveInstallableZipPath($module);
            if ($zipPath === null) {
                throw new RuntimeException(__('marketing_checkout_no_zip'));
            }

            try {
                $this->installer->installFromStoredZip($company, $zipPath);
            } catch (RuntimeException $e) {
                // If a race left the module installed, continue to open it.
                if (! $this->isInstalled($company, $module)) {
                    throw $e;
                }
            }
        }

        $order = $this->latestPaidOrder($company, $module);
        if ($order === null) {
            throw new RuntimeException(__('settings_modules_free_activate_already', ['name' => $module->name]));
        }

        return $order;
    }

    private function resolveInstallableZipPath(MarketplaceModule $module): ?string
    {
        $zipPath = $this->zipService->absolutePath($module);
        if (is_string($zipPath) && is_file($zipPath)) {
            return $zipPath;
        }

        foreach ([
            base_path('storage/stubs/installable/zips/'.$module->slug.'.zip'),
            base_path('storage/stubs/business/zips/'.$module->slug.'.zip'),
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function latestPaidOrder(Company $company, MarketplaceModule $module): ?MarketplaceOrder
    {
        $item = MarketplaceOrderItem::query()
            ->where('marketplace_module_id', $module->id)
            ->whereHas('order', function ($query) use ($company): void {
                $query->where('status', MarketplaceOrderStatus::Paid)
                    ->where(function ($inner) use ($company): void {
                        $inner->where('company_id', $company->id)
                            ->orWhereIn('user_id', User::query()->where('company_id', $company->id)->pluck('id'));
                    });
            })
            ->latest()
            ->first();

        return $item?->order;
    }

    private function clearDismissal(Company $company, MarketplaceModule $module): void
    {
        CompanyMarketplaceModuleDismissal::query()
            ->where('company_id', $company->id)
            ->where(function ($query) use ($module): void {
                $query->where('module_slug', $module->slug);
                if ($module->id) {
                    $query->orWhere('marketplace_module_id', $module->id);
                }
            })
            ->delete();
    }

    /**
     * Settings route to open after activating a core free module, if any.
     */
    public function settingsRouteFor(MarketplaceModule|string $module): ?string
    {
        $slug = is_string($module) ? $module : (string) $module->slug;

        return match (strtolower($slug)) {
            'odoo-sync', 'odoo' => 'settings.odoo',
            'shopify-sync', 'shopify' => 'settings.shopify',
            'woocommerce-sync', 'woocommerce' => 'settings.woocommerce',
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}|null
     */
    public function openRoute(MarketplaceModule|string $module): ?array
    {
        $slug = is_string($module) ? strtolower($module) : strtolower((string) $module->slug);

        if ($slug === 'comptabilite') {
            return ['modules.show', ['slug' => 'comptabilite']];
        }

        $settings = $this->settingsRouteFor($slug);
        if ($settings !== null) {
            return [$settings, []];
        }

        return null;
    }
}
