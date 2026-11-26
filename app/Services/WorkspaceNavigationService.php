<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;

class WorkspaceNavigationService
{
    /**
     * Catalog of the toggleable staff sidebar items.
     * `visible` receives (User $user, array $gates) and applies the same
     * permission / plan-gate rules as the previous hardcoded sidebar.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            'dashboard' => [
                'section' => 'operations',
                'label' => fn () => __('Dashboard'),
                'icon' => 'dashboard',
                'route' => 'dashboard',
                'patterns' => ['dashboard'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.view_dashboard'),
            ],
            'calendar' => [
                'section' => 'operations',
                'label' => fn () => __('Calendar'),
                'icon' => 'calendar',
                'route' => 'calendar.index',
                'patterns' => ['calendar.*'],
                'visible' => fn (User $u, array $g) => ($g['calendar'] ?? true) && $u->can('workspace.view_dashboard'),
            ],
            'clients' => [
                'section' => 'operations',
                'label' => fn () => __('Clients'),
                'icon' => 'clients',
                'route' => 'clients.index',
                'patterns' => ['clients.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_clients'),
            ],
            'prospects' => [
                'section' => 'marketing_growth',
                'label' => fn () => __('Prospects'),
                'icon' => 'prospects',
                'route' => 'prospects.index',
                'patterns' => ['prospects.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_clients'),
            ],
            'projects' => [
                'section' => 'operations',
                'label' => fn () => __('Projects'),
                'icon' => 'projects',
                'route' => 'projects.index',
                'patterns' => ['projects.*'],
                'visible' => fn (User $u, array $g) => ($g['projects'] ?? true) && $u->can('workspace.manage_projects'),
            ],
            'vault' => [
                'section' => 'operations',
                'label' => fn () => __('vault_nav'),
                'hint' => fn () => __('vault_nav_hint'),
                'icon' => 'vault',
                'route' => 'vault.index',
                'patterns' => ['vault.*'],
                'visible' => fn (User $u, array $g) => $u->hasAnyRole(['company_admin', 'team_member']),
            ],
            'inquiries' => [
                'section' => 'operations',
                'label' => fn () => __('Inquiries'),
                'icon' => 'inquiries',
                'route' => 'inquiries.index',
                'patterns' => ['inquiries.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_inquiries'),
            ],
            'proposals' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Proposals'),
                'icon' => 'proposals',
                'route' => 'proposals.index',
                'patterns' => ['proposals.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_invoices'),
            ],
            'contracts' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Contracts'),
                'icon' => 'contracts',
                'route' => 'contracts.index',
                'patterns' => ['contracts.*', 'contract-templates.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_clients'),
            ],
            'project_studies' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Project studies'),
                'icon' => 'fa-solid fa-file-lines',
                'route' => 'project-studies.index',
                'patterns' => ['project-studies.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_clients'),
            ],
            'invoices' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Invoices'),
                'icon' => 'invoices',
                'route' => 'invoices.index',
                'patterns' => ['invoices.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_invoices'),
            ],
            'commerce_products' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Products & services'),
                'icon' => 'fa-solid fa-boxes-stacked',
                'route' => 'commerce.products.index',
                'patterns' => ['commerce.products.*', 'commerce.variants.*'],
                'visible' => fn (User $u, array $g) => (
                    ($g['ecommerce'] ?? false) || ($u->can('workspace.manage_invoices'))
                ) && ($u->can('workspace.manage_commerce') || $u->can('workspace.manage_invoices')),
            ],
            'data_import' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Data import'),
                'icon' => 'fa-solid fa-file-import',
                'route' => 'import.index',
                'patterns' => ['import.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_invoices'),
            ],
            'billing' => [
                'section' => 'sales_billing',
                'label' => fn () => __('Plan subscription'),
                'icon' => 'billing',
                'route' => 'billing.index',
                'patterns' => ['billing.*'],
                'visible' => fn (User $u, array $g) => $u->can('workspace.manage_invoices') && $u->can('workspace.manage_subscription'),
            ],
            'analytics' => [
                'section' => 'insights',
                'label' => fn () => __('Analytics'),
                'icon' => 'analytics',
                'route' => 'analytics.index',
                'patterns' => ['analytics.*'],
                'visible' => fn (User $u, array $g) => ($g['analytics'] ?? true) && $u->can('workspace.view_analytics'),
            ],
            'reports' => [
                'section' => 'insights',
                'label' => fn () => __('Reports'),
                'icon' => 'fa-regular fa-file-lines',
                'route' => 'reports.index',
                'patterns' => ['reports.*'],
                'visible' => fn (User $u, array $g) => ($g['reports'] ?? true) && $u->can('workspace.view_analytics'),
            ],
            'marketing' => [
                'section' => 'marketing_growth',
                'label' => fn () => __('Marketing'),
                'icon' => 'marketing',
                'route' => 'marketing.hub',
                'patterns' => ['marketing.hub*'],
                'visible' => fn (User $u, array $g) => ($g['marketing_hub'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'forms' => [
                'section' => 'marketing_growth',
                'label' => fn () => __('Lead forms'),
                'icon' => 'forms',
                'route' => 'forms.index',
                'patterns' => ['forms.*'],
                'visible' => fn (User $u, array $g) => ($g['forms'] ?? true) && $u->can('workspace.manage_settings') && $u->can('workspace.manage_projects'),
            ],
            'em_overview' => [
                'section' => 'email_marketing',
                'label' => fn () => __('Overview'),
                'icon' => 'email-marketing',
                'route' => 'email-marketing.index',
                'patterns' => ['email-marketing.index'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'em_campaigns' => [
                'section' => 'email_marketing',
                'label' => fn () => __('Campaigns'),
                'icon' => 'fa-solid fa-paper-plane',
                'route' => 'email-marketing.campaigns.index',
                'patterns' => ['email-marketing.campaigns.*'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'em_templates' => [
                'section' => 'email_marketing',
                'label' => fn () => __('Templates'),
                'icon' => 'fa-regular fa-file-lines',
                'route' => 'email-marketing.templates.index',
                'patterns' => ['email-marketing.templates.*'],
                'exclude_patterns' => ['email-marketing.templates.create'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'em_template_new' => [
                'section' => 'email_marketing',
                'label' => fn () => __('email_marketing_nav_new_template'),
                'icon' => 'fa-solid fa-plus',
                'route' => 'email-marketing.templates.create',
                'patterns' => ['email-marketing.templates.create'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'em_audiences' => [
                'section' => 'email_marketing',
                'label' => fn () => __('Audiences'),
                'icon' => 'fa-solid fa-users',
                'route' => 'email-marketing.audiences.index',
                'patterns' => ['email-marketing.audiences.*'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'em_sequences' => [
                'section' => 'email_marketing',
                'label' => fn () => __('Sequences'),
                'icon' => 'fa-solid fa-route',
                'route' => 'email-marketing.sequences.index',
                'patterns' => ['email-marketing.sequences.*'],
                'visible' => fn (User $u, array $g) => ($g['email_marketing'] ?? true) && $u->can('workspace.manage_settings'),
            ],
            'providers' => [
                'section' => 'partners',
                'label' => fn () => __('Providers'),
                'icon' => 'providers',
                'route' => 'providers.index',
                'patterns' => ['providers.*', 'providers.remittance-requests.*'],
                'visible' => fn (User $u, array $g) => ($g['providers'] ?? true) && $u->can('workspace.manage_providers'),
            ],
            'provider_portal' => [
                'section' => 'partners',
                'label' => fn () => __('Provider portal'),
                'hint' => fn () => __('provider_portal_nav_hint'),
                'icon' => 'provider',
                'route' => 'provider.dashboard',
                'patterns' => ['provider.*'],
                'visible' => fn (User $u, array $g) => $u->hasRole('business_provider'),
            ],
            'provider_vault' => [
                'section' => 'partners',
                'label' => fn () => __('vault_nav'),
                'hint' => fn () => __('vault_nav_hint'),
                'icon' => 'vault',
                'route' => 'provider.vault.index',
                'patterns' => ['provider.vault.*'],
                'visible' => fn (User $u, array $g) => $u->hasRole('business_provider'),
            ],
            'hr_overview' => [
                'section' => 'hr',
                'label' => fn () => __('Overview'),
                'icon' => 'fa-solid fa-gauge-high',
                'route' => 'hr.index',
                'patterns' => ['hr.index'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_employees' => [
                'section' => 'hr',
                'label' => fn () => __('Employees'),
                'icon' => 'fa-solid fa-id-badge',
                'route' => 'hr.employees.index',
                'patterns' => ['hr.employees.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_departments' => [
                'section' => 'hr',
                'label' => fn () => __('hr_departments'),
                'icon' => 'fa-solid fa-sitemap',
                'route' => 'hr.departments.index',
                'patterns' => ['hr.departments.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_leave' => [
                'section' => 'hr',
                'label' => fn () => __('hr_leave'),
                'icon' => 'fa-solid fa-plane-departure',
                'route' => 'hr.leave.index',
                'patterns' => ['hr.leave.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_payroll' => [
                'section' => 'hr',
                'label' => fn () => __('hr_payroll'),
                'icon' => 'fa-solid fa-money-check-dollar',
                'route' => 'hr.payroll.index',
                'patterns' => ['hr.payroll.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_advances' => [
                'section' => 'hr',
                'label' => fn () => __('hr_salary_advances'),
                'icon' => 'fa-solid fa-hand-holding-dollar',
                'route' => 'hr.advances.index',
                'patterns' => ['hr.advances.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_settings' => [
                'section' => 'hr',
                'label' => fn () => __('hr_settings'),
                'icon' => 'fa-solid fa-sliders',
                'route' => 'hr.settings.index',
                'patterns' => ['hr.settings.*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && $u->can('workspace.manage_hr'),
            ],
            'hr_me_leave' => [
                'section' => 'my_hr',
                'label' => fn () => __('hr_request_leave'),
                'icon' => 'fa-solid fa-plane-departure',
                'route' => 'hr.me.leave',
                'patterns' => ['hr.me.leave*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && ($u->can('workspace.hr_self_service') || $u->can('workspace.manage_hr')),
            ],
            'hr_me_advances' => [
                'section' => 'my_hr',
                'label' => fn () => __('hr_request_advance'),
                'icon' => 'fa-solid fa-hand-holding-dollar',
                'route' => 'hr.me.advances',
                'patterns' => ['hr.me.advances*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && ($u->can('workspace.hr_self_service') || $u->can('workspace.manage_hr')),
            ],
            'hr_me_payslips' => [
                'section' => 'my_hr',
                'label' => fn () => __('hr_my_payslips'),
                'icon' => 'fa-solid fa-file-invoice-dollar',
                'route' => 'hr.me.payslips',
                'patterns' => ['hr.me.payslips*'],
                'visible' => fn (User $u, array $g) => ($g['hr'] ?? true) && ($u->can('workspace.hr_self_service') || $u->can('workspace.manage_hr')),
            ],
            'commerce_orders' => [
                'section' => 'commerce',
                'label' => fn () => __('Orders'),
                'icon' => 'fa-solid fa-receipt',
                'route' => 'commerce.orders.index',
                'patterns' => ['commerce.orders.*'],
                'visible' => fn (User $u, array $g) => ($g['ecommerce'] ?? false) && ($u->can('workspace.manage_commerce') || $u->can('workspace.manage_invoices')),
            ],
            'commerce_deliveries' => [
                'section' => 'commerce',
                'label' => fn () => __('Deliveries'),
                'icon' => 'fa-solid fa-truck',
                'route' => 'commerce.deliveries.index',
                'patterns' => ['commerce.deliveries.*'],
                'visible' => fn (User $u, array $g) => ($g['ecommerce'] ?? false) && ($u->can('workspace.manage_commerce') || $u->can('workspace.manage_invoices')),
            ],
            'pos' => [
                'section' => 'commerce',
                'label' => fn () => __('POS'),
                'icon' => 'fa-solid fa-cash-register',
                'route' => 'pos.index',
                'patterns' => ['pos.*'],
                'visible' => fn (User $u, array $g) => ($g['pos'] ?? false) && ($u->can('workspace.manage_commerce') || $u->can('workspace.manage_invoices')),
            ],
            'real_estate_offers' => [
                'section' => 'real_estate',
                'label' => fn () => __('Offers'),
                'icon' => 'fa-solid fa-building',
                'route' => 'real-estate.offers.index',
                'patterns' => ['real-estate.offers.*', 'real-estate.images.*'],
                'visible' => fn (User $u, array $g) => ($g['real_estate'] ?? false) && ($u->can('workspace.manage_real_estate') || $u->can('workspace.manage_clients')),
            ],
            'real_estate_demands' => [
                'section' => 'real_estate',
                'label' => fn () => __('Demands'),
                'icon' => 'fa-solid fa-hand-holding-heart',
                'route' => 'real-estate.demands.index',
                'patterns' => ['real-estate.demands.*'],
                'visible' => fn (User $u, array $g) => ($g['real_estate'] ?? false) && ($u->can('workspace.manage_real_estate') || $u->can('workspace.manage_clients')),
            ],
            'real_estate_contracts' => [
                'section' => 'real_estate',
                'label' => fn () => __('Contracts'),
                'icon' => 'fa-solid fa-file-contract',
                'route' => 'real-estate.contracts.index',
                'patterns' => ['real-estate.contracts.*'],
                'visible' => fn (User $u, array $g) => ($g['real_estate'] ?? false) && ($u->can('workspace.manage_real_estate') || $u->can('workspace.manage_clients')),
            ],
            'shopify_settings' => [
                'section' => 'commerce',
                'label' => fn () => __('Shopify'),
                'icon' => 'fa-brands fa-shopify',
                'route' => 'settings.shopify',
                'patterns' => ['settings.shopify*'],
                'visible' => fn (User $u, array $g) => ($g['shopify'] ?? false) && $u->can('workspace.manage_settings'),
            ],
            'woocommerce_settings' => [
                'section' => 'commerce',
                'label' => fn () => __('WooCommerce'),
                'icon' => 'fa-brands fa-wordpress',
                'route' => 'settings.woocommerce',
                'patterns' => ['settings.woocommerce*'],
                'visible' => fn (User $u, array $g) => ($g['woocommerce'] ?? false) && $u->can('workspace.manage_settings'),
            ],
            'activity' => [
                'section' => 'productivity',
                'label' => fn () => __('Activity'),
                'icon' => 'activity',
                'route' => 'notifications.index',
                'patterns' => ['notifications.*'],
                'visible' => fn (User $u, array $g) => true,
            ],
            'assistant' => [
                'section' => 'nova',
                'label' => fn () => __('nova_tab_chat'),
                'icon' => 'ai',
                'route' => 'assistant.index',
                'url' => fn () => route('assistant.index'),
                'patterns' => ['assistant.*'],
                'active' => fn () => request()->routeIs('assistant.*') && ! request()->boolean('writing'),
                'visible' => fn (User $u, array $g) => (bool) config('flowdesk.nova_nav_chat', false)
                    && ($g['ai_credits'] ?? true)
                    && $u->can('workspace.view_dashboard')
                    && $u->hasAnyRole(['company_admin', 'team_member']),
            ],
            'assistant_writing' => [
                'section' => 'nova',
                'label' => fn () => __('nova_tab_writing'),
                'icon' => 'fa-solid fa-pen-nib',
                'route' => 'assistant.index',
                'url' => fn () => route('assistant.index', ['writing' => 1]),
                'patterns' => ['assistant.*'],
                'active' => fn () => request()->routeIs('assistant.*') && request()->boolean('writing'),
                'visible' => fn (User $u, array $g) => (bool) config('flowdesk.nova_nav_chat', false)
                    && ($g['ai_credits'] ?? true)
                    && $u->can('workspace.view_dashboard')
                    && $u->hasAnyRole(['company_admin', 'team_member']),
            ],
            'growth_advisor' => [
                'section' => 'nova',
                'label' => fn () => __('AI growth advisor'),
                'icon' => 'fa-solid fa-chart-line',
                'route' => 'growth-advisor.index',
                'patterns' => ['growth-advisor.*'],
                'visible' => fn (User $u, array $g) => (bool) config('flowdesk.nova_nav_chat', false)
                    && ($g['ai_credits'] ?? true)
                    && $u->can('workspace.view_dashboard')
                    && $u->hasAnyRole(['company_admin', 'team_member']),
            ],
            'messages' => [
                'section' => 'support',
                'label' => fn () => __('Messages'),
                'icon' => 'messages',
                'route' => 'chat.index',
                'patterns' => ['chat.*'],
                'visible' => fn (User $u, array $g) => true,
            ],
            'tickets' => [
                'section' => 'support',
                'label' => fn () => __('Tickets'),
                'icon' => 'tickets',
                'route' => 'tickets.index',
                'patterns' => ['tickets.*'],
                'visible' => fn (User $u, array $g) => true,
            ],
        ];
    }

    /**
     * Sidebar sections rendered as direct links (no flyout group).
     *
     * @return list<string>
     */
    public function flatSidebarSections(): array
    {
        return ['operations'];
    }

