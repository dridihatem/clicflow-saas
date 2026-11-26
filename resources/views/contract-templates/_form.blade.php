@php
    $typeKeys = ['service', 'nda', 'retainer', 'consulting', 'custom'];
@endphp

<div class="grid gap-5 lg:grid-cols-2">
    <x-flow.form-card tone="indigo" icon="fa-solid fa-file-lines" :title="__('Name')">
        <x-input-label for="template_name" :value="__('Name')" class="sr-only" />
        <x-text-input id="template_name" name="name" type="text" class="mt-0 block w-full" :value="old('name', $template?->name)" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </x-flow.form-card>

    <x-flow.form-card tone="violet" icon="fa-solid fa-layer-group" :title="__('contract_type_label')">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <x-input-label for="template_type_key" :value="__('contract_type_label')" />
                <select id="template_type_key" name="type_key" class="flow-input mt-1 block w-full" required>
                    @foreach ($typeKeys as $key)
                        <option value="{{ $key }}" @selected(old('type_key', $template?->type_key ?? 'custom') === $key)>{{ __('contract_type_'.$key) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type_key')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="template_sort_order" :value="__('Sort order')" />
                <x-text-input id="template_sort_order" name="sort_order" type="number" min="0" class="mt-1 block w-full" :value="old('sort_order', $template?->sort_order ?? 0)" />
                <x-input-error :messages="$errors->get('sort_order')" class="mt-1" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200 sm:col-span-2">
                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500" @checked(old('is_active', $template?->is_active ?? true)) />
                <span>{{ __('Active') }}</span>
            </label>
        </div>
    </x-flow.form-card>
</div>

<x-flow.form-card tone="emerald" icon="fa-solid fa-pen-nib" :title="__('contract_body_label')" :hint="__('contract_merge_tags_help')">
    @if (! empty($mergeHints))
        <div class="flex flex-wrap gap-2" id="contract-merge-hints">
            @foreach ($mergeHints as $hint)
                <button type="button"
                    class="rounded-md border border-emerald-200/80 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-slate-800 dark:text-slate-200"
                    data-tag="{{ '{'.'{'.$hint['key'].'}'.'}' }}">
                    {{ '{'.'{'.$hint['key'].'}'.'}' }} — {{ $hint['label'] }}
                </button>
            @endforeach
        </div>
    @endif
    @include('contracts.partials.sample-modal', [
        'modalName' => 'contract-sample-template_body_html',
        'defaultType' => old('type_key', $template?->type_key ?? 'service'),
        'typeSelectId' => 'template_type_key',
    ])
    <textarea id="template_body_html" name="body_html" rows="14" class="flow-input mt-2 block w-full text-sm text-slate-900 dark:text-slate-100" required>{{ old('body_html', $template?->body_html) }}</textarea>
    <x-input-error :messages="$errors->get('body_html')" class="mt-1" />
</x-flow.form-card>
