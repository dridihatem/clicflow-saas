<div class="cf-shell-minimal flow-minimal-canvas flex min-h-screen flex-col bg-slate-50">
    @include('layouts.navigation-minimal', ['flowdeskTheme' => $flowdeskTheme])

    @isset($header)
        <header class="flow-chrome-header border-b backdrop-blur-md">
            <div class="max-w-12xl w-full py-4 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endisset

    @if (! empty($flowdeskBreadcrumbs ?? []))
        <div class="flow-chrome-breadcrumb border-b backdrop-blur-sm">
            <div class="max-w-12xl w-full px-4 py-2.5 sm:px-6 lg:px-8">
                <x-flow.breadcrumb-bar :items="$flowdeskBreadcrumbs" :back="$flowdeskBreadcrumbBack ?? null" />
            </div>
        </div>
    @endif

    <main class="flex-1 pb-8">
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