    /**
     * @return array<string, string> section key => label
     */
    public function sectionLabels(): array
    {
        return [
            'operations' => __('Nav section operations'),
            'sales_billing' => __('Nav section sales_billing'),
            'insights' => __('Nav section insights'),
            'marketing_growth' => __('Nav section marketing_growth'),
            'email_marketing' => __('Nav section email_marketing'),
            'partners' => __('Nav section partners'),
            'nova' => config('flowdesk.ai_assistant_brand_name', 'Nova'),
            'productivity' => __('Nav section productivity'),
            'support' => __('Nav section support'),
            'hr' => __('Nav section hr'),
            'my_hr' => __('Nav section my_hr'),
            'commerce' => __('Nav section commerce'),
            'real_estate' => __('Nav section real_estate'),
        ];
    }

    /**
     * @return array<string, string> section key => nav-icon name
     */
    public function sectionIcons(): array
    {
        return [
            'operations' => 'dashboard',
            'sales_billing' => 'invoices',
            'insights' => 'analytics',
            'marketing_growth' => 'marketing',
            'email_marketing' => 'email-marketing',
            'partners' => 'providers',
            'nova' => 'ai',
            'productivity' => 'activity',
            'support' => 'messages',
            'hr' => 'fa-solid fa-people-group',
            'my_hr' => 'fa-solid fa-user-clock',
            'commerce' => 'fa-solid fa-store',
            'real_estate' => 'fa-solid fa-building',
        ];
    }

