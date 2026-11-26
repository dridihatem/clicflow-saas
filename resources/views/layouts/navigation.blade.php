@php
    $flowdeskTheme = $flowdeskTheme ?? [];
    $flowdeskUser = Auth::user();
    $navVis = app(\App\Services\PortalVisibilityService::class)->mapForUser($flowdeskUser);
    $isPureClient = $flowdeskUser && $flowdeskUser->hasRole('client') && ! $flowdeskUser->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
    $isPureProvider = $flowdeskUser && $flowdeskUser->hasRole('business_provider') && ! $flowdeskUser->hasAnyRole(['company_admin', 'team_member']);
    $flowdeskHome = $isPureClient
        ? route('portal.dashboard')
        : ($isPureProvider
            ? route('provider.dashboard')
            : route('dashboard'));
@endphp
<nav x-data="{ open: false }" class="flow-glass-nav sticky top-0 z-40">
    <div class="max-w-12xl w-full px-4 sm:px-6 lg:px-8">
        <div class="flex h-16 items-center justify-between gap-2">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <div class="flex min-w-0 shrink items-center">
                    <x-flow.brand-tile
                        :href="$flowdeskHome"
                        size="sm"
                        :src="! empty($flowdeskTheme['logo_url']) ? $flowdeskTheme['logo_url'] : null"
                    />
                </div>

                <div class="hidden min-w-0 flex-1 items-center gap-2 lg:flex">
                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-1 gap-y-1 xl:gap-x-2">
                        @if ($isPureClient)
                            @php
                                $portalNavSections = app(\App\Services\PortalNavigationService::class)
                                    ->sectionsFor($flowdeskUser, $flowdeskPlanGates ?? []);
                            @endphp
                            @foreach ($portalNavSections as $section)
                                @if (($section['type'] ?? '') === 'heading')
                                    {{-- section labels are for the sidebar; skip in the compact top bar --}}
                                @elseif (($section['type'] ?? '') === 'group')
                                    <x-flow.topbar-nav-dropdown
                                        :label="$section['label']"
                                        :active="$section['active'] ?? false"
                                        :items="$section['children'] ?? []"
                                    />
                                @else
                                    <x-nav-link :href="$section['url']" :active="$section['active'] ?? false">
                                        {{ $section['label'] }}
                                    </x-nav-link>
                                @endif
                            @endforeach
                        @elseif ($isPureProvider)
                            @can('provider.view_dashboard')
                                <x-nav-link :href="route('provider.dashboard')" :active="request()->routeIs('provider.dashboard')">
                                    {{ __('Overview') }}
                                </x-nav-link>
                            @endcan
                            @can('provider.manage_projects')
                                <x-nav-link :href="route('provider.projects.index')" :active="request()->routeIs('provider.projects.*')">
                                    {{ __('My projects') }}
                                </x-nav-link>
                            @endcan
                            @can('provider.view_payments')
                                <x-nav-link :href="route('provider.remittance-requests.index')" :active="request()->routeIs('provider.remittance-requests.*')">
                                    {{ __('provider_payment_requests') }}
                                </x-nav-link>
                            @endcan
                        @else
                            @php
                                $flowdeskTopbarNavSections = app(\App\Services\WorkspaceNavigationService::class)
                                    ->sectionsFor($flowdeskUser, $flowdeskPlanGates ?? []);
                            @endphp
                            @foreach ($flowdeskTopbarNavSections as $navSection)
                                @if ($navSection['flat'] ?? false)
                                    @foreach ($navSection['items'] as $navItem)
                                        <x-nav-link :href="$navItem['url']" :active="$navItem['active'] ?? false">
                                            {{ $navItem['label'] }}
                                        </x-nav-link>
                                    @endforeach
                                @else
                                    <x-flow.topbar-nav-dropdown
                                        :label="$navSection['label']"
                                        :active="collect($navSection['items'])->contains(fn ($i) => ! empty($i['active']))"
                                        :items="$navSection['items']"
                                    />
                                @endif
                            @endforeach
                            @can('workspace.manage_settings')
                                <x-dropdown align="left" width="56" wrapperClass="relative z-[120] inline-flex" floatingClass="z-[130]" contentClasses="py-1 overflow-hidden bg-white/95 backdrop-blur-md dark:bg-slate-800/95 dark:ring-1 dark:ring-slate-600/80">
                                    <x-slot name="trigger">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium text-slate-600 transition duration-150 ease-in-out hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800/80 dark:hover:text-white"
                                            aria-haspopup="true"
                                            aria-expanded="false"
                                        >
                                            {{ __('Product website') }}
                                            <i class="fa-solid fa-chevron-down text-[10px] text-slate-400" aria-hidden="true"></i>
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <div class="border-b border-slate-100 px-3 py-2 dark:border-slate-700/80">
                                            <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('SEO & public pages') }}</p>
                                        </div>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Home') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/features')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Features') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/pricing')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Pricing') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/about')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('About us') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/contact')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Contact') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/terms')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Terms of service') }}
                                        </x-dropdown-link>
                                        <x-dropdown-link :href="flowdesk_public_site_url('/privacy')" class="dark:text-slate-200 dark:hover:bg-slate-700/80">
                                            {{ __('Privacy policy') }}
                                        </x-dropdown-link>
                                    </x-slot>
                                </x-dropdown>
                            @endcan
                        @endif
                        @if ($isPureClient || $isPureProvider)
                            @if ($navVis['activity'])
                            <x-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                                {{ __('Activity') }}
                            </x-nav-link>
                            @endif
                            @if ($navVis['messages'])
                            <x-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.*')">
                                {{ __('Messages') }}
                            </x-nav-link>
                            @endif
                            @if ($navVis['support'])
                            <x-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')">
                                {{ __('Tickets') }}
                            </x-nav-link>
                            @endif
                        @endif
                    </div>
                    @if ($flowdeskUser?->hasAnyRole(['company_admin', 'team_member']))
                        <div class="flex shrink-0 items-center border-s border-slate-200/80 ps-3 dark:border-slate-600/80">
                            <x-flow.nav-ai-credits />
                        </div>
                    @endif
                </div>
            </div>

            <div class="hidden items-center gap-2 sm:ms-2 sm:flex md:gap-3">
                @if ($navVis['activity'])
                <a
                    href="{{ route('notifications.index') }}"
                    class="flow-notify-bell !h-9 !w-9 shrink-0"
                    title="{{ __('Activity') }}"
                    aria-label="{{ __('Activity') }}"
                >
                    <i class="fa-regular fa-bell text-base" aria-hidden="true"></i>
                </a>
                @endif
                <x-flow.nav-ai-credits class="hidden md:inline-flex lg:hidden" />
                <form method="POST" action="{{ route('locale.update') }}" class="hidden items-center gap-2 text-sm md:flex">
                    @csrf
                    <label for="nav_locale" class="sr-only">{{ __('Language') }}</label>
                    <select
                        id="nav_locale"
                        name="locale"
                        class="max-w-[7.5rem] rounded-lg border-slate-200 bg-white/90 text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        onchange="this.form.submit()"
                    >
                        @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                            <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="hidden md:block">
                    <x-flow.topbar-profile-menu variant="nav" />
                </div>
            </div>

            <div class="flex shrink-0 items-center lg:hidden">
                <button
                    @click="open = ! open"
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :aria-expanded="open.toString()"
                    aria-controls="flowdesk-mobile-nav"
                >
                    <span class="sr-only">{{ __('Open menu') }}</span>
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div
        id="flowdesk-mobile-nav"
        :class="{'block': open, 'hidden': ! open}"
        class="hidden max-h-[min(70dvh,32rem)] overflow-y-auto overscroll-contain border-t border-slate-200/70 dark:border-slate-700/60 lg:hidden"
    >
        <div class="space-y-1 px-3 pb-3 pt-2 sm:px-4">
            @if ($isPureClient)
                @php
                    $portalNavSectionsMobile = app(\App\Services\PortalNavigationService::class)
                        ->sectionsFor($flowdeskUser, $flowdeskPlanGates ?? []);
                @endphp
                @foreach ($portalNavSectionsMobile as $section)
                    @if (($section['type'] ?? '') === 'heading')
                        <p class="px-4 pt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $section['label'] }}</p>
                    @elseif (($section['type'] ?? '') === 'group')
                        <p class="px-4 pt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $section['label'] }}</p>
                        @foreach ($section['children'] ?? [] as $child)
                            <x-responsive-nav-link :href="$child['url']" :active="$child['active'] ?? false">
                                {{ $child['label'] }}
                            </x-responsive-nav-link>
                        @endforeach
                    @else
                        <x-responsive-nav-link :href="$section['url']" :active="$section['active'] ?? false">
                            {{ $section['label'] }}
                        </x-responsive-nav-link>
                    @endif
                @endforeach
            @elseif ($isPureProvider)
                @can('provider.view_dashboard')
                    <x-responsive-nav-link :href="route('provider.dashboard')" :active="request()->routeIs('provider.dashboard')">
                        {{ __('Overview') }}
                    </x-responsive-nav-link>
                @endcan
                @can('provider.manage_projects')
                    <x-responsive-nav-link :href="route('provider.projects.index')" :active="request()->routeIs('provider.projects.*')">
                        {{ __('My projects') }}
                    </x-responsive-nav-link>
                @endcan
                @can('provider.view_payments')
                    <x-responsive-nav-link :href="route('provider.remittance-requests.index')" :active="request()->routeIs('provider.remittance-requests.*')">
                        {{ __('provider_payment_requests') }}
                    </x-responsive-nav-link>
                @endcan
                <p class="px-3 pt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 sm:px-4">{{ __('Nav section support') }}</p>
                <x-responsive-nav-link :href="route('chat.index')" :active="request()->routeIs('chat.*')">{{ __('Messages') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('tickets.index')" :active="request()->routeIs('tickets.*')">{{ __('Tickets') }}</x-responsive-nav-link>
                <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">{{ __('Activity') }}</x-responsive-nav-link>
            @else
                @php
                    $flowdeskMobileNavSections = app(\App\Services\WorkspaceNavigationService::class)
                        ->sectionsFor($flowdeskUser, $flowdeskPlanGates ?? []);
                @endphp
                @foreach ($flowdeskMobileNavSections as $navSection)
                    @if (! ($navSection['flat'] ?? false))
                        <p class="px-3 pt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 sm:px-4">
                            {{ $navSection['label'] }}
                        </p>
                    @endif
                    @foreach ($navSection['items'] as $navItem)
                        <x-responsive-nav-link :href="$navItem['url']" :active="$navItem['active'] ?? false">
                            {{ $navItem['label'] }}
                        </x-responsive-nav-link>
                    @endforeach
                @endforeach

                @if ($flowdeskUser?->can('workspace.manage_settings'))
                    @if (($flowdeskPlanGates['modules'] ?? true) && ! empty($flowdeskInstalledModules))
                        <p class="px-3 pt-3 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400 sm:px-4">
                            {{ __('Nav section modules') }}
                        </p>
                        @foreach ($flowdeskInstalledModules as $mod)
                            <x-responsive-nav-link
                                :href="$mod['route']"
                                :active="request()->routeIs('modules.*') && request()->route('slug') === $mod['slug']"
                            >
                                {{ $mod['name'] }}
                            </x-responsive-nav-link>
                        @endforeach
                    @endif
                    <x-responsive-nav-link :href="route('settings.workspace')" :active="request()->routeIs('settings.*')">
                        {{ __('Company settings') }}
                    </x-responsive-nav-link>
                @elseif ($flowdeskUser?->hasRole('team_member'))
                    <x-responsive-nav-link :href="route('profile.edit')" :active="request()->routeIs('profile.*')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>
                @endif
            @endif
        </div>

        <div class="border-t border-slate-200/70 px-4 py-4 dark:border-slate-700/60">
            <div class="font-medium text-slate-800 dark:text-slate-100">{{ Auth::user()->name }}</div>
            <div class="text-sm text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</div>
            <div class="mt-3 md:hidden">
                <form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2">
                    @csrf
                    <label for="nav_locale_mobile" class="sr-only">{{ __('Language') }}</label>
                    <select
                        id="nav_locale_mobile"
                        name="locale"
                        class="w-full rounded-lg border-slate-200 bg-white/90 text-sm text-slate-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                        onchange="this.form.submit()"
                    >
                        @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                            <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="mt-3">
                <x-flow.nav-ai-credits compact />
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                @if ($navVis['activity'])
                <x-responsive-nav-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" class="sm:hidden">
                    {{ __('Activity') }}
                </x-responsive-nav-link>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
