<x-guest-layout hero-variant="login">
    @php
        $oauthProviders = [
            'google' => [
                'label' => 'Google',
                'url' => route('oauth.redirect', ['provider' => 'google']),
            ],
            'github' => [
                'label' => 'GitHub',
                'url' => route('oauth.redirect', ['provider' => 'github']),
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'url' => route('oauth.redirect', ['provider' => 'linkedin']),
            ],
        ];
    @endphp

    <div
        class="relative"
        x-data="{
            redirectOpen: false,
            provider: null,
            label: '',
            url: '',
            timer: null,
            openRedirect(provider, label, url) {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
                this.provider = provider;
                this.label = label;
                this.url = url;
                this.redirectOpen = true;
                this.timer = setTimeout(() => {
                    window.location.href = this.url;
                }, 900);
            },
            closeRedirect() {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
                this.redirectOpen = false;
                this.provider = null;
                this.label = '';
                this.url = '';
            },
            goNow() {
                if (this.timer) {
                    clearTimeout(this.timer);
                    this.timer = null;
                }
                if (this.url) {
                    window.location.href = this.url;
                }
            },
        }"
        @keydown.escape.window="closeRedirect()"
    >
        <div class="mb-6 text-center lg:text-start">
            <span class="mkt-eyebrow">{{ __('Log in') }}</span>
            <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Welcome back') }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ __('auth_login_nova_sub') }}</p>
        </div>

        <x-auth-session-status
            class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800"
            :status="session('status')"
        />

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <div>
                <x-input-label for="email" :value="__('Email')" class="!text-slate-600" />
                <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <x-auth-password-field id="password" name="password" autocomplete="current-password" />

            <div class="flow-auth-inset flex flex-col gap-3 rounded-xl px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2.5">
                    <input
                        id="remember_me"
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-slate-300 text-[#EA580C] shadow-sm focus:ring-[#EA580C]"
                    />
                    <span class="text-sm font-medium text-slate-700">{{ __('Remember me') }}</span>
                </label>
                @if (Route::has('password.request'))
                    <a
                        class="text-sm font-semibold text-[#C2410C] underline-offset-2 transition hover:text-[#EA580C] hover:underline"
                        href="{{ route('password.request') }}"
                    >{{ __('Forgot your password?') }}</a>
                @endif
            </div>

            @include('auth.partials.math-captcha', ['captcha' => $captcha, 'inputId' => 'login_captcha'])

            <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg mt-1 w-full justify-center">
                {{ __('Log in') }}
            </button>
        </form>

        <div class="relative mt-7">
            <div class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-[rgba(91,100,140,0.14)]" aria-hidden="true"></div>
            <p class="relative mx-auto w-fit bg-white px-3 text-center text-xs font-medium uppercase tracking-wider text-slate-400">
                {{ __('Or continue with') }}
            </p>
        </div>

        <div class="mt-5 flex items-center justify-center gap-3">
            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[rgba(91,100,140,0.16)] bg-white text-slate-700 shadow-sm transition hover:border-[rgba(234,88,12,0.35)] hover:bg-[rgba(234,88,12,0.04)] hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(234,88,12,0.35)]"
                @click="openRedirect('google', @js($oauthProviders['google']['label']), @js($oauthProviders['google']['url']))"
                aria-label="{{ __('Continue with :provider', ['provider' => 'Google']) }}"
                title="Google"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
            </button>

            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[rgba(91,100,140,0.16)] bg-white text-slate-800 shadow-sm transition hover:border-[rgba(234,88,12,0.35)] hover:bg-[rgba(234,88,12,0.04)] hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(234,88,12,0.35)]"
                @click="openRedirect('github', @js($oauthProviders['github']['label']), @js($oauthProviders['github']['url']))"
                aria-label="{{ __('Continue with :provider', ['provider' => 'GitHub']) }}"
                title="GitHub"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
            </button>

            <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[rgba(91,100,140,0.16)] bg-white text-[#0A66C2] shadow-sm transition hover:border-[rgba(10,102,194,0.35)] hover:bg-[rgba(10,102,194,0.04)] hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(10,102,194,0.3)]"
                @click="openRedirect('linkedin', @js($oauthProviders['linkedin']['label']), @js($oauthProviders['linkedin']['url']))"
                aria-label="{{ __('Continue with :provider', ['provider' => 'LinkedIn']) }}"
                title="LinkedIn"
            >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
            </button>
        </div>

        <p class="mt-6 text-center text-sm text-slate-500 lg:text-start">
            {{ __('New here?') }}
            <a class="font-semibold text-[#C2410C] underline decoration-[rgba(234,88,12,0.35)] underline-offset-2 transition hover:text-[#EA580C]" href="{{ route('register') }}">{{ __('Create workspace') }}</a>
        </p>

        {{-- Redirect confirmation modal (no full-page jump until here) --}}
        <div
            x-show="redirectOpen"
            x-cloak
            class="fixed inset-0 z-[80] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="auth-oauth-redirect-title"
        >
            <div
                class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"
                @click="closeRedirect()"
                aria-hidden="true"
            ></div>

            <div
                class="relative z-10 w-full max-w-sm overflow-hidden rounded-2xl border border-[rgba(91,100,140,0.14)] bg-white shadow-[0_24px_60px_-20px_rgba(15,23,42,0.35)]"
                @click.stop
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                x-transition:leave-end="opacity-0 translate-y-2 scale-95"
            >
                <div class="h-1 bg-gradient-to-r from-[#EA580C] via-[#F97316] to-[#FBBF24]" aria-hidden="true"></div>

                <div class="px-6 pb-6 pt-6 text-center">
                    <div class="flow-auth-inset mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl">
                        <template x-if="provider === 'google'">
                            <svg class="h-7 w-7" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                        </template>
                        <template x-if="provider === 'github'">
                            <svg class="h-7 w-7 text-slate-800" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                        </template>
                        <template x-if="provider === 'linkedin'">
                            <svg class="h-7 w-7 text-[#0A66C2]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </template>
                    </div>

                    <h2 id="auth-oauth-redirect-title" class="mkt-display text-lg font-semibold !text-slate-900">
                        <span x-text="@js(__('Redirecting to :provider…')).replace(':provider', label)"></span>
                    </h2>
                    <p class="mt-2 text-sm text-slate-500">
                        {{ __('You will leave this page to sign in securely. This may take a moment.') }}
                    </p>

                    <div class="mt-5 flex items-center justify-center gap-2 text-sm text-slate-500" aria-live="polite">
                        <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-[rgba(234,88,12,0.25)] border-t-[#EA580C]" aria-hidden="true"></span>
                        {{ __('Preparing redirect…') }}
                    </div>

                    <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                        <button type="button" class="mkt-btn mkt-btn-primary w-full justify-center sm:w-auto" @click="goNow()">
                            {{ __('Continue') }}
                        </button>
                        <button type="button" class="mkt-btn mkt-btn-ghost w-full justify-center sm:w-auto" @click="closeRedirect()">
                            {{ __('Cancel') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