    /**
     * @return list<string>
     */
    public function defaultSectionOrder(): array
    {
        $order = [];
        foreach ($this->catalog() as $item) {
            $section = $item['section'];
            if (! in_array($section, $order, true)) {
                $order[] = $section;
            }
        }

        return $order;
    }

    /**
     * @return array{hidden: list<string>, order: list<string>, section_order: list<string>}
     */
    public function preferencesFor(Company $company): array
    {
        $settings = CompanySetting::query()->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $nav = is_array($settings?->navigation) ? $settings->navigation : [];

        $hidden = array_values(array_filter(
            (array) ($nav['hidden'] ?? []),
            fn ($v) => is_string($v),
        ));
        $order = array_values(array_filter(
            (array) ($nav['order'] ?? []),
            fn ($v) => is_string($v),
        ));
        $sectionOrder = array_values(array_filter(
            (array) ($nav['section_order'] ?? []),
            fn ($v) => is_string($v),
        ));

        return ['hidden' => $hidden, 'order' => $order, 'section_order' => $sectionOrder];
    }

    /**
     * @param  array{hidden?: list<string>, order?: list<string>, section_order?: list<string>}  $preferences
     */
    public function savePreferences(Company $company, array $preferences, ?User $user = null): void
    {
        $keys = array_keys($this->catalog());
        $manageable = array_flip($this->manageableItemKeys($company, $user));
        $prev = $this->preferencesFor($company);

        $submittedHidden = array_values(array_intersect($preferences['hidden'] ?? [], $keys));
        $preservedHidden = array_values(array_filter(
            $prev['hidden'],
            fn (string $key): bool => ! isset($manageable[$key]) && in_array($key, $keys, true),
        ));
        $hidden = array_values(array_unique([...$submittedHidden, ...$preservedHidden]));

        $submittedOrder = array_values(array_intersect($preferences['order'] ?? [], $keys));
        $preservedOrder = array_values(array_filter(
            $prev['order'],
            fn (string $key): bool => ! isset($manageable[$key]) && in_array($key, $keys, true),
        ));
        $order = array_values(array_unique([...$submittedOrder, ...$preservedOrder]));

        $validSections = $this->defaultSectionOrder();
        $manageableSections = array_flip(array_column($this->manageableSectionsFor($company, $user), 'key'));
        $submittedSectionOrder = array_values(array_intersect($preferences['section_order'] ?? [], $validSections));
        $preservedSectionOrder = array_values(array_filter(
            $prev['section_order'],
            fn (string $key): bool => ! isset($manageableSections[$key]) && in_array($key, $validSections, true),
        ));
        $sectionOrder = array_values(array_unique([...$submittedSectionOrder, ...$preservedSectionOrder]));

        $settings = CompanySetting::query()->withoutGlobalScopes()
            ->firstOrCreate(['company_id' => $company->id]);
        $settings->update([
            'navigation' => [
                'hidden' => $hidden,
                'order' => $order,
                'section_order' => $sectionOrder,
            ],
        ]);
    }

