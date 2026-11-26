<x-guest-layout>
    <div class="mb-6 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Security') }}</span>
        <h1 class="mkt-display mt-2 text-xl font-semibold !text-[#EA580C]">{{ __('Confirm password') }}</h1>
    </div>

    <div class="mb-5 text-sm leading-relaxed text-slate-600">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4">
        @csrf

        <x-auth-password-field id="password" name="password" autocomplete="current-password" />

        <div class="flex justify-end pt-1">
            <button type="submit" class="mkt-btn mkt-btn-primary">
                {{ __('Confirm') }}
            </button>
        </div>
    </form>
</x-guest-layout>
