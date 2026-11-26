@php
    $flowdeskTheme = app(\App\Services\CompanyThemeService::class)->themeFor(null);
    $here = request()->route()?->getName();
    $variant = $heroVariant ?? 'default';

    $heroImages = [
        'login' => asset('images/nova-robot.png'),
        'register' => asset('images/nova-robot.png'),
        'forgot' => asset('images/motif-clicflow.png'),
        'default' => asset('images/nova-robot.png'),
    ];
    $heroImage = $heroImages[$variant] ?? $heroImages['default'];

    $heroTitles = [
        'login' => __('brand_tagline'),
        'register' => __('Create workspace'),
        'forgot' => __('Forgot your password?'),
        'default' => __('brand_tagline'),
    ];
    $heroTitle = $heroTitles[$variant] ?? $heroTitles['default'];
    $heroLead = __('brand_pitch');
@endphp
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
    class="flow-theme-app mkt-light {{ $flowdeskTheme['html_class'] ?? '' }} scroll-smooth"
>
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

        <title>{{ config('app.name', 'ClicFlow') }}</title>

        @include('partials.favicon')
        @include('partials.theme-fonts', ['flowdeskTheme' => $flowdeskTheme])
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @include('partials.theme-variables', ['flowdeskTheme' => $flowdeskTheme])
    </head>
    <body class="min-h-screen bg-[#fff7ed] font-sans antialiased text-slate-900">
        <div class="flex min-h-screen flex-col lg:flex-row" dir="ltr">
            {{-- Mobile slim visual (top) --}}
            <div class="relative h-32 overflow-hidden sm:h-40 lg:hidden" aria-hidden="true">
                <img src="{{ asset('images/motif-clicflow.png') }}" alt="" class="absolute inset-0 h-full w-full object-cover object-center opacity-30" />
                <div class="absolute inset-0 bg-gradient-to-r from-[#1c0f08]/85 via-[#9a3412]/60 to-[#ea580c]/40"></div>
                <div class="relative z-10 flex h-full items-end px-5 pb-4">
                    <p class="font-[Space_Grotesk,ui-sans-serif,system-ui] text-lg font-bold leading-tight text-white">{{ $heroTitle }}</p>
                </div>
            </div>

            {{-- Visual (left) --}}
            <aside
                class="relative hidden overflow-hidden bg-[#1c0f08] lg:sticky lg:top-0 lg:block lg:h-screen lg:w-[54%] xl:w-[56%] lg:self-start"
                aria-hidden="true"
            >
                <img
                    src="{{ asset('images/motif-clicflow.png') }}"
                    alt=""
                    class="absolute inset-0 h-full w-full object-cover opacity-20 mix-blend-screen"
                />
                <div class="absolute inset-0 bg-gradient-to-br from-[#ea580c] via-[#9a3412] to-[#1c0f08] opacity-90"></div>
                <div class="pointer-events-none absolute -left-20 top-10 h-80 w-80 rounded-full bg-orange-300/25 blur-3xl"></div>
                <div class="pointer-events-none absolute bottom-16 right-10 h-64 w-64 rounded-full bg-amber-200/15 blur-3xl"></div>

                <div class="relative z-10 flex h-full flex-col px-10 py-12 xl:px-16">
                    <div class="flex-1"></div>
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.22em] text-orange-100/80">
                            {{ config('flowdesk.brand_name', config('app.name', 'ClicFlow')) }}
                        </p>
                        <h2 class="max-w-lg font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold leading-tight tracking-tight text-white xl:text-4xl">
                            {{ $heroTitle }}
                        </h2>
                        <p class="mt-4 max-w-lg text-base leading-relaxed text-orange-50/85">
                            {{ $heroLead }}
                        </p>
                    </div>
                </div>
            </aside>

            {{-- Form (right) --}}
            <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="relative flex w-full flex-col bg-white lg:min-h-screen lg:w-[46%] xl:w-[44%]">
                <header class="border-b border-orange-100/80">
                    <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-12">
                        <x-flow.brand-tile :href="route('login')" size="md" />
                        <nav class="flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 p-1 text-sm font-bold">
                            <a
                                href="{{ route('login') }}"
                                @class([
                                    'rounded-full px-4 py-1.5 transition',
                                    'bg-[#EA580C] text-white shadow-sm' => $here === 'login',
                                    'text-slate-900 hover:bg-white hover:text-[#C2410C]' => $here !== 'login',
                                ])
                            >{{ __('Log in') }}</a>
                            <a
                                href="{{ route('register') }}"
                                @class([
                                    'rounded-full px-4 py-1.5 transition',
                                    'bg-[#EA580C] text-white shadow-sm' => $here === 'register',
                                    'text-slate-900 hover:bg-white hover:text-[#C2410C]' => $here !== 'register',
                                ])
                            >{{ __('Register') }}</a>
                        </nav>
                    </div>
                </header>

                <main class="flex flex-1 items-start justify-center px-4 py-8 sm:px-6 lg:items-center lg:px-12 lg:py-12">
                    <div class="w-full max-w-md">
                        {{ $slot }}
                    </div>
                </main>

                <footer class="px-4 py-4 text-center text-xs text-slate-400 lg:px-12 lg:text-start">
                    © {{ date('Y') }} {{ config('app.name', 'ClicFlow') }}
                </footer>
            </div>

        </div>
    </body>
</html>
