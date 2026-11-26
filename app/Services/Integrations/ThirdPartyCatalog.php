<?php

namespace App\Services\Integrations;

use App\Models\CommerceIntegration;
use App\Models\Company;
use App\Services\Odoo\OdooConfigService;
use App\Services\PlanLimitService;

class ThirdPartyCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   title: string,
     *   summary: string,
     *   imports: list<string>,
     *   route: string,
     *   plan_feature: string|null,
     *   available: bool,
     *   connected: bool,
     *   last_synced_at: string|null
     * }>
     */
    public function cards(Company $company): array
    {
        $gates = app(PlanLimitService::class)->featureGates($company);
        $integrations = CommerceIntegration::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->get()
            ->keyBy('provider');

        $odoo = app(OdooConfigService::class)->toFormArray($company);

        return [
            $this->card(
                key: 'perfex',
                title: __('perfex_settings_title'),
                summary: __('integrations_card_perfex'),
                imports: [__('Prospects'), __('Clients'), __('Invoices'), __('Products & services')],
                route: 'settings.perfex',
                planFeature: null,
                available: true,
                connected: $this->commerceConnected($integrations->get('perfex')),
                lastSyncedAt: $integrations->get('perfex')?->last_synced_at?->toIso8601String(),
            ),
            $this->card(
                key: 'shopify',
                title: __('Shopify sync'),
                summary: __('integrations_card_shopify'),
                imports: [__('Products & services'), __('Clients')],
                route: 'settings.shopify',
                planFeature: 'shopify',
                available: (bool) ($gates['shopify'] ?? false),
                connected: $this->commerceConnected($integrations->get('shopify')),
                lastSyncedAt: $integrations->get('shopify')?->last_synced_at?->toIso8601String(),
            ),
            $this->card(
                key: 'woocommerce',
                title: __('WooCommerce sync'),
                summary: __('integrations_card_woocommerce'),
                imports: [__('Products & services'), __('Clients')],
                route: 'settings.woocommerce',
                planFeature: 'woocommerce',
                available: (bool) ($gates['woocommerce'] ?? false),
                connected: $this->commerceConnected($integrations->get('woocommerce')),
                lastSyncedAt: $integrations->get('woocommerce')?->last_synced_at?->toIso8601String(),
            ),
            $this->card(
                key: 'odoo',
                title: __('odoo_settings_title'),
                summary: __('integrations_card_odoo'),
                imports: [__('Clients'), __('Projects'), __('Quotes'), __('Invoices')],
                route: 'settings.odoo',
                planFeature: 'odoo',
                available: (bool) ($gates['odoo'] ?? false),
                connected: (bool) ($odoo['connected'] ?? false),
                lastSyncedAt: $odoo['last_sync_at'] ?? null,
            ),
            $this->card(
                key: 'quickbooks',
                title: __('quickbooks_settings_title'),
                summary: __('integrations_card_quickbooks'),
                imports: [__('Clients'), __('Invoices'), __('Products & services'), __('Quotes')],
                route: 'settings.quickbooks',
                planFeature: null,
                available: true,
                connected: $this->commerceConnected($integrations->get('quickbooks')),
                lastSyncedAt: $integrations->get('quickbooks')?->last_synced_at?->toIso8601String(),
            ),
            $this->card(
                key: 'trello',
                title: __('trello_settings_title'),
                summary: __('integrations_card_trello'),
                imports: [__('Projects'), __('Tasks')],
                route: 'settings.trello',
                planFeature: null,
                available: true,
                connected: $this->commerceConnected($integrations->get('trello')),
                lastSyncedAt: $integrations->get('trello')?->last_synced_at?->toIso8601String(),
            ),
            $this->card(
                key: 'notion',
                title: __('notion_settings_title'),
                summary: __('integrations_card_notion'),
                imports: [__('Projects')],
                route: 'settings.notion',
                planFeature: null,
                available: true,
                connected: $this->commerceConnected($integrations->get('notion')),
                lastSyncedAt: $integrations->get('notion')?->last_synced_at?->toIso8601String(),
            ),
        ];
    }

    private function commerceConnected(?CommerceIntegration $integration): bool
    {
        if (! $integration) {
            return false;
        }

        $creds = $integration->credentials ?? [];

        return collect($creds)->filter(fn ($value) => is_string($value) && trim($value) !== '')->isNotEmpty();
    }

    /**
     * @param  list<string>  $imports
     * @return array{
     *   key: string,
     *   title: string,
     *   summary: string,
     *   imports: list<string>,
     *   route: string,
     *   plan_feature: string|null,
     *   available: bool,
     *   connected: bool,
     *   last_synced_at: string|null
     * }
     */
    private function card(
        string $key,
        string $title,
        string $summary,
        array $imports,
        string $route,
        ?string $planFeature,
        bool $available,
        bool $connected,
        ?string $lastSyncedAt,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'summary' => $summary,
            'imports' => $imports,
            'route' => $route,
            'plan_feature' => $planFeature,
            'available' => $available,
            'connected' => $connected,
            'last_synced_at' => $lastSyncedAt,
        ];
    }
}
