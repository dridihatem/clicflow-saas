@php
    $catalogFormConfig = \App\Support\CatalogUnits::formConfig(
        old('kind', $product->kind?->value ?? 'product'),
        old('unit', $product->unit ?? ''),
        old('name', $product->name ?? ''),
        old('description', $product->description ?? ''),
        (bool) ($flowdeskPlanGates['ai_credits'] ?? false),
    );
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $product->name }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $product->kind?->label() ?? __('catalog_kind_product') }}
                    · <span class="flowdesk-ltr-num tabular-nums">{{ flowdesk_format_minor((int) $product->base_price_minor, $product->currency) }} {{ $product->currency }}</span>
                    @if ($product->taxLabel() !== '')
                        · {{ __('product_tax_short') }} {{ $product->taxLabel() }}
                    @endif
                    @if ($product->category)
                        · {{ $product->category }}
                    @endif
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first() }}</div>
            @endif

            <form
                method="POST"
                action="{{ route('commerce.products.update', $product) }}"
                class="flow-panel space-y-5 p-6 sm:p-8"
                x-data="productCatalogForm({{ \Illuminate\Support\Js::from($catalogFormConfig) }})"
                x-on:change="if ($event.target && $event.target.id === 'kind') onKindChange()"
            >
                @csrf
                @method('PUT')
                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <x-input-label for="name" :value="__('Name')" />
                        @include('commerce.products.partials.ai-copy')
                    </div>
                    <x-text-input id="name" name="name" class="mt-1 block w-full" x-model="name" :value="old('name', $product->name)" required />
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="kind" :value="__('Type')" />
                        <select id="kind" name="kind" x-model="kind" class="flow-input-select mt-1 block w-full">
                            <option value="product">{{ __('catalog_kind_product') }}</option>
                            <option value="service">{{ __('catalog_kind_service') }}</option>
                        </select>
                    </div>
                    <div>
                        <x-input-label for="sku" :value="__('SKU')" />
                        <x-text-input id="sku" name="sku" class="mt-1 block w-full" :value="old('sku', $product->sku)" />
                    </div>
                    <div>
                        <x-input-label for="base_price" :value="__('Price')" />
                        <x-text-input
                            id="base_price"
                            name="base_price"
                            type="number"
                            step="0.01"
                            class="mt-1 block w-full"
                            :value="old('base_price', flowdesk_major_amount_for_input((int) $product->base_price_minor, (string) $product->currency))"
                            required
                        />
                    </div>
                    @include('commerce.products.partials.unit-field')
                    <label class="inline-flex items-center gap-2 self-end text-sm text-slate-700 dark:text-slate-200">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $product->is_active))>
                        {{ __('Active') }}
                    </label>
                </div>

                @include('commerce.products.partials.tax-fields', ['product' => $product])

                <label x-show="kind === 'product'" x-cloak class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="track_stock" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked(old('track_stock', $product->track_stock))>
                    {{ __('Track stock') }}
                </label>

                <div>
                    <x-input-label for="description" :value="__('Description')" />
                    <textarea id="description" name="description" rows="4" class="flow-input mt-1 block w-full" x-model="description">{{ old('description', $product->description) }}</textarea>
                </div>

                <x-primary-button type="submit">{{ __('commerce_update_item') }}</x-primary-button>
            </form>

            <div class="flow-panel space-y-4 p-6 sm:p-8">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('commerce_variants_heading') }}</h3>
                <ul class="space-y-3 text-sm">
                    @foreach ($product->variants as $variant)
                        <li class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-700">
                            <div>
                                <p class="font-medium text-slate-900 dark:text-white">{{ $variant->label() }}</p>
                                <p class="text-slate-500 dark:text-slate-400">
                                    {{ __('Stock') }}: {{ $variant->stock_qty }}
                                    · {{ flowdesk_format_minor($variant->unitPriceMinor(), $product->currency) }} {{ $product->currency }}
                                </p>
                            </div>
                            @if (! $product->isService() && $product->track_stock)
                                <form method="POST" action="{{ route('commerce.variants.stock', $variant) }}" class="flex items-center gap-2">
                                    @csrf
                                    <x-text-input name="delta" type="number" class="w-24" placeholder="+/-" required />
                                    <x-secondary-button type="submit">{{ __('Adjust') }}</x-secondary-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <form method="POST" action="{{ route('commerce.products.variants.store', $product) }}" class="grid gap-3 border-t border-slate-200/80 pt-4 sm:grid-cols-5 dark:border-slate-700">
                    @csrf
                    <x-text-input name="color" :placeholder="__('Color')" />
                    <x-text-input name="size" :placeholder="__('Size')" />
                    <x-text-input name="sku" :placeholder="__('SKU')" />
                    <x-text-input name="price" type="number" step="0.01" :placeholder="__('Price')" />
                    <x-text-input name="stock_qty" type="number" :placeholder="__('Stock')" value="0" />
                    <div class="sm:col-span-5">
                        <x-primary-button type="submit">{{ __('Add variant') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
