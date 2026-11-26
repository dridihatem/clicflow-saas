@props([
    'name' => 'cartesia_tts_voice',
    'id' => null,
    'selected' => '',
    'includeEmpty' => true,
    'emptyLabel' => null,
    'class' => 'mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100',
])

@php
    $cartesia = app(\App\Services\CartesiaTextToSpeechService::class);
    $grouped = $cartesia->catalogGroupedByLanguage();
    $labels = $cartesia->languageLabels();
    $selected = (string) $selected;
    $inputId = $id ?: $name;
    $flat = $cartesia->catalog();
@endphp

<select
    id="{{ $inputId }}"
    name="{{ $name }}"
    {{ $attributes->merge(['class' => $class]) }}
>
    @if ($includeEmpty)
        <option value="" @selected($selected === '')>{{ $emptyLabel ?: __('Use platform default voice') }}</option>
    @endif

    @foreach ($grouped as $lang => $voices)
        <optgroup label="{{ $labels[$lang] ?? strtoupper($lang) }}">
            @foreach ($voices as $voiceId => $voiceLabel)
                <option value="{{ $voiceId }}" @selected($selected === $voiceId)>{{ $voiceLabel }}</option>
            @endforeach
        </optgroup>
    @endforeach

    @if ($selected !== '' && ! array_key_exists($selected, $flat))
        <optgroup label="{{ __('Custom voice') }}">
            <option value="{{ $selected }}" selected>{{ $selected }}</option>
        </optgroup>
    @endif
</select>
