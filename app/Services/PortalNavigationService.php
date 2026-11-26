<?php

namespace App\Services;

use App\Models\User;

class PortalNavigationService
{
    /**
     * Client portal nav: flat links grouped by section headings (ClicFlow-styled).
     *
     * @param  array<string, bool>  $gates
     * @return list<array{
     *     type: 'link'|'group'|'heading',
     *     label: string,
     *     icon?: string,
     *     url?: string,
     *     active: bool,
     *     children?: list<array{url: string, label: string, icon: string, active: bool}>
     * }>
     */
    public function sectionsFor(User $user, array $gates = []): array
    {
        $sections = [];

        $sections[] = [
            'type' => 'heading',
            'label' => __('portal_nav_my_account'),
            'active' => false,
        ];

        $sections[] = [
            'type' => 'link',
            'url' => route('portal.dashboard'),
            'label' => __('Overview'),
            'icon' => 'dashboard',
            'active' => request()->routeIs('portal.dashboard'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'prospects',
            'url' => route('portal.prospects.index'),
            'label' => __('portal_nav_prospects'),
            'icon' => 'fa-solid fa-address-book',
            'active' => request()->routeIs('portal.prospects.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'appointments',
            'url' => route('portal.prospects.appointments'),
            'label' => __('portal_nav_appointments'),
            'icon' => 'fa-solid fa-calendar-check',
            'active' => request()->routeIs('portal.prospects.appointments'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'campaigns',
            'url' => route('portal.campaigns.index'),
            'label' => __('portal_nav_campaigns'),
            'icon' => 'fa-solid fa-bullhorn',
            'active' => request()->routeIs('portal.campaigns.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'bookings',
            'url' => route('portal.bookings.index'),
            'label' => __('portal_nav_bookings'),
            'icon' => 'fa-solid fa-calendar-days',
            'active' => request()->routeIs('portal.bookings.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'assistant',
            'url' => route('portal.assistant.index'),
            'label' => __('portal_nav_assistant'),
            'icon' => 'fa-solid fa-wand-magic-sparkles',
            'active' => request()->routeIs('portal.assistant.*'),
        ];

        if ($user->can('portal.view_projects')) {
            $sections[] = [
                'type' => 'link',
            'section' => 'projects',
                'url' => route('portal.projects.index'),
                'label' => __('My projects'),
                'icon' => 'projects',
                'active' => request()->routeIs('portal.projects.*'),
            ];
        }

        if ($user->can('portal.view_proposals')) {
            $sections[] = [
                'type' => 'link',
            'section' => 'proposals',
                'url' => route('portal.proposals.index'),
                'label' => __('Quotes'),
                'icon' => 'fa-solid fa-file-contract',
                'active' => request()->routeIs('portal.proposals.*'),
            ];
        }

        if ($user->can('portal.view_invoices') || $user->can('portal.view_payments')) {
            $sections[] = [
                'type' => 'link',
            'section' => 'invoices',
                'url' => route('portal.payments.index'),
                'label' => __('Invoices'),
                'icon' => $user->can('portal.view_invoices') ? 'fa-solid fa-file-invoice' : 'billing',
                'active' => request()->routeIs('portal.payments.*') || request()->routeIs('portal.invoices.*'),
            ];
        }

        $sections[] = [
            'type' => 'link',
            'section' => 'vault',
            'url' => route('portal.vault.index'),
            'label' => __('vault_nav'),
            'icon' => 'vault',
            'active' => request()->routeIs('portal.vault.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'contracts',
            'url' => route('portal.contracts.index'),
            'label' => __('Contracts'),
            'icon' => 'fa-solid fa-file-signature',
            'active' => request()->routeIs('portal.contracts.*'),
        ];

        if ($user->can('portal.request_quote')) {
            $sections[] = [
                'type' => 'link',
            'section' => 'quote_requests',
                'url' => route('portal.quote-requests.index'),
                'label' => __('portal_quote_requests'),
                'icon' => 'fa-solid fa-file-circle-plus',
                'active' => request()->routeIs('portal.quote-requests.*'),
            ];
        }

        if ($gates['calendar'] ?? true) {
            $sections[] = [
                'type' => 'link',
            'section' => 'appointments',
                'url' => route('portal.calendar'),
                'label' => __('Calendar'),
                'icon' => 'calendar',
                'active' => request()->routeIs('portal.calendar'),
            ];
        }

        $sections[] = [
            'type' => 'heading',
            'label' => __('Nav section support'),
            'active' => false,
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'support',
            'url' => route('tickets.index'),
            'label' => __('portal_nav_support'),
            'icon' => 'tickets',
            'active' => request()->routeIs('tickets.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'section' => 'messages',
            'url' => route('chat.index'),
            'label' => __('Messages'),
            'icon' => 'messages',
            'active' => request()->routeIs('chat.*'),
        ];

        if ($user->can('portal.suggest_client_account')) {
            $sections[] = [
                'type' => 'link',
            'section' => 'invite_colleague',
                'url' => route('portal.client-account-requests.create'),
                'label' => __('Invite colleague'),
                'icon' => 'user-plus',
                'active' => request()->routeIs('portal.client-account-requests.*'),
            ];
        }

        $sections[] = [
            'type' => 'link',
            'section' => 'activity',
            'url' => route('notifications.index'),
            'label' => __('Activity'),
            'icon' => 'activity',
            'active' => request()->routeIs('notifications.*'),
        ];

        $sections[] = [
            'type' => 'link',
            'url' => route('profile.edit'),
            'label' => __('Account'),
            'icon' => 'profile',
            'active' => request()->routeIs('profile.*'),
        ];

        return $this->applyVisibility($user, $sections);
    }

    /**
     * Drop links the company hid for this client, then drop headings left without links.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return list<array<string, mixed>>
     */
    private function applyVisibility(User $user, array $sections): array
    {
        $map = app(PortalVisibilityService::class)->mapForUser($user);
        $sections = array_values(array_filter($sections, function (array $s) use ($map): bool {
            $key = $s['section'] ?? null;

            return ! is_string($key) || ($map[$key] ?? true);
        }));

        $out = [];
        foreach ($sections as $i => $s) {
            if (($s['type'] ?? '') === 'heading') {
                $next = $sections[$i + 1] ?? null;
                if (! $next || ($next['type'] ?? '') === 'heading') {
                    continue;
                }
            }
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @param  list<array{active?: bool}>  $items
     */
    private function anyActive(array $items): bool
    {
        foreach ($items as $item) {
            if (! empty($item['active'])) {
                return true;
            }
        }

        return false;
    }
}
