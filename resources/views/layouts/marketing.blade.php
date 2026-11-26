<!DOCTYPE html>
@php($isRtl = app()->getLocale() === 'ar')
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="light">
        <script>
            document.documentElement.classList.add('mkt-light');
            document.documentElement.style.colorScheme = 'light';
            try { localStorage.setItem('clicflow-mkt-theme', 'light'); } catch (e) {}
        </script>
        <title>@yield('title', config('app.name'))</title>
        @hasSection('meta_description')
            <meta name="description" content="@yield('meta_description')">
        @endif
        @include('partials.favicon')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
        @php($viteEntries = flowdesk_marketing_vite_entries())
        @if ($viteEntries !== [])
            @vite($viteEntries)
        @endif
        @stack('head')
    </head>
    <body class="marketing-site antialiased text-slate-100">
        <div class="mkt-shell relative min-h-screen">
            <div class="mkt-ambient" aria-hidden="true"></div>
            <div class="mkt-dot-grid" aria-hidden="true"></div>

            @include('marketing.partials.nav')

            <main class="relative z-10">
                @yield('content')
            </main>

            @include('marketing.partials.footer')
        </div>

        @include('marketing.partials.chatbot')

        @stack('scripts')
    </body>
</html>
