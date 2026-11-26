@props([
    'id' => 'password',
    'name' => 'password',
    'label' => null,
    'autocomplete' => 'current-password',
    'required' => true,
])

@php
    $label = $label ?? __('Password');
@endphp

{{-- Local x-data so toggle works even without an ancestor Alpine scope; syncs robot via $store.authPeek --}}
<div
    x-data="{
        show: false,
        toggle() {
            this.show = ! this.show;
            $store.authPeek.showPassword = this.show;
            $store.authPeek.sync();
        },
        onFocus() {
            $store.authPeek.cover();
        },
        onBlur() {
            $store.authPeek.focused = false;
            $store.authPeek.sync();
        },
        onInput(el) {
            $store.authPeek.setValue(el.value);
            $store.authPeek.cover();
        },
    }"
>
    <x-input-label :for="$id" :value="$label" class="!text-slate-600" />
    <div class="relative mt-2">
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="password"
            x-bind:type="show ? 'text' : 'password'"
            class="flow-input block w-full pe-12 ps-3"
            @if ($required) required @endif
            autocomplete="{{ $autocomplete }}"
            x-on:focus="onFocus()"
            x-on:blur="onBlur()"
            x-on:input="onInput($event.target)"
            {{ $attributes->except(['id', 'name', 'type', 'class', 'required', 'autocomplete']) }}
        />
        {{-- mousedown.prevent: avoid blur-before-click killing the toggle --}}
        <button
            type="button"
            class="auth-password-toggle absolute end-1.5 top-1/2 inline-flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-lg text-slate-400 transition hover:bg-[rgba(124,92,252,0.08)] hover:text-[#5b45d6] focus:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(124,92,252,0.35)]"
            x-on:mousedown.prevent="toggle()"
            :aria-pressed="show.toString()"
            :aria-label="show ? @js(__('Hide password')) : @js(__('Show password'))"
            :title="show ? @js(__('Hide password')) : @js(__('Show password'))"
        >
            <svg x-show="! show" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
            <svg x-show="show" x-cloak class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
            </svg>
        </button>
    </div>
    <x-input-error :messages="$errors->get($name)" class="mt-2" />
</div>
