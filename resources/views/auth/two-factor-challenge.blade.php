<x-guest-layout>
    <div class="mb-5 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Security') }}</span>
        <h1 class="mkt-display mt-2 text-xl font-semibold !text-[#EA580C]">{{ __('Two-factor authentication') }}</h1>
    </div>

    <p class="mb-4 text-sm leading-relaxed text-slate-600">
        {{ __('Enter the code from your authenticator app, or a recovery code.') }}
    </p>

    <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="code" :value="__('Authentication code')" class="!text-slate-600" />
            <x-text-input id="code" class="mt-2 block w-full" type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus />
            <x-input-error :messages="$errors->get('code')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end">
            <button type="submit" class="mkt-btn mkt-btn-primary">
                {{ __('Continue') }}
            </button>
        </div>
    </form>
</x-guest-layout>