    /**
     * Sections + items ready for the sidebar: filtered by permissions/gates,
     * minus the hidden items, ordered by the workspace preferences.
     *
     * @param  array<string, bool>  $gates
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function sectionsFor(User $user, array $gates): array
    {
        $company = $user->company;
        $prefs = $company ? $this->preferencesFor($company) : ['hidden' => [], 'order' => [], 'section_order' => []];

        $items = $this->orderedCatalog($prefs['order']);
        $labels = $this->sectionLabels();
        $icons = $this->sectionIcons();
        $sections = [];

        foreach ($items as $key => $item) {
            if (in_array($key, $prefs['hidden'], true)) {
                continue;
            }
            if (! ($item['visible'])($user, $gates)) {
                continue;
            }

            $section = $item['section'];
            $sections[$section] ??= [
                'key' => $section,
                'label' => $labels[$section] ?? $section,
                'icon' => $icons[$section] ?? 'navigation',
                'flat' => in_array($section, $this->flatSidebarSections(), true),
                'items' => [],
            ];
            $sections[$section]['items'][] = [
                'key' => $key,
                'label' => ($item['label'])(),
                'icon' => $item['icon'],
                'url' => $this->resolveItemUrl($item),
                'active' => isset($item['active']) && is_callable($item['active'])
                    ? (bool) ($item['active'])()
                    : $this->isActive($item),
            ];
        }

        return $this->orderedSections($sections, $prefs['section_order']);
    }

    /**
     * Catalog grouped by section for the settings page. Permissions are evaluated
     * for the acting admin so they can toggle anything they can access; module
     * gates (ecommerce, real_estate, …) still hide inactive packs — same as the
     * sidebar (e.g. Products only after the e-commerce module is activated).
     *
     * @return list<array{key: string, label: string, items: list<array<string, mixed>>}>
     */
    public function manageableSectionsFor(Company $company, ?User $user = null): array
    {
        $prefs = $this->preferencesFor($company);
        $items = $this->orderedCatalog($prefs['order']);
        $labels = $this->sectionLabels();
        $gates = app(PlanLimitService::class)->featureGates($company);
        $sections = [];

        foreach ($items as $key => $item) {
            if ($user instanceof User && ! ($item['visible'])($user, $gates)) {
                continue;
            }

            $section = $item['section'];
            $sections[$section] ??= [
                'key' => $section,
                'label' => $labels[$section] ?? $section,
                'items' => [],
            ];
            $entry = [
                'key' => $key,
                'label' => ($item['label'])(),
                'icon' => $item['icon'],
                'enabled' => ! in_array($key, $prefs['hidden'], true),
            ];
            if (isset($item['hint'])) {
                $entry['hint'] = ($item['hint'])();
            }
            $sections[$section]['items'][] = $entry;
        }

        return $this->orderedSections($sections, $prefs['section_order']);
    }

