@php
    $defaults = $defaults ?? [];
    $prefix = $prefix ?? 'rule';
    $idName = $prefix.'-name';
    $idCode = $prefix.'-code';
    $idKind = $prefix.'-kind';
    $idCalc = $prefix.'-calc';
    $idPercent = $prefix.'-percent';
    $idFixed = $prefix.'-fixed';
    $idOrder = $prefix.'-order';
    $initialCalc = (string) ($defaults['calculation'] ?? 'percent_of_base');
    $percentValue = $defaults['percent'] ?? '';
    if ($percentValue !== '' && $percentValue !== null) {
        $percentValue = rtrim(rtrim(number_format((float) $percentValue, 4, '.', ''), '0'), '.');
    }
@endphp

<div
    class="space-y-4"
    x-data="{ calculation: @js($initialCalc) }"
>
    <div>
        <x-input-label :for="$idName" :value="__('Name')" />
        <x-text-input :id="$idName" name="name" class="mt-1 block w-full" required :value="$defaults['name'] ?? ''" />
    </div>
    <div>
        <x-input-label :for="$idCode" :value="__('hr_rule_code')" />
        <x-text-input :id="$idCode" name="code" class="mt-1 block w-full" :value="$defaults['code'] ?? ''" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :for="$idKind" :value="__('Type')" />
            <select id="{{ $idKind }}" name="kind" class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                @foreach (\App\Enums\HrPayrollRuleKind::cases() as $kind)
                    <option value="{{ $kind->value }}" @selected(($defaults['kind'] ?? '') === $kind->value)>{{ $kind->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label :for="$idCalc" :value="__('hr_rule_calculation')" />
            <select
                id="{{ $idCalc }}"
                name="calculation"
                x-model="calculation"
                class="mt-1 block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
            >
                @foreach (\App\Enums\HrPayrollRuleCalculation::cases() as $calc)
                    <option value="{{ $calc->value }}" @selected(($defaults['calculation'] ?? '') === $calc->value)>{{ $calc->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div x-show="calculation === 'percent_of_base'" x-cloak>
            <x-input-label :for="$idPercent" :value="__('hr_rule_percent')" />
            <input
                id="{{ $idPercent }}"
                name="percent"
                type="number"
                step="0.01"
                min="0"
                max="100"
                class="flow-input mt-1 block w-full"
                value="{{ $percentValue }}"
                x-bind:disabled="calculation !== 'percent_of_base'"
                x-bind:required="calculation === 'percent_of_base'"
            />
        </div>
        <div x-show="calculation === 'fixed'" x-cloak>
            <x-input-label :for="$idFixed" :value="__('hr_rule_fixed_amount', ['cur' => $currency])" />
            <input
                id="{{ $idFixed }}"
                name="fixed_amount"
                type="text"
                inputmode="decimal"
                class="flow-input flowdesk-amount mt-1 block w-full"
                value="{{ $defaults['fixed_amount'] ?? '' }}"
                x-bind:disabled="calculation !== 'fixed'"
                x-bind:required="calculation === 'fixed'"
            />
        </div>
    </div>
    <div>
        <x-input-label :for="$idOrder" :value="__('hr_rule_sort_order')" />
        <x-text-input :id="$idOrder" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="$defaults['sort_order'] ?? 0" />
    </div>
    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" @checked(! empty($defaults['is_active']))>
        {{ __('Active') }}
    </label>
    <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
        <input type="hidden" name="include_on_payslip" value="0">
        <input type="checkbox" name="include_on_payslip" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800" @checked(! empty($defaults['include_on_payslip']))>
        {{ __('hr_rule_include_on_payslip') }}
    </label>
</div>
