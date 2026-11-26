<div>
    <x-input-label for="unit_select" :value="__('Unit')" />
    <select
        id="unit_select"
        class="flow-input-select mt-1 block w-full"
        x-model="unit"
    >
        <template x-for="opt in units" :key="opt.value">
            <option :value="opt.value" x-text="opt.label" :selected="unit === opt.value"></option>
        </template>
        <option value="__custom__">{{ __('catalog_unit_custom') }}</option>
    </select>
    <input type="hidden" name="unit" :value="resolvedUnit">
    <x-text-input
        id="unit_custom"
        class="mt-2 block w-full"
        x-show="unit === '__custom__'"
        x-cloak
        x-model="customUnit"
        maxlength="32"
        :placeholder="__('catalog_unit_custom_placeholder')"
    />
    <x-input-error :messages="$errors->get('unit')" class="mt-2" />
</div>
