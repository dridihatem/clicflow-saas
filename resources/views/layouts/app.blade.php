@php
    if (! isset($flowdeskTheme) || ! is_array($flowdeskTheme)) {
        $company = app()->bound('currentCompany') && app('currentCompany')
            ? app('currentCompany')
            : auth()->user()?->company;
        $themeService = app(\App\Services\CompanyThemeService::class);
        $flowdeskTheme = $themeService->themeFor($company, auth()->user());
        $flowdeskLayoutView = $flowdeskLayoutView ?? $themeService->layoutView($company, auth()->user());
    }
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
    class="flow-theme-app {{ $flowdeskTheme['html_class'] ?? '' }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        @auth
            @php $cfNovaVoiceOn = \App\Services\NovaVoiceNavigationService::companyVoiceEnabled(auth()->user()->company); @endphp
            <script>
                window.flowdeskNovaVoice = { enabled: @js($cfNovaVoiceOn), statusUrl: @js(\Illuminate\Support\Facades\Route::has('assistant.voice-status') ? route('assistant.voice-status') : null) };
                @if (! $cfNovaVoiceOn)
                    try { Object.keys(localStorage).filter(function (k) { return k.indexOf('flowdesk.nova.listeningPaused.') === 0; }).forEach(function (k) { localStorage.removeItem(k); }); } catch (e) {}
                @endif
            </script>
            @if (auth()->user()->hasAnyRole(['company_admin', 'team_member']) && ($flowdeskPlanGates['ai_credits'] ?? false))
                <script>
                    try { (function () { var k = 'cf.aiChat.' + @js((string) auth()->id()); var h = document.documentElement; if (localStorage.getItem(k + '.open') === '1') { h.classList.add('cf-ai-chat-open'); } var w = parseInt(localStorage.getItem(k + '.w') || '0', 10); if (w >= 340 && w <= 640) { h.style.setProperty('--cf-ai-chat-w', w + 'px'); } })(); } catch (e) {}
                </script>
            @endif
        @endauth
        <meta name="color-scheme" content="{{ ! empty($flowdeskTheme['use_system_dark']) ? 'light dark' : (($flowdeskTheme['html_class'] ?? '') === 'dark' || ($flowdeskTheme['dark_mode'] ?? '') === 'dark' ? 'dark' : 'light') }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        @include('partials.favicon')
        @include('partials.theme-fonts', ['flowdeskTheme' => $flowdeskTheme])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.theme-variables', ['flowdeskTheme' => $flowdeskTheme])
        @stack('styles')
        @include('partials.block-devtools')
    </head>
    <body class="font-sans antialiased flow-app-body">
        <div class="pointer-events-none fixed inset-0 z-0 opacity-70" aria-hidden="true" style="background-image: radial-gradient(rgba(91,100,140,0.1) 1px, transparent 1px); background-size: 22px 22px; mask-image: radial-gradient(ellipse 80% 50% at 50% 0%, #000 20%, transparent 70%);"></div>
        {{-- Gate sits directly under <body>: its "hide everything but me" rule must not hide itself (blank page on phones). --}}
        @include('partials.mobile-desktop-gate')
        <div class="relative z-10">
        @include($flowdeskLayoutView ?? 'themes.default.layouts.sidebar', ['flowdeskTheme' => $flowdeskTheme])
        @auth
            <x-ai.chat-panel :floating="($flowdeskLayoutView ?? 'themes.default.layouts.sidebar') !== 'themes.default.layouts.sidebar'" />
        @endauth
        @include('partials.notify-labels')
        @include('components.flow.product-tour-modal')
        @stack('modals')
        @stack('scripts')
        </div>
    </body>
</html>
