<div class="min-h-screen flex flex-col lg:flex-row" x-data="clicflowShell()" :class="{ 'cf-shell-collapsed': collapsed }">
    <div class="lg:hidden">
        @include('layouts.navigation', ['flowdeskTheme' => $flowdeskTheme])
    </div>

    @include('layouts.navigation-sidebar', ['flowdeskTheme' => $flowdeskTheme])

    <div class="flow-workspace-main flex min-w-0 flex-1 flex-col">
        <div class="flow-chrome-topbar relative z-[40] flex items-center justify-end gap-2 overflow-visible border-b px-3 py-2 sm:px-6 lg:px-8">
            @auth
                <div class="min-w-0 flex-1 lg:flex-none">
                    <x-ai.nova-voice-nav />
                </div>
                <x-ai.chat-launcher />
            @endauth
            @php
                $topPortalVisible = app(\App\Services\PortalVisibilityService::class)->mapForUser(auth()->user());
            @endphp
            @if (! empty($flowdeskCalendarNav) && ($topPortalVisible['appointments'] ?? true))
                <div class="shrink-0">
                    <x-flow.calendar-nav-widget :preview="$flowdeskCalendarNav" />
                </div>
            @endif
            @php
                $uTop = auth()->user();
                $topIsClient = $uTop && $uTop->hasRole('client') && ! $uTop->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
                $topIsProvider = $uTop && $uTop->hasRole('business_provider') && ! $uTop->hasAnyRole(['company_admin', 'team_member']);
                $topSearch = $topIsClient
                    ? (($topPortalVisible['prospects'] ?? true) ? route('portal.prospects.index') : null)
                    : ($topIsProvider
                        ? (Route::has('provider.projects.index') ? route('provider.projects.index') : route('provider.dashboard'))
                        : (Route::has('clients.index') ? route('clients.index') : route('dashboard')));
            @endphp
            <div class="hidden items-center gap-2 lg:flex">
            @if ($topSearch)
            <form method="GET" action="{{ $topSearch }}" class="cf-topbar-search">
                <label class="sr-only" for="cf-top-search">{{ __('Search') }}</label>
                <div class="relative">
                    <x-cf-icon name="search" size="sm" class="pointer-events-none absolute start-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input id="cf-top-search" type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('Search') }}" class="w-52 rounded-xl border border-slate-200 bg-white py-1.5 ps-8 pe-3 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" />
                </div>
            </form>
            @endif
            @if ($topPortalVisible['activity'] ?? true)
            <a
                href="{{ route('notifications.index') }}"
                class="flow-notify-bell"
                title="{{ __('Activity') }}"
                aria-label="{{ __('Activity') }}"
            >
                <x-cf-icon name="bell" class="cf-topbar-icon" />
            </a>
            @endif
            <form method="POST" action="{{ route('locale.update') }}" class="flex items-center gap-2">
                @csrf
                <label for="topbar_locale" class="sr-only">{{ __('Language') }}</label>
                <span class="hidden text-flow-text-muted xl:inline" aria-hidden="true">
                    <x-cf-icon name="globe" class="cf-topbar-icon" />
                </span>
                <select
                    id="topbar_locale"
                    name="locale"
                    class="flow-chrome-select rounded-lg border py-1.5 ps-2 pe-8 text-sm shadow-sm focus:outline-none focus:ring-2"
                    onchange="this.form.submit()"
                >
                    @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                        <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                    @endforeach
                </select>
            </form>
            <x-flow.topbar-profile-menu variant="topbar" />
            </div>
        </div>
        @isset($header)
            <header class="flow-chrome-header relative z-10 border-b backdrop-blur-md">
                <div class="max-w-12xl w-full py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        @if (! empty($flowdeskBreadcrumbs ?? []))
            <div class="flow-chrome-breadcrumb relative z-10 border-b backdrop-blur-sm">
                <div class="max-w-12xl w-full px-4 py-2.5 sm:px-6 lg:px-8">
                    <x-flow.breadcrumb-bar :items="$flowdeskBreadcrumbs" :back="$flowdeskBreadcrumbBack ?? null" />
                </div>
            </div>
        @endif

        <main class="flex-1 pb-8">
            @if (session('tenant_switch_notice'))
                <div class="max-w-12xl w-full px-4 pt-6 sm:px-6 lg:px-8">
                    <div class="rounded-xl border border-amber-200/90 bg-amber-50/95 px-4 py-3 text-sm text-amber-950 shadow-sm dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">
                        {{ session('tenant_switch_notice') }}
                    </div>
                </div>
            @endif
            @if (session('error'))
                <div class="max-w-12xl w-full px-4 pt-6 sm:px-6 lg:px-8">
                    <div class="rounded-xl border border-rose-200/90 bg-rose-50/95 px-4 py-3 text-sm text-rose-950 shadow-sm dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-100">
                        {{ session('error') }}
                    </div>
                </div>
            @endif
            <x-ai.draft-banner />
            <x-flow.trial-banner />
            {{ $slot ?? '' }}
            @hasSection('content')
                @yield('content')
            @endif
        </main>
        @include('partials.app-footer')
        @auth
            <x-flow.chat-messenger-widget />
        @endauth
    </div>
</div>
