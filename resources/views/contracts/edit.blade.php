@include('contracts.partials.summernote', [
    'textareaId' => 'contract_body_html',
    'hintsId' => 'contract-merge-hints',
    'typeSelectId' => 'contract_type_key',
    'defaultType' => $contract->type_key ?? 'service',
    'height' => 420,
])

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit contract') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-12xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('contracts.update', $contract) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div class="grid gap-5 lg:grid-cols-2">
                    <x-flow.form-card tone="indigo" icon="fa-solid fa-user" :title="__('contract_form_parties')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="contract_client_id" :value="__('Client')" />
                                <select id="contract_client_id" name="client_id" class="flow-input mt-1 block w-full" required>
                                    @foreach ($clients as $client)
                                        <option value="{{ $client->id }}" @selected(old('client_id', $contract->client_id) === (string) $client->id)>{{ $client->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="contract_title" :value="__('Title')" />
                                <x-text-input id="contract_title" name="title" type="text" class="mt-1 block w-full" :value="old('title', $contract->title)" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-1" />
                            </div>
                        </div>
                    </x-flow.form-card>

                    <x-flow.form-card tone="violet" icon="fa-solid fa-file-signature" :title="__('contract_form_period')">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="contract_type_key" :value="__('contract_type_label')" />
                                <select id="contract_type_key" name="type_key" class="flow-input mt-1 block w-full" required>
                                    @foreach (['service', 'nda', 'retainer', 'consulting', 'custom'] as $key)
                                        <option value="{{ $key }}" @selected(old('type_key', $contract->type_key) === $key)>{{ __('contract_type_'.$key) }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('type_key')" class="mt-1" />
                            </div>
                            <div>
                                <x-input-label for="contract_starts_on" :value="__('Starts on')" />
                                <x-text-input id="contract_starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="old('starts_on', $contract->starts_on?->format('Y-m-d'))" />
                            </div>
                            <div>
                                <x-input-label for="contract_ends_on" :value="__('Ends on')" />
                                <x-text-input id="contract_ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="old('ends_on', $contract->ends_on?->format('Y-m-d'))" />
                            </div>
                        </div>
                    </x-flow.form-card>
                </div>

                <x-flow.form-card tone="amber" icon="fa-regular fa-note-sticky" :title="__('Notes')">
                    <x-input-label for="contract_notes" :value="__('Notes')" class="sr-only" />
                    <textarea id="contract_notes" name="notes" rows="2" class="flow-input block w-full">{{ old('notes', $contract->notes) }}</textarea>
                </x-flow.form-card>

                <x-flow.form-card tone="emerald" icon="fa-solid fa-pen-nib" :title="__('contract_body_label')" :hint="__('contract_merge_tags_help')">
                    @include('contracts.partials.ai-generate', [
                        'textareaId' => 'contract_body_html',
                        'titleInputId' => 'contract_title',
                        'typeSelectId' => 'contract_type_key',
                        'clientSelectId' => 'contract_client_id',
                    ])
                    @if (! empty($mergeHints))
                        <div class="mt-2 flex flex-wrap gap-2" id="contract-merge-hints">
                            @foreach ($mergeHints as $hint)
                                <button type="button" class="rounded-md border border-emerald-200/80 bg-white px-2 py-1 text-xs font-medium text-slate-700 hover:bg-emerald-50 dark:border-emerald-900/40 dark:bg-slate-800 dark:text-slate-200" data-tag="{{ '{'.'{'.$hint['key'].'}'.'}' }}">
                                    {{ '{'.'{'.$hint['key'].'}'.'}' }}
                                </button>
                            @endforeach
                        </div>
                    @endif
                    @include('contracts.partials.sample-modal', [
                        'modalName' => 'contract-sample-contract_body_html',
                        'defaultType' => old('type_key', $contract->type_key ?? 'service'),
                        'typeSelectId' => 'contract_type_key',
                    ])
                    <textarea id="contract_body_html" name="body_html" rows="14" class="flow-input mt-2 block w-full text-sm text-slate-900 dark:text-slate-100" required>{{ old('body_html', $contract->body_html) }}</textarea>
                    <x-input-error :messages="$errors->get('body_html')" class="mt-1" />
                </x-flow.form-card>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('contracts.show', $contract) }}"><x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button></a>
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
