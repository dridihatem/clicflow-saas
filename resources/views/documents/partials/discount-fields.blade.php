{{-- Document discount controls (quotes & invoices). Requires the documentLineFormMixin Alpine scope. --}}
@php
    $doc = $doc ?? null;
    $dt = (string) old('discount_type', $doc?->discount_type ?? '');
    $dv = old('discount_value');
    if ($dv === null) {
        $dv = ($doc && $doc->discount_value !== null && $doc->discount_type)
            ? rtrim(rtrim(number_format((float) $doc->discount_value, 4, '.', ''), '0'), '.')
            : '';
    }
@endphp
<div class="flex flex-wrap items-center justify-end gap-2 {{ $class ?? '' }}" data-cf-discount
    x-init="discountType = @js($dt); discountValue = @js((string) $dv)">
    <label for="doc_discount_type" class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('Discount') }}</label>
    <select id="doc_discount_type" name="discount_type" x-model="discountType" class="flow-input-select w-40 py-1.5 text-sm"
        @change="if (!discountType) discountValue = ''">
        <option value="">{{ __('No discount') }}</option>
        <option value="percent">{{ __('Percentage (%)') }}</option>
        <option value="amount">{{ __('Fixed amount (ex. VAT)') }}</option>
    </select>
    <div class="relative" x-show="discountType" x-cloak>
        <input id="doc_discount_value" name="discount_value" type="text" inputmode="decimal" maxlength="24" x-model="discountValue"
            class="flow-input w-28 py-1.5 pe-8 text-end text-sm tabular-nums" :placeholder="discountType === 'percent' ? '10' : '50'">
        <span class="pointer-events-none absolute inset-y-0 end-2 flex items-center text-xs text-slate-500" x-text="discountType === 'percent' ? '%' : (typeof currencyCode !== 'undefined' ? currencyCode : '')"></span>
    </div>
    <x-input-error class="w-full text-end" :messages="array_merge($errors->get('discount_type'), $errors->get('discount_value'))" />
</div>
