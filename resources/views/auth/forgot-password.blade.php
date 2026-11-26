<x-guest-layout hero-variant="forgot">
    <div class="mb-6 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Account') }}</span>
        <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Forgot your password?') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">
            {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
        </p>
    </div>

    <x-auth-session-status
        class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800"
        :status="session('status')"
    />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-slate-600" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <a class="text-sm font-semibold text-[#C2410C] underline decoration-[rgba(234,88,12,0.35)] underline-offset-2 hover:text-[#EA580C]" href="{{ route('login') }}">
                ← {{ __('Back to sign in') }}
            </a>
            <button type="submit" class="mkt-btn mkt-btn-primary">
                {{ __('Email Password Reset Link') }}
            </button>
        </div>
    </form>
</x-guest-layout>
