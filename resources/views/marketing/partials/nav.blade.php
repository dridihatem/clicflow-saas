@php
    $here = request()->route()?->getName();
    $onHome = $here === 'home';
    $links = [
        ['href' => $onHome ? '#how-it-works' : route('home').'#how-it-works', 'label' => __('See how it works')],
        ['href' => $onHome ? '#features' : route('home').'#features', 'label' => __('Features')],
        ['href' => $onHome ? '#get-started' : route('home').'#get-started', 'label' => __('Get started')],
    ];
@endphp
<header
    class="mkt-nav sticky top-0 z-40"
    data-mkt-nav
    x-data="{ open: false }"
    x-init="$watch('open', (value) => { document.body.classList.toggle('mkt-nav-locked', value); document.body.style.overflow = value ? 'hidden' : ''; })"
    @keydown.escape.window="open = false"
>
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 sm:py-4 lg:px-8">
        <a href="{{ route('home') }}" class="mkt-brand relative z-10 shrink-0 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ea580c]">
            <x-application-logo :tagline="false" :themed="true" class="!h-11 !max-w-[168px] sm:!h-14 sm:!max-w-[240px]" />
        </a>

        <nav class="hidden items-center justify-end gap-1 text-[14.5px] font-medium lg:flex" aria-label="{{ __('Primary') }}">
            @foreach ($links as $link)
                <a
                    href="{{ $link['href'] }}"
                    class="rounded-full px-3 py-2 text-slate-400 transition hover:text-white"
                >{{ $link['label'] }}</a>
            @endforeach

            <span class="mx-1 hidden h-4 w-px bg-[rgba(124,92,252,0.35)] xl:inline-block" aria-hidden="true"></span>

            <div class="ms-1 inline-flex items-center gap-2">
                <button
                    type="button"
                    class="mkt-theme-toggle"
                    data-mkt-theme-toggle
                    data-label-light="{{ __('Light mode') }}"
                    data-label-dark="{{ __('Dark mode') }}"
                    aria-pressed="false"
                    aria-label="{{ __('Light mode') }}"
                    title="{{ __('Light mode') }}"
                >
                    <i class="fa-solid fa-sun mkt-theme-icon-sun text-sm" aria-hidden="true"></i>
                    <i class="fa-solid fa-moon mkt-theme-icon-moon text-sm" aria-hidden="true"></i>
                </button>
                <form method="POST" action="{{ route('locale.update') }}" class="inline-flex items-center">
                    @csrf
                    <label for="marketing_locale" class="sr-only">{{ __('Language') }}</label>
                    <select
                        id="marketing_locale"
                        name="locale"
                        class="mkt-locale-select h-9 rounded-md border-0 bg-transparent pe-1 ps-1 text-xs font-medium text-slate-300 focus:outline-none focus:ring-0"
                        onchange="this.form.submit()"
                    >
                        @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                            <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ __('locale.name.'.$loc) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            @if (Route::has('login'))
                <a href="{{ route('login') }}" class="mkt-btn mkt-btn-ghost !px-4 !py-2">{{ __('Log in') }}</a>
            @endif
            @if (Route::has('register'))
                <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary !px-5 !py-2.5">{{ __('Get started') }}</a>
            @endif
        </nav>

        <div class="flex items-center gap-2 lg:hidden">
            <button
                type="button"
                class="mkt-theme-toggle"
                data-mkt-theme-toggle
                data-label-light="{{ __('Light mode') }}"
                data-label-dark="{{ __('Dark mode') }}"
                aria-pressed="false"
                aria-label="{{ __('Light mode') }}"
                title="{{ __('Light mode') }}"
            >
                <i class="fa-solid fa-sun mkt-theme-icon-sun text-sm" aria-hidden="true"></i>
                <i class="fa-solid fa-moon mkt-theme-icon-moon text-sm" aria-hidden="true"></i>
            </button>
            <button
                type="button"
                class="mkt-nav-toggle inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[rgba(124,92,252,0.28)] bg-[#12182B] text-slate-200 hover:text-white"
                @click="open = !open"
                :aria-expanded="open.toString()"
                aria-controls="mkt-mobile-nav"
                aria-label="{{ __('Open menu') }}"
            >
                <span class="sr-only" x-text="open ? @js(__('Close menu')) : @js(__('Open menu'))">{{ __('Menu') }}</span>
                <span class="relative block h-3.5 w-4" aria-hidden="true">
                    <span class="absolute inset-x-0 top-0 h-0.5 rounded-full bg-current transition" :class="open ? 'top-1.5 rotate-45' : ''"></span>
                    <span class="absolute inset-x-0 top-1.5 h-0.5 rounded-full bg-current transition" :class="open ? 'opacity-0' : ''"></span>
                    <span class="absolute inset-x-0 top-3 h-0.5 rounded-full bg-current transition" :class="open ? 'top-1.5 -rotate-45' : ''"></span>
                </span>
            </button>
        </div>
    </div>

    <div
        id="mkt-mobile-nav"
        class="lg:hidden"
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
    >
        <nav class="mkt-nav-mobile mx-auto max-w-7xl px-4 pb-5 pt-1 sm:px-6" aria-label="{{ __('Menu') }}">
            <div class="flex flex-col gap-1">
                @foreach ($links as $link)
                    <a
                        href="{{ $link['href'] }}"
                        @click="open = false"
                        class="rounded-xl px-4 py-3 text-base font-medium text-slate-300 transition hover:bg-[#12182B] hover:text-white"
                    >{{ $link['label'] }}</a>
                @endforeach
            </div>

            <div class="mt-4 border-t border-[rgba(124,92,252,0.22)] pt-4">
                <form method="POST" action="{{ route('locale.update') }}" class="flex items-center justify-between gap-3 px-1 py-1">
                    @csrf
                    <label for="marketing_locale_mobile" class="text-sm text-slate-400">{{ __('Language') }}</label>
                    <select
                        id="marketing_locale_mobile"
                        name="locale"
                        class="mkt-locale-select h-9 rounded-md border-0 bg-transparent text-sm font-medium text-slate-200 focus:outline-none focus:ring-0"
                        onchange="this.form.submit()"
                    >
                        @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                            <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ __('locale.name.'.$loc) }}</option>
                        @endforeach
                    </select>
                </form>

                <div class="mt-3 flex flex-col gap-2">
                    @if (Route::has('login'))
                        <a href="{{ route('login') }}" class="mkt-btn mkt-btn-ghost w-full justify-center !px-4 !py-2.5">{{ __('Log in') }}</a>
                    @endif
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="mkt-btn mkt-btn-primary w-full justify-center !px-5 !py-2.5">{{ __('Get started') }}</a>
                    @endif
                </div>
            </div>
        </nav>
    </div>
</header>
