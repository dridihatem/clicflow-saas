{{-- Expects Alpine line context: line, i, catalog --}}
<div class="sm:col-span-12">
    <label class="block text-xs font-medium text-slate-500">
        <span>{{ __('Product / service') }}</span>
        <select
            class="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            x-show="catalog.length"
            x-model="line.product_id"
            x-bind:name="'items['+i+'][product_id]'"
            @change="applyCatalog(line)"
        >
            <option value="">{{ __('Custom line') }}</option>
            <template x-for="p in catalog" :key="p.id">
                <option
                    :value="p.id"
                    x-text="p.label || (p.name + (p.kind === 'service' ? ' (' + @js(__('catalog_kind_service')) + ')' : ''))"
                ></option>
            </template>
        </select>
        <p
            class="mt-1 rounded-lg border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm font-normal text-slate-600 dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-300"
            x-show="!catalog.length"
            x-cloak
        >
            {{ __('document_catalog_empty_hint') }}
            @can('workspace.manage_commerce')
                <a href="{{ route('commerce.products.create') }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('Add product or service') }}</a>
            @endcan
        </p>
        <input type="hidden" x-bind:name="'items['+i+'][product_variant_id]'" x-bind:value="line.product_variant_id || ''" />
        <input type="hidden" x-bind:name="'items['+i+'][unit]'" x-bind:value="line.unit || ''" />
    </label>
    <p
        class="mt-1.5 text-xs text-slate-500 dark:text-slate-400"
        x-show="line.product_id && line.catalog_detail"
        x-text="line.catalog_detail"
        x-cloak
    ></p>
</div>
