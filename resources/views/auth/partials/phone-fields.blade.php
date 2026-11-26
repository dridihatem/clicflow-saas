@props([
    'countries',
    'dialCodes',
    'phoneIsoId' => 'phone_country_iso',
    'nationalId' => 'phone_national_number',
    'nationalName' => 'phone_national_number',
])

@php
    $dialCodes = is_array($dialCodes) ? $dialCodes : [];
    $selectedIso = old('phone_country_iso', old('country'));
    $dialOptions = [];
    foreach ($countries as $iso => $name) {
        $dial = $dialCodes[$iso] ?? null;
        if ($dial === null || $dial === '') {
            continue;
        }
        $dialOptions[] = [
            'value' => (string) $iso,
            'label' => $name.' (+'.$dial.')',
            'search' => strtolower($iso.' '.$name.' '.$dial.' +'.$dial),
        ];
    }
@endphp

{{-- Narrow dial / indicatif + wider phone number --}}
<div class="grid grid-cols-1 items-start gap-3 sm:grid-cols-12">
    <div class="sm:col-span-4 lg:col-span-3">
        <x-input-label :for="$phoneIsoId" :value="__('Country code')" class="!text-slate-600 dark:!text-slate-400" />
        @include('auth.partials.searchable-select', [
            'options' => $dialOptions,
            'name' => 'phone_country_iso',
            'id' => $phoneIsoId,
            'value' => $selectedIso,
            'placeholder' => __('— Code —'),
            'searchPlaceholder' => __('Search code…'),
            'emptyLabel' => __('No countries found'),
            'selectClass' => 'flow-input-select !px-2.5 !text-sm',
            'autocomplete' => 'tel-country-code',
        ])
        <x-input-error :messages="$errors->get('phone_country_iso')" class="mt-2" />
    </div>

    <div class="sm:col-span-8 lg:col-span-9">
        <x-input-label :for="$nationalId" :value="__('Phone number')" class="!text-slate-600 dark:!text-slate-400" />
        <input
            id="{{ $nationalId }}"
            name="{{ $nationalName }}"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            autocomplete="tel-national"
            data-flowdesk-digits-only
            value="{{ old('phone_national_number') }}"
            placeholder="{{ __('e.g. 12345678') }}"
            class="flow-input mt-2 block w-full tabular-nums flowdesk-ltr-num"
        />
        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">{{ __('Digits only, no spaces or symbols.') }}</p>
        <x-input-error :messages="$errors->get('phone_national_number')" class="mt-2" />
    </div>
</div>
