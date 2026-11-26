@php
    $errorStepMap = [
        1 => ['company_name'],
        2 => ['country'],
        3 => ['vat_percent'],
        4 => ['phone', 'phone_country_iso', 'phone_national_number'],
        5 => ['contact_email', 'website', 'industry', 'tax_id', 'address_line1', 'city', 'postal_code', 'default_currency'],
        6 => ['name'],
        7 => ['email'],
        8 => ['password', 'password_confirmation'],
        9 => ['_captcha_answer'],
    ];
    $initialStep = 1;
    foreach ($errorStepMap as $step => $fields) {
        foreach ($fields as $field) {
            if ($errors->has($field)) {
                $initialStep = $step;
                break 2;
            }
        }
    }
@endphp

<x-guest-layout hero-variant="register">
    <div
        class="relative"
        x-data="{
            step: {{ (int) $initialStep }},
            maxStep: 9,
            goNext() {
                if (! this.validateStep()) return;
                if (this.step < this.maxStep) {
                    this.step++;
                    this.$nextTick(() => {
                        if (this.step === 4 && typeof window.flowdeskSyncPhoneIsoFromCountry === 'function') {
                            window.flowdeskSyncPhoneIsoFromCountry();
                        }
                        this.focusStep();
                    });
                }
            },
            goBack() {
                if (this.step > 1) {
                    this.step--;
                    try { Alpine.store('authPeek').uncover(); } catch (e) {}
                    this.$nextTick(() => {
                        if (this.step === 4 && typeof window.flowdeskSyncPhoneIsoFromCountry === 'function') {
                            window.flowdeskSyncPhoneIsoFromCountry();
                        }
                        this.focusStep();
                    });
                }
            },
            skip() {
                if (this.step < this.maxStep) {
                    this.step++;
                    this.$nextTick(() => {
                        if (this.step === 4 && typeof window.flowdeskSyncPhoneIsoFromCountry === 'function') {
                            window.flowdeskSyncPhoneIsoFromCountry();
                        }
                        this.focusStep();
                    });
                }
            },
            validateStep() {
                const map = {
                    1: ['company_name'],
                    6: ['name'],
                    7: ['email'],
                    8: ['password', 'password_confirmation'],
                    9: ['register_captcha'],
                };
                const ids = map[this.step] || [];
                for (const id of ids) {
                    const el = document.getElementById(id);
                    if (el && ! el.checkValidity()) {
                        el.reportValidity();
                        return false;
                    }
                }
                if (this.step === 8) {
                    const p = document.getElementById('password');
                    const c = document.getElementById('password_confirmation');
                    if (p && c && p.value !== c.value) {
                        c.setCustomValidity(@js(__('The password field confirmation does not match.')));
                        c.reportValidity();
                        c.setCustomValidity('');
                        return false;
                    }
                }
                if (this.step === 5) {
                    const email = document.getElementById('contact_email');
                    if (email && email.value.trim() !== '' && ! email.checkValidity()) {
                        email.reportValidity();
                        return false;
                    }
                }
                return true;
            },
            focusStep() {
                const root = this.$refs.questionRoot;
                if (! root) return;
                const el = root.querySelector('input:not([type=hidden]), select, textarea');
                if (el) el.focus({ preventScroll: true });
            },
            onEnter(e) {
                if (e.target.tagName === 'TEXTAREA') return;
                e.preventDefault();
                if (this.step < this.maxStep) this.goNext();
            },
        }"
        x-init="$nextTick(() => focusStep())"
        @keydown.escape.window="if (step > 1) goBack()"
    >
        <div class="mb-5 text-center lg:text-start">
            <span class="mkt-eyebrow">{{ __('Register') }}</span>
            <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Create workspace') }}</h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ __('auth_register_nova_sub') }}</p>
        </div>

        {{-- Progress --}}
        <div class="mb-6">
            <div class="mb-2 flex items-center justify-between gap-3 text-xs font-medium text-slate-500">
                <span>
                    <span x-text="step"></span>
                    <span class="text-slate-400">/</span>
                    <span x-text="maxStep"></span>
                </span>
                <span x-text="Math.round((step / maxStep) * 100) + '%'"></span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-slate-200/90">
                <div
                    class="h-full rounded-full bg-[#EA580C] transition-all duration-300 ease-out"
                    :style="'width:' + ((step / maxStep) * 100) + '%'"
                ></div>
            </div>
            <div class="mt-3 flex justify-between gap-1">
                <template x-for="i in maxStep" :key="'dot-' + i">
                    <button
                        type="button"
                        class="h-1.5 flex-1 rounded-full transition-colors"
                        :class="i <= step ? 'bg-[#EA580C]' : 'bg-slate-200'"
                        @click="if (i < step) { step = i; $nextTick(() => focusStep()) }"
                        :aria-label="'Step ' + i"
                    ></button>
                </template>
            </div>
        </div>

        <form
            method="POST"
            action="{{ route('register') }}"
            class="space-y-5"
            @keydown.enter="onEnter($event)"
        >
            @csrf

            <div
                x-ref="questionRoot"
                class="flow-auth-inset min-h-[11.5rem] rounded-2xl p-5 sm:p-6"
            >
                {{-- 1: Company name --}}
                <div x-show="step === 1" x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 1</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_company_name') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('reg_q_company_name_hint') }}</p>
                    </div>
                    <div>
                        <x-input-label for="company_name" :value="__('Company name')" class="sr-only" />
                        <x-text-input id="company_name" class="mt-1 block w-full text-base" type="text" name="company_name" :value="old('company_name')" required autocomplete="organization" placeholder="{{ __('e.g. Acme Studio') }}" />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>
                </div>

                {{-- 2: Country --}}
                <div x-show="step === 2" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 2</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_country') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Country list hint') }}</p>
                    </div>
                    <div>
                        <x-input-label for="country" :value="__('Country')" class="sr-only" />
                        @include('auth.partials.country-select', ['countries' => $countries, 'id' => 'country', 'name' => 'country', 'value' => old('country')])
                        <x-input-error :messages="$errors->get('country')" class="mt-2" />
                    </div>
                </div>

                {{-- 3: VAT --}}
                <div x-show="step === 3" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 3</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_vat') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('VAT country hint') }}</p>
                    </div>
                    @include('auth.partials.vat-percent-field')
                </div>

                {{-- 4: Phone --}}
                <div x-show="step === 4" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 4</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_phone') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Phone optional hint') }}</p>
                    </div>
                    @include('auth.partials.phone-fields', ['countries' => $countries, 'dialCodes' => $dialCodes])
                </div>

                {{-- 5: Optional company profile --}}
                <div x-show="step === 5" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 5</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_company_extra') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('Optional details — you can finish these later in settings.') }}</p>
                    </div>
                    <div class="max-h-56 space-y-1 overflow-y-auto pe-1">
                        @include('auth.partials.company-profile-info')
                    </div>
                </div>

                {{-- 6: Your name --}}
                <div x-show="step === 6" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 6</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_your_name') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('reg_q_your_name_hint') }}</p>
                    </div>
                    <div>
                        <x-input-label for="name" :value="__('Your name')" class="sr-only" />
                        <x-text-input id="name" class="mt-1 block w-full text-base" type="text" name="name" :value="old('name')" required autocomplete="name" placeholder="{{ __('Your name') }}" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                </div>

                {{-- 7: Email --}}
                <div x-show="step === 7" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 7</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_email') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('reg_q_email_hint') }}</p>
                    </div>
                    <div>
                        <x-input-label for="email" :value="__('Email')" class="sr-only" />
                        <x-text-input id="email" class="mt-1 block w-full text-base" type="email" name="email" :value="old('email')" required autocomplete="username" placeholder="you@company.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                {{-- 8: Password --}}
                <div x-show="step === 8" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 8</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_password') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('reg_q_password_hint') }}</p>
                    </div>
                    <x-auth-password-field id="password" name="password" autocomplete="new-password" />
                    <x-auth-password-field
                        id="password_confirmation"
                        name="password_confirmation"
                        :label="__('Confirm Password')"
                        autocomplete="new-password"
                    />
                </div>

                {{-- 9: Captcha --}}
                <div x-show="step === 9" x-cloak x-transition.opacity.duration.200ms class="space-y-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-[#C2410C]">{{ __('Question') }} 9</p>
                        <h2 class="mkt-display mt-1.5 text-xl font-semibold !text-slate-900">{{ __('reg_q_captcha') }}</h2>
                        <p class="mt-2 text-sm text-slate-600">{{ __('reg_q_captcha_hint') }}</p>
                    </div>
                    @include('auth.partials.math-captcha', ['captcha' => $captcha, 'inputId' => 'register_captcha'])
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="button"
                    class="text-sm font-semibold text-slate-600 transition hover:text-slate-900"
                    x-show="step > 1"
                    x-cloak
                    @click="goBack()"
                >
                    ← {{ __('Back') }}
                </button>

                <button
                    type="button"
                    class="text-sm font-semibold text-slate-500 underline-offset-2 transition hover:text-[#C2410C] hover:underline"
                    x-show="[2,3,4,5].includes(step)"
                    x-cloak
                    @click="skip()"
                >
                    {{ __('Skip for now') }}
                </button>

                <div class="ms-auto flex items-center gap-2">
                    <button
                        type="button"
                        class="mkt-btn mkt-btn-primary !rounded-xl !px-5 !py-2.5"
                        x-show="step < maxStep"
                        @click="goNext()"
                    >
                        {{ __('Next') }} →
                    </button>

                    <button
                        type="submit"
                        class="mkt-btn mkt-btn-primary !rounded-xl !px-6 !py-2.5"
                        x-show="step === maxStep"
                        x-cloak
                    >
                        {{ __('Create workspace') }}
                    </button>
                </div>
            </div>

            <p class="pt-1 text-center text-sm text-slate-600 lg:text-start">
                {{ __('Already registered?') }}
                <a class="font-semibold text-[#C2410C] underline decoration-[rgba(234,88,12,0.35)] underline-offset-2 transition hover:text-[#EA580C]" href="{{ route('login') }}">{{ __('Log in') }}</a>
            </p>
        </form>

        <p class="mt-5 text-center text-sm text-slate-500">
            {{ __('Use social sign-in on the login page if you prefer Google, GitHub, or LinkedIn.') }}
        </p>
    </div>

    @include('auth.partials.country-registration-data')
</x-guest-layout>
