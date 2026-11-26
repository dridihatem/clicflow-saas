<x-guest-layout>
    <div class="mb-6 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Account') }}</span>
        <h1 class="mkt-display mt-2 text-2xl font-semibold tracking-tight !text-[#EA580C]">{{ __('Reset Password') }}</h1>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-slate-600" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-auth-password-field id="password" name="password" autocomplete="new-password" />

        <x-auth-password-field
            id="password_confirmation"
            name="password_confirmation"
            :label="__('Confirm Password')"
            autocomplete="new-password"
        />

        <div class="flex items-center justify-end pt-1">
            <button type="submit" class="mkt-btn mkt-btn-primary">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
</x-guest-layout>
