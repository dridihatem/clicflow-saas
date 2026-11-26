@php
    $catalogFormConfig = \App\Support\CatalogUnits::formConfig(
        old('kind', 'product'),
        old('unit', ''),
        old('name', ''),
        old('description', ''),
        (bool) ($flowdeskPlanGates['ai_credits'] ?? false),
    );
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('commerce_add_item') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            <x-flow.page-header
                :title="__('commerce_add_item')"
                :description="__('commerce_add_item_intro')"
            />

            <form
                method="POST"
                action="{{ route('commerce.products.store') }}"
                class="flow-panel space-y-5 p-6 sm:p-8"
                x-data="productCatalogForm({{ \Illuminate\Support\Js::from($catalogFormConfig) }})"
                x-on:change="if ($event.target && $event.target.id === 'kind') onKindChange()"
            >
                @csrf
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-input-label for="name" :value="__('Name')" />
                        @include('commerce.products.partials.ai-copy')
                    </div>
                    <x-text-input id="name" name="name" class="mt-1 block w-full" x-model="name" :value="old('name')" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="kind" :value="__('Type')" />
                    <select id="kind" name="kind" x-model="kind" class="flow-input-select mt-1 block w-full">
                        <option value="product">{{ __('catalog_kind_product') }}</option>
                        <option value="service">{{ __('catalog_kind_service') }}</option>
                    </select>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="sku" :value="__('SKU')" />
                        <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku')" />
                    </div>
                    <div>
                        <x-input-label for="base_price" :value="__('Price')" />
                        <x-text-input id="base_price" name="base_price" type="number" step="0.01" class="mt-1 block w-full" :value="old('base_price', '0')" required />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="currency" :value="__('Currency')" />
                        <x-text-input id="currency" name="currency" class="mt-1 block w-full" :value="old('currency', auth()->user()->company->default_currency ?? flowdesk_default_currency())" maxlength="3" required />
                    </div>
                    @include('commerce.products.partials.unit-field')
                </div>

                @include('commerce.products.partials.tax-fields')

                <div class="grid gap-4 sm:grid-cols-2" x-show="kind === 'product'" x-cloak>
                    <div>
                        <x-input-label for="color" :value="__('Color')" />
                        <x-text-input id="color" name="color" class="mt-1 block w-full" :value="old('color')" />
                    </div>
                    <div>
                        <x-input-label for="size" :value="__('Size')" />
                        <x-text-input id="size" name="size" class="mt-1 block w-full" :value="old('size')" />
                    </div>
                </div>

                <div x-show="kind === 'product'" x-cloak>
                    <x-input-label for="stock_qty" :value="__('Stock')" />
                    <x-text-input id="stock_qty" name="stock_qty" type="number" class="mt-1 block w-full" :value="old('stock_qty', 0)" />
                    <label class="mt-3 inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="track_stock" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('track_stock', '1') === '1')>
                        {{ __('Track stock') }}
                    </label>
                </div>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea id="description" name="description" rows="4" class="flow-input mt-1 block w-full" x-model="description">{{ old('description') }}</textarea>
                </div>

                <div class="flex flex-wrap gap-3">
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    <a href="{{ route('commerce.products.index') }}">
                        <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
