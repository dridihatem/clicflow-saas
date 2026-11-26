<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyModuleEntitlement;
use App\Models\MarketplaceOrder;

class ModuleEntitlementService
{
    /**
     * First-party capabilities implemented in the SaaS core (not zip plugins).
     *
     * @var list<string>
     */
    public const KEYS = [
        'ecommerce',
        'pos',
        'shopify',
        'woocommerce',
        'real_estate',
        'odoo',
    ];

    /**
     * Plan/core feature keys that must never surface as installable "modules" UI.
     *
     * @var list<string>
     */
    public const CORE_FEATURE_KEYS = [
        'ecommerce',
        'pos',
        'shopify',
        'woocommerce',
        'real_estate',
        'odoo',
    ];

    /** @var array<string, string> marketplace slug => primary module_key */
    public const MARKETPLACE_SLUG_MAP = [
        'ecommerce' => 'ecommerce',
        'retail-pos' => 'pos',
        'shopify-sync' => 'shopify',
        'woocommerce-sync' => 'woocommerce',
        'real-estate' => 'real_estate',
        'odoo-sync' => 'odoo',
    ];

    /**
     * Marketplace slug => first-party keys unlocked by that purchase.
     * E-commerce pack includes products/inventory and fullscreen POS.
     *
     * @var array<string, list<string>>
     */
    public const MARKETPLACE_SLUG_ENTITLEMENTS = [
        'ecommerce' => ['ecommerce', 'pos'],
        'retail-pos' => ['pos'],
        'shopify-sync' => ['shopify'],
        'woocommerce-sync' => ['woocommerce'],
        'real-estate' => ['real_estate'],
        'odoo-sync' => ['odoo'],
    ];

    /**
     * Extra zip/plugin slugs that alias core features (no marketplace map entry).
     *
     * @var list<string>
     */
    public const CORE_ALIAS_SLUGS = [
        'odoo',
        'odoo-sync',
        'pos',
        'shopify',
        'woocommerce',
        'real_estate',
    ];

    public function isEntitled(Company $company, string $moduleKey): bool
    {
        if (! in_array($moduleKey, self::KEYS, true)) {
            return false;
        }

        return CompanyModuleEntitlement::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('module_key', $moduleKey)
            ->exists();
    }

    /**
     * @return array<string, bool>
     */
    public function entitlementGates(Company $company): array
    {
        $owned = CompanyModuleEntitlement::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereIn('module_key', self::KEYS)
            ->pluck('module_key')
            ->all();

        $gates = [];
        foreach (self::KEYS as $key) {
            $gates[$key] = in_array($key, $owned, true);
        }

        return $gates;
    }

    public function grant(
        Company $company,
        string $moduleKey,
        string $source = 'marketplace',
        ?MarketplaceOrder $order = null,
    ): CompanyModuleEntitlement {
        return CompanyModuleEntitlement::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'company_id' => $company->id,
                'module_key' => $moduleKey,
            ],
            [
                'source' => $source,
                'marketplace_order_id' => $order?->id,
            ],
        );
    }

    public function grantFromMarketplaceSlug(Company $company, string $slug, ?MarketplaceOrder $order = null): ?CompanyModuleEntitlement
    {
        $keys = $this->keysForMarketplaceSlug($slug);
        if ($keys === []) {
            return null;
        }

        $last = null;
        foreach ($keys as $key) {
            $last = $this->grant($company, $key, 'marketplace', $order);
        }

        return $last;
    }

    /**
     * @return list<string>
     */
    public function keysForMarketplaceSlug(string $slug): array
    {
        $slug = strtolower(trim($slug));

        return self::MARKETPLACE_SLUG_ENTITLEMENTS[$slug]
            ?? (isset(self::MARKETPLACE_SLUG_MAP[$slug]) ? [self::MARKETPLACE_SLUG_MAP[$slug]] : []);
    }

    public function keyForMarketplaceSlug(string $slug): ?string
    {
        return $this->keysForMarketplaceSlug($slug)[0] ?? null;
    }

    public function isCoreFeatureKey(string $key): bool
    {
        return in_array($key, self::CORE_FEATURE_KEYS, true);
    }

    /**
     * Companion / plugin zip slugs that map onto SaaS core capabilities.
     * These must not appear in Settings → Modules or installed-module nav.
     */
    public function isCoreBundledSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }

        if (isset(self::MARKETPLACE_SLUG_MAP[$slug])) {
            return true;
        }

        if (in_array($slug, self::CORE_ALIAS_SLUGS, true)) {
            return true;
        }

        return in_array($slug, self::CORE_FEATURE_KEYS, true);
    }

    /**
     * @return list<string>
     */
    public function coreBundledSlugs(): array
    {
        return array_values(array_unique(array_merge(
            array_keys(self::MARKETPLACE_SLUG_MAP),
            self::CORE_ALIAS_SLUGS,
            self::CORE_FEATURE_KEYS,
        )));
    }
}
