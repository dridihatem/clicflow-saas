@props(['disabled' => false])

@php
    $inputClass = 'flow-input';
    $name = (string) $attributes->get('name', '');
    $id = (string) $attributes->get('id', '');
    $type = strtolower((string) $attributes->get('type', ''));
    $autocomplete = strtolower((string) $attributes->get('autocomplete', ''));

    // Keep label[for] ↔ input[id] in sync when callers only pass name.
    if ($id === '' && $name !== '') {
        $attributes = $attributes->merge(['id' => $name]);
        $id = $name;
    }

    $nameLower = strtolower($name);
    $idLower = strtolower($id);

    if (
        $type === 'tel'
        || str_contains($nameLower, 'phone')
        || str_contains($idLower, 'phone')
        || str_contains($autocomplete, 'tel')
    ) {
        $inputClass .= ' flowdesk-ltr-num tabular-nums';
    }
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => $inputClass]) }}>
