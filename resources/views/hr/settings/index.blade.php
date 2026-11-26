@php
    $editModals = $rules;
@endphp

@push('modals')
    @foreach ($editModals as $rule)
        @php($modalName = 'edit-hr-rule-'.$rule->id)
        <x-modal :name="$modalName" :show="false" maxWidth="lg" focusable>
            <div class="p-6 sm:p-8">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Edit') }} — {{ $rule->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('hr_settings_intro') }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
                        x-on:click="$dispatch('close-modal', {{ \Illuminate\Support\Js::from($modalName) }})"
                        aria-label="{{ __('Close') }}"
                    >
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <form method="POST" action="{{ route('hr.settings.rules.update', $rule) }}" class="mt-6 space-y-4">
                    @csrf
                    @method('PUT')
                    @include('hr.settings._rule-fields', [
                        'currency' => $currency,
                        'prefix' => 'edit-'.$rule->id,
                        'defaults' => [
                            'name' => $rule->name,
                            'code' => $rule->code,
                            'kind' => $rule->kind->value,
                            'calculation' => $rule->calculation->value,
                            'percent' => $rule->percent,
                            'fixed_amount' => $rule->fixed_minor !== null ? flowdesk_major_amount_for_input((int) $rule->fixed_minor, $currency) : '',
                            'sort_order' => $rule->sort_order,
                            'is_active' => $rule->is_active,
                            'include_on_payslip' => $rule->include_on_payslip,
                        ],
                    ])
                    <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200/80 pt-4 dark:border-slate-700/80">
                        <x-secondary-button
                            type="button"
                            class="!normal-case"
                            x-on:click="$dispatch('close-modal', {{ \Illuminate\Support\Js::from($modalName) }})"
                        >
                            {{ __('Cancel') }}
                        </x-secondary-button>
                        <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </x-modal>
    @endforeach
@endpush

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_settings') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_settings')" :description="__('hr_settings_intro')" />

            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="flow-panel p-6 sm:p-8 lg:col-span-1">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_add_payroll_rule') }}</h3>
                    <form method="POST" action="{{ route('hr.settings.rules.store') }}" class="mt-4 space-y-4">
                        @csrf
                        @include('hr.settings._rule-fields', [
                            'currency' => $currency,
                            'prefix' => 'create',
                            'defaults' => [
                                'name' => old('name'),
                                'code' => old('code'),
                                'kind' => old('kind', 'deduction'),
                                'calculation' => old('calculation', 'percent_of_base'),
                                'percent' => old('percent'),
                                'fixed_amount' => old('fixed_amount'),
                                'sort_order' => old('sort_order', 0),
                                'is_active' => old('is_active', true),
                                'include_on_payslip' => old('include_on_payslip', true),
                            ],
                        ])
                        <x-primary-button type="submit" class="!normal-case inline-flex items-center gap-2">
                            <i class="fa-solid fa-plus text-xs" aria-hidden="true"></i>
                            {{ __('Add') }}
                        </x-primary-button>
                    </form>
                </div>

                <div class="flow-panel overflow-hidden p-0 lg:col-span-2">
                    <x-flow.table>
                        <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Order') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Type') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('hr_rule_calculation') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                            @forelse ($rules as $rule)
                                @php($modalName = 'edit-hr-rule-'.$rule->id)
                                <tr>
                                    <td class="px-4 py-3 text-sm tabular-nums">{{ $rule->sort_order }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $rule->name }}</div>
                                        @if ($rule->code)
                                            <div class="text-xs text-slate-500 dark:text-slate-400">{{ $rule->code }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">{{ $rule->kind->label() }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        @if ($rule->calculation === \App\Enums\HrPayrollRuleCalculation::PercentOfBase)
                                            {{ rtrim(rtrim(number_format((float) $rule->percent, 2, '.', ''), '0'), '.') }}%
                                        @else
                                            {{ flowdesk_format_minor((int) ($rule->fixed_minor ?? 0), $currency) }} {{ $currency }}
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' => $rule->is_active,
                                            'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => ! $rule->is_active,
                                        ])>
                                            {{ $rule->is_active ? __('Active') : __('Inactive') }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-end">
                                        <div class="inline-flex items-center justify-end gap-1">
                                            <button
                                                type="button"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                                title="{{ __('Edit') }}"
                                                aria-label="{{ __('Edit') }}"
                                                x-data
                                                x-on:click="$dispatch('open-modal', {{ \Illuminate\Support\Js::from($modalName) }})"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                            </button>
                                            <form method="POST" action="{{ route('hr.settings.rules.destroy', $rule) }}" class="inline" onsubmit="return confirm(@json(__('hr_rule_delete_confirm')))">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400" title="{{ __('Delete') }}" aria-label="{{ __('Delete') }}">
                                                    <i class="fa-regular fa-trash-can text-sm" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('hr_no_payroll_rules') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-flow.table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
