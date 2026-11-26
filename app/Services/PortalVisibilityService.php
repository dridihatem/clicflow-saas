<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Lets a company choose which client-portal sections its clients can see.
 *
 * Company level: company_settings.portal_visibility = {"hidden": ["invoices", ...]}
 * Client level:  clients.portal_visibility = {"invoices": "show"|"hide"} (absent = inherit)
 * Everything is visible by default.
 */
class PortalVisibilityService
{
    /** @var array<string, array<string, bool>> */
    private array $cache = [];

    /**
     * @return array<string, array{label: string, description: string, icon: string, tone: string, routes: list<string>}>
     */
    public function sections(): array
    {
        return [
            'dashboard' => [
                'label' => __('portal_vis_dashboard'),
                'description' => __('portal_vis_dashboard_help'),
                'icon' => 'dashboard',
                'tone' => 'orange',
                'routes' => [], // home page is always reachable; this toggles its widgets
            ],
            'projects' => [
                'label' => __('portal_vis_projects'),
                'description' => __('portal_vis_projects_help'),
                'icon' => 'projects',
                'tone' => 'teal',
                'routes' => ['portal.projects.*'],
            ],
            'quote_requests' => [
                'label' => __('portal_vis_quote_requests'),
                'description' => __('portal_vis_quote_requests_help'),
                'icon' => 'file-circle-plus',
                'tone' => 'amber',
                'routes' => ['portal.quote-requests.*'],
            ],
            'proposals' => [
                'label' => __('portal_vis_proposals'),
                'description' => __('portal_vis_proposals_help'),
                'icon' => 'file-contract',
                'tone' => 'sky',
                'routes' => ['portal.proposals.*'],
            ],
            'invoices' => [
                'label' => __('portal_vis_invoices'),
                'description' => __('portal_vis_invoices_help'),
                'icon' => 'file-invoice-dollar',
                'tone' => 'emerald',
                'routes' => ['portal.payments.*', 'portal.invoices.*'],
            ],
            'contracts' => [
                'label' => __('portal_vis_contracts'),
                'description' => __('portal_vis_contracts_help'),
                'icon' => 'file-signature',
                'tone' => 'violet',
                'routes' => ['portal.contracts.*'],
            ],
            'messages' => [
                'label' => __('portal_vis_messages'),
                'description' => __('portal_vis_messages_help'),
                'icon' => 'comments',
                'tone' => 'slate',
                'routes' => ['chat.*'],
            ],
            'support' => [
                'label' => __('portal_vis_support'),
                'description' => __('portal_vis_support_help'),
                'icon' => 'headset',
                'tone' => 'rose',
                'routes' => ['tickets.*'],
            ],
            'prospects' => [
                'label' => __('portal_vis_prospects'),
                'description' => __('portal_vis_prospects_help'),
                'icon' => 'address-book',
                'tone' => 'orange',
                'routes' => [
                    'portal.prospects.index',
                    'portal.prospects.show',
                    'portal.prospects.store',
                    'portal.prospects.update',
                    'portal.prospects.status',
                    'portal.prospects.comment',
                ],
            ],
            'appointments' => [
                'label' => __('portal_vis_appointments'),
                'description' => __('portal_vis_appointments_help'),
                'icon' => 'calendar-check',
                'tone' => 'amber',
                'routes' => [
                    'portal.prospects.appointments',
                    'portal.prospects.appointment',
                    'portal.calendar',
                    'portal.calendar.*',
                ],
            ],
            'campaigns' => [
                'label' => __('portal_vis_campaigns'),
                'description' => __('portal_vis_campaigns_help'),
                'icon' => 'bullhorn',
                'tone' => 'violet',
                'routes' => ['portal.campaigns.*'],
            ],
            'bookings' => [
                'label' => __('portal_vis_bookings'),
                'description' => __('portal_vis_bookings_help'),
                'icon' => 'calendar-days',
                'tone' => 'emerald',
                'routes' => ['portal.bookings.*'],
            ],
            'assistant' => [
                'label' => __('portal_vis_assistant'),
                'description' => __('portal_vis_assistant_help'),
                'icon' => 'wand-magic-sparkles',
                'tone' => 'orange',
                'routes' => ['portal.assistant.*'],
            ],
            'invite_colleague' => [
                'label' => __('portal_vis_invite_colleague'),
                'description' => __('portal_vis_invite_colleague_help'),
                'icon' => 'user-group',
                'tone' => 'blue',
                'routes' => ['portal.client-account-requests.*'],
            ],
            'vault' => [
                'label' => __('portal_vis_vault'),
                'description' => __('portal_vis_vault_help'),
                'icon' => 'vault',
                'tone' => 'blue',
                'routes' => ['portal.vault.*'],
            ],
            'activity' => [
                'label' => __('portal_vis_activity'),
                'description' => __('portal_vis_activity_help'),
                'icon' => 'activity',
                'tone' => 'slate',
                'routes' => ['notifications.*'],
            ],
        ];
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys($this->sections());
    }

