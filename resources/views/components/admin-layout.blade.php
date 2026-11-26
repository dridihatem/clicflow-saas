@props(['title' => null])
@php
    $flowdeskTheme = app(\App\Services\CompanyThemeService::class)->themeFor(null, auth('admin')->user());
    $preset = (array) (config('flowdesk.theme_presets.admin') ?? []);
    $flowdeskTheme = array_merge($flowdeskTheme, array_filter([
        'primary_color' => $preset['primary_color'] ?? null,
        'secondary_color' => $preset['secondary_color'] ?? null,
    ], fn ($v) => $v !== null && $v !== ''));
    $isDark = ($flowdeskTheme['html_class'] ?? '') === 'dark'
        || ($flowdeskTheme['dark_mode'] ?? 'light') === 'dark';
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
        <meta name="color-scheme" content="{{ $isDark ? 'dark' : 'light' }}">
        <title>{{ $title ? $title.' — ' : '' }}{{ __('Platform admin') }} — {{ config('app.name') }}</title>
        @include('partials.favicon')
        @include('partials.theme-fonts', ['flowdeskTheme' => $flowdeskTheme])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @include('partials.theme-variables', ['flowdeskTheme' => $flowdeskTheme])
    </head>
    <body class="font-sans antialiased min-h-screen flow-app-body bg-[#fffaf6]" style="font-family: var(--flow-font-sans, Inter, ui-sans-serif, system-ui, sans-serif);">
        @include('partials.mobile-desktop-gate')
        @include('layouts.admin-sidebar', ['flowdeskTheme' => $flowdeskTheme, 'slot' => $slot])
        <x-admin-company-contact-widget />
        @include('partials.notify-labels')
        @stack('scripts')
    </body>
</html>
