@php
    $flowdeskTheme = $flowdeskTheme ?? [];
    $u = Auth::user();
    $isPureClient = $u && $u->hasRole('client') && ! $u->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
    $isPureProvider = $u && $u->hasRole('business_provider') && ! $u->hasAnyRole(['company_admin', 'team_member']);
    $sidebarHome = $isPureClient
        ? route('portal.dashboard')
        : ($isPureProvider ? route('provider.dashboard') : route('dashboard'));

    $navSectionActive = static fn (array $items): bool => collect($items)->contains(fn (array $item): bool => (bool) ($item['active'] ?? false));
@endphp
<aside
    class="flow-sidebar-aside hidden min-h-screen w-64 shrink-0 backdrop-blur-md lg:flex lg:flex-col"
>
    <div class="flow-sidebar-aside-header flex h-16 shrink-0 items-center gap-2 border-b px-2">
        <x-flow.brand-tile
            :href="$sidebarHome"
            size="md"
            :src="! empty($flowdeskTheme['logo_url']) ? $flowdeskTheme['logo_url'] : null"
            class="min-w-0 max-w-full"
        />
        @if ($u && $u->hasAnyRole(['company_admin', 'team_member']) && ! $isPureClient && ! $isPureProvider)
            <div class="ms-auto shrink-0 cf-nav-label">
                <x-flow.nav-ai-credits compact />
            </div>
        @endif
        <button type="button" class="cf-collapse-btn ms-auto inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 hover:bg-orange-50 hover:text-orange-700 dark:hover:bg-orange-950/40" @click="toggleCollapsed()" :aria-expanded="!collapsed" aria-label="{{ __('portal_sidebar_collapse') }}">
            <x-cf-icon name="chevron-left" size="sm" class="transition-transform duration-200 rtl:rotate-180" x-bind:class="{ 'rotate-180 rtl:rotate-0': collapsed }" />
        </button>
    </div>
    <nav class="flex-1 space-y-1 overflow-y-auto overflow-x-visible px-2 py-4">
        @if ($isPureClient)
            @php
                $portalNavSections = app(\App\Services\PortalNavigationService::class)
                    ->sectionsFor($u, $flowdeskPlanGates ?? []);
            @endphp

            @foreach ($portalNavSections as $section)
                @if (($section['type'] ?? '') === 'heading')
                    <p class="px-3 pb-1 pt-4 text-[10px] font-semibold uppercase tracking-wide text-slate-400 first:pt-1 dark:text-slate-500">{{ $section['label'] }}</p>
                @elseif (($section['type'] ?? '') === 'group')
                    <x-flow.sidebar-nav-flyout
                        :label="$section['label']"
                        :icon="$section['icon'] ?? 'navigation'"
                        :items="$section['children'] ?? []"
                        :active="$section['active'] ?? false"
                    />
                @else
                    <x-flow.sidebar-nav-link
                        :href="$section['url']"
                        :label="$section['label']"
                        :icon="$section['icon'] ?? 'navigation'"
                        :active="$section['active'] ?? false"
                    />
                @endif
            @endforeach
        @elseif ($isPureProvider)
            @php
                $providerNavItems = [];
                if ($u->can('provider.view_dashboard')) {
                    $providerNavItems[] = [
                        'url' => route('provider.dashboard'),
                        'label' => __('Overview'),
                        'icon' => 'dashboard',
                        'active' => request()->routeIs('provider.dashboard'),
                    ];
                }
                if ($u->can('provider.manage_projects')) {
                    $providerNavItems[] = [
                        'url' => route('provider.projects.index'),
                        'label' => __('My projects'),
                        'icon' => 'projects',
                        'active' => request()->routeIs('provider.projects.*'),
                    ];
                }
                if ($u->can('provider.view_payments')) {
                    $providerNavItems[] = [
                        'url' => route('provider.remittance-requests.index'),
                        'label' => __('provider_payment_requests'),
                        'icon' => 'billing',
                        'active' => request()->routeIs('provider.remittance-requests.*'),
                    ];
                }

                $providerSupportItems = [
                    [
                        'url' => route('chat.index'),
                        'label' => __('Messages'),
                        'icon' => 'messages',
                        'active' => request()->routeIs('chat.*'),
                    ],
                    [
                        'url' => route('tickets.index'),
                        'label' => __('Tickets'),
                        'icon' => 'tickets',
                        'active' => request()->routeIs('tickets.*'),
                    ],
                ];
            @endphp

            <x-flow.sidebar-nav-flyout
                :label="__('Provider portal')"
                icon="provider"
                :items="$providerNavItems"
                :active="$navSectionActive($providerNavItems)"
            />
            <x-flow.sidebar-nav-flyout
                :label="__('Nav section support')"
                icon="messages"
                :items="$providerSupportItems"
                :active="$navSectionActive($providerSupportItems)"
            />
            <x-flow.sidebar-nav-link
                :href="route('notifications.index')"
                :label="__('Activity')"
                icon="activity"
                :active="request()->routeIs('notifications.*')"
            />
            <x-flow.sidebar-nav-link
                :href="route('profile.edit')"
                :label="__('Account')"
                icon="profile"
                :active="request()->routeIs('profile.*')"
            />
        @else
            @php
                $flowdeskNavSections = app(\App\Services\WorkspaceNavigationService::class)->sectionsFor($u, $flowdeskPlanGates ?? []);
            @endphp
            @foreach ($flowdeskNavSections as $navSection)
                @if ($navSection['flat'] ?? false)
                    @foreach ($navSection['items'] as $navItem)
                        <x-flow.sidebar-nav-link
                            :href="$navItem['url']"
                            :label="$navItem['label']"
                            :icon="$navItem['icon']"
                            :active="$navItem['active'] ?? false"
                        />
                    @endforeach
                @else
                    <x-flow.sidebar-nav-flyout
                        :label="$navSection['label']"
                        :icon="$navSection['icon'] ?? 'navigation'"
                        :items="$navSection['items']"
                        :active="$navSectionActive($navSection['items'])"
                    />
                @endif
            @endforeach

            @if (Auth::user()->can('workspace.manage_settings'))
                @if (($flowdeskPlanGates['modules'] ?? true) && ! empty($flowdeskInstalledModules))
                    @php
                        $moduleNavItems = collect($flowdeskInstalledModules)->map(fn (array $mod): array => [
                            'url' => $mod['route'],
                            'label' => $mod['name'],
                            'icon' => $mod['icon'],
                            'active' => request()->routeIs('modules.*') && request()->route('slug') === $mod['slug'],
                        ])->values()->all();
                    @endphp
                    <x-flow.sidebar-nav-flyout
                        :label="__('Nav section modules')"
                        icon="modules"
                        :items="$moduleNavItems"
                        :active="$navSectionActive($moduleNavItems)"
                    />
                @endif
                <x-flow.sidebar-nav-link
                    :href="route('settings.workspace')"
                    :label="__('Company settings')"
                    icon="settings"
                    :active="request()->routeIs('settings.*')"
                />
            @elseif (Auth::user()->hasRole('team_member'))
                <x-flow.sidebar-nav-link
                    :href="route('profile.edit')"
                    :label="__('Profile')"
                    icon="settings"
                    :active="request()->routeIs('profile.*')"
                />
            @endif
        @endif
    </nav>
</aside>