    /** @return list<string> */
    public function companyHidden(?Company $company): array
    {
        if (! $company) {
            return [];
        }
        $settings = CompanySetting::query()->withoutGlobalScopes()->where('company_id', $company->id)->first();
        $raw = $settings?->portal_visibility;
        $hidden = is_array($raw) ? ($raw['hidden'] ?? []) : [];

        return array_values(array_intersect($this->keys(), array_map('strval', (array) $hidden)));
    }

    /** @param list<string> $hidden */
    public function saveCompanyHidden(Company $company, array $hidden): void
    {
        $hidden = array_values(array_intersect($this->keys(), $hidden));
        $settings = CompanySetting::query()->withoutGlobalScopes()->firstOrCreate(['company_id' => $company->id]);
        $settings->forceFill(['portal_visibility' => ['hidden' => $hidden]])->save();
        $this->cache = [];
    }

    /** @return array<string, string> key => show|hide */
    public function clientOverrides(?Client $client): array
    {
        $raw = $client?->portal_visibility;
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($this->keys() as $key) {
            $v = $raw[$key] ?? null;
            if ($v === 'show' || $v === 'hide') {
                $out[$key] = $v;
            }
        }

        return $out;
    }

    /** @param array<string, string> $overrides */
    public function saveClientOverrides(Client $client, array $overrides): void
    {
        $clean = [];
        foreach ($this->keys() as $key) {
            $v = $overrides[$key] ?? 'inherit';
            if ($v === 'show' || $v === 'hide') {
                $clean[$key] = $v;
            }
        }
        $client->forceFill(['portal_visibility' => $clean ?: null])->save();
        $this->cache = [];
    }

    /**
     * Effective visibility for a client record.
     *
     * @return array<string, bool>
     */
    public function mapForClient(?Client $client): array
    {
        $cacheKey = $client ? 'c'.$client->getKey() : 'none';
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        $company = $client?->company ?? ($client?->company_id ? Company::query()->find($client->company_id) : null);
        $hidden = $this->companyHidden($company);
        $overrides = $this->clientOverrides($client);
        $map = [];
        foreach ($this->keys() as $key) {
            $visible = ! in_array($key, $hidden, true);
            if (($overrides[$key] ?? null) === 'show') {
                $visible = true;
            } elseif (($overrides[$key] ?? null) === 'hide') {
                $visible = false;
            }
            $map[$key] = $visible;
        }

        return $this->cache[$cacheKey] = $map;
    }

    /** @return array<string, bool> */
    public function mapForUser(mixed $user): array
    {
        if (! $user instanceof User || ! $this->isPureClient($user)) {
            return array_fill_keys($this->keys(), true);
        }

        return $this->mapForClient($user->clientProfile);
    }

    public function visible(mixed $user, string $key): bool
    {
        return $this->mapForUser($user)[$key] ?? true;
    }

    public function isPureClient(mixed $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return $user->hasRole('client') && ! $user->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
    }

    /** Section key that owns this route name, or null. */
    public function sectionForRoute(?string $routeName): ?string
    {
        if (! $routeName) {
            return null;
        }
        foreach ($this->sections() as $key => $section) {
            foreach ($section['routes'] as $pattern) {
                if (Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
