@php
    $modalName = $modalName ?? 'contract-sample-modal';
    $defaultType = $defaultType ?? 'service';
    $preferredLocale = in_array(app()->getLocale(), ['en', 'fr', 'es', 'ar'], true)
        ? app()->getLocale()
        : 'en';
@endphp

<div class="mt-2 flex flex-wrap items-center gap-2">
    <button
        type="button"
        class="inline-flex items-center gap-2 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', '{{ $modalName }}')"
    >
        <i class="fa-solid fa-language text-sm" aria-hidden="true"></i>
        {{ __('Insert sample contract by language') }}
    </button>
</div>

@push('modals')
    <x-modal :name="$modalName" :show="false" maxWidth="lg">
        <div class="p-6" id="{{ $modalName }}-panel">
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('contract_sample_modal_title') }}</h3>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ __('contract_sample_modal_help') }}</p>

            <div class="mt-6 space-y-5">
                <div>
                    <x-input-label for="{{ $modalName }}_type" :value="__('contract_type_label')" />
                    <select id="{{ $modalName }}_type" name="sample_type" class="flow-input mt-2 block w-full dark:bg-slate-800 dark:text-slate-100">
                        @foreach (['service', 'nda', 'retainer', 'consulting'] as $typeKey)
                            <option value="{{ $typeKey }}" @selected($defaultType === $typeKey)>{{ __('contract_type_'.$typeKey) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label :value="__('Language')" />
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ([
                            'en' => __('Lang English short'),
                            'fr' => __('Lang French short'),
                            'es' => __('Lang Spanish short'),
                            'ar' => __('Lang Arabic short'),
                        ] as $loc => $label)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                <input type="radio" name="{{ $modalName }}_locale" value="{{ $loc }}" class="text-indigo-600 focus:ring-indigo-500" @checked($loc === $preferredLocale) />
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        x-data=""
                        x-on:click="$dispatch('close-modal', '{{ $modalName }}')"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <x-primary-button type="button" id="{{ $modalName }}-insert">{{ __('Insert sample') }}</x-primary-button>
                </div>
            </div>
        </div>
    </x-modal>
@endpush
