@props([
    'countries',
    'name' => 'country',
    'id' => 'country',
    'value' => null,
    'required' => false,
])

@php
    $options = [];
    foreach ($countries as $code => $label) {
        $options[] = [
            'value' => (string) $code,
            'label' => (string) $label,
            'search' => strtolower($code.' '.$label),
        ];
    }
@endphp

@include('auth.partials.searchable-select', [
    'options' => $options,
    'name' => $name,
    'id' => $id,
    'value' => $value,
    'required' => $required,
    'placeholder' => __('— Select country —'),
    'searchPlaceholder' => __('Search country…'),
    'emptyLabel' => __('No countries found'),
    'selectClass' => 'flow-input-select',
    'autocomplete' => 'country',
])