    /**
     * Nav item keys the company admin can manage right now (gates + permissions).
     *
     * @return list<string>
     */
    public function manageableItemKeys(Company $company, ?User $user = null): array
    {
        $keys = [];
        foreach ($this->manageableSectionsFor($company, $user) as $section) {
            foreach ($section['items'] as $item) {
                $keys[] = $item['key'];
            }
        }

        return $keys;
    }

    /**
     * @param  array<string, array<string, mixed>>  $sections
     * @param  list<string>  $sectionOrder
     * @return list<array<string, mixed>>
     */
    private function orderedSections(array $sections, array $sectionOrder): array
    {
        if ($sectionOrder === []) {
            return array_values($sections);
        }

        $sorted = [];
        foreach ($sectionOrder as $key) {
            if (isset($sections[$key])) {
                $sorted[] = $sections[$key];
                unset($sections[$key]);
            }
        }

        foreach ($sections as $section) {
            $sorted[] = $section;
        }

        return $sorted;
    }

    /**
     * @param  list<string>  $order
     * @return array<string, array<string, mixed>>
     */
    private function orderedCatalog(array $order): array
    {
        $catalog = $this->catalog();
        if ($order === []) {
            return $catalog;
        }

        $sorted = [];
        foreach ($order as $key) {
            if (isset($catalog[$key])) {
                $sorted[$key] = $catalog[$key];
                unset($catalog[$key]);
            }
        }

        if ($catalog === []) {
            return $sorted;
        }

        // Leftovers (items added after the saved order) slot after the last
        // saved neighbor that precedes them in the default catalog.
        $positions = array_flip(array_keys($this->catalog()));
        $keys = array_keys($sorted);
        foreach ($catalog as $key => $item) {
            $anchor = -1;
            foreach ($keys as $index => $savedKey) {
                if (($positions[$savedKey] ?? -1) < ($positions[$key] ?? PHP_INT_MAX)) {
                    $anchor = $index;
                }
            }
            array_splice($keys, $anchor + 1, 0, [$key]);
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $sorted[$key] ?? $catalog[$key];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function resolveItemUrl(array $item): string
    {
        if (isset($item['url']) && is_callable($item['url'])) {
            return (string) ($item['url'])();
        }

        $params = is_array($item['route_params'] ?? null) ? $item['route_params'] : [];

        return route($item['route'], $params);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isActive(array $item): bool
    {
        $patterns = $item['patterns'] ?? [];
        if (! is_array($patterns) || $patterns === []) {
            return false;
        }

        $active = request()->routeIs(...$patterns);
        if ($active && ! empty($item['exclude_patterns'])) {
            $active = ! request()->routeIs(...$item['exclude_patterns']);
        }

        return $active;
    }
}
