<x-guest-layout hero-variant="register">
    <div class="mb-5 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Partner') }}</span>
        <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Join as business provider') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ __('You are registering to collaborate with :company. After signup you will sign a partnership agreement; the company signs second to activate your account.', ['company' => $company->name]) }}
        </p>
    </div>

    <x-auth-session-status
        class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800"
        :status="session('status')"
    />

    <form method="POST" action="{{ route('partner.register.store', $slug) }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Full name')" class="!text-slate-600" />
            <x-text-input id="name" class="mt-1.5 block w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-slate-600" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="phone" :value="__('Phone or WhatsApp (optional)')" class="!text-slate-600" />
            <x-text-input id="phone" class="mt-1.5 block w-full" type="text" name="phone" :value="old('phone')" autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="job_title" :value="__('Role / specialty (optional)')" class="!text-slate-600" />
            <x-text-input id="job_title" class="mt-1.5 block w-full" type="text" name="job_title" :value="old('job_title')" />
            <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
        </div>

        <x-auth-password-field id="password" name="password" autocomplete="new-password" />

        <x-auth-password-field
            id="password_confirmation"
            name="password_confirmation"
            :label="__('Confirm password')"
            autocomplete="new-password"
        />

        <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg mt-1 w-full justify-center">
            {{ __('Create account and continue') }}
        </button>
    </form>

    <p class="mt-6 text-center text-sm text-slate-500 lg:text-start">
        {{ __('Already have an account?') }}
        <a href="{{ route('login') }}" class="font-semibold text-[#C2410C] underline-offset-2 hover:underline">{{ __('Log in') }}</a>
    </p>
</x-guest-layout>
