<x-guest-layout hero-variant="login">
    <div class="mb-6 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Admin') }}</span>
        <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Admin sign in') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ __('Sign in to manage the platform.') }}</p>
    </div>

    <x-auth-session-status
        class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm font-medium text-emerald-800"
        :status="session('status')"
    />

    <form method="POST" action="{{ route('admin.login.store') }}" class="space-y-4">
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
        </div>

        <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg mt-1 w-full justify-center">
            {{ __('Log in') }}
        </button>
    </form>
</x-guest-layout>
