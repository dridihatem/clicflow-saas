@php
    $taxProduct = $product ?? null;
    $taxInput = static fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    $companyVat = (float) data_get(auth()->user()?->company?->settings, 'billing.vat_percent', 0);
@endphp
<div class="rounded-2xl border border-amber-200/70 bg-amber-50/40 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
    <div class="mb-3 flex items-center gap-2">
        <x-cf-icon name="commission" tone="amber" class="h-5 w-5" />
        <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('product_tax_section') }}</p>
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <x-input-label for="tax_rate" :value="__('product_tax_rate')" />
            <div class="relative mt-1">
                <x-text-input id="tax_rate" name="tax_rate" type="number" step="0.01" min="0" max="100" class="block w-full pe-8" :value="old('tax_rate', $taxInput($taxProduct?->tax_rate))" placeholder="{{ $taxInput($companyVat ?: null) ?: '20' }}" />
                <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-slate-400">%</span>
            </div>
            <x-input-error :messages="$errors->get('tax_rate')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="tax_rate_2" :value="__('product_tax_rate_2')" />
            <div class="relative mt-1">
                <x-text-input id="tax_rate_2" name="tax_rate_2" type="number" step="0.01" min="0" max="100" class="block w-full pe-8" :value="old('tax_rate_2', $taxInput($taxProduct?->tax_rate_2))" placeholder="0" />
                <span class="pointer-events-none absolute inset-y-0 end-3 flex items-center text-sm text-slate-400">%</span>
            </div>
            <x-input-error :messages="$errors->get('tax_rate_2')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="category" :value="__('product_category')" />
            <x-text-input id="category" name="category" class="mt-1 block w-full" :value="old('category', $taxProduct?->category)" maxlength="100" />
        </div>
    </div>
    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('product_tax_hint', ['vat' => $taxInput($companyVat) ?: '0']) }}</p>
</div>
