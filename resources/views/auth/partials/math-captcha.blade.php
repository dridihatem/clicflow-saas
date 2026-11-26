@props([
    'captcha' => [],
    'inputId' => 'auth_captcha',
])

<div {{ $attributes->merge(['class' => 'flow-auth-inset rounded-xl p-4']) }}>
    <x-input-label for="{{ $inputId }}" :value="__('Math CAPTCHA')" class="!text-slate-400" />
    <p class="flow-auth-inset-title mt-1.5 text-sm font-semibold tabular-nums">{{ $captcha['question'] ?? '' }}</p>
    <input type="hidden" name="_captcha_token" value="{{ $captcha['token'] ?? '' }}" />
    <x-text-input
        id="{{ $inputId }}"
        name="_captcha_answer"
        type="number"
        inputmode="numeric"
        class="mt-2 block w-full max-w-[8rem]"
        :value="old('_captcha_answer')"
        autocomplete="off"
    />
    <p class="mt-2 text-xs text-slate-500">{{ __('Auth captcha hint') }}</p>
    <x-input-error :messages="$errors->get('_captcha_answer')" class="mt-2" />
</div>
