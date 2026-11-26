@include('contracts.partials.summernote', [
    'textareaId' => 'contract_body_html',
    'hintsId' => null,
    'defaultType' => 'service',
    'height' => 360,
])

@php
    $templateBodies = $templates->mapWithKeys(function ($t) {
        return [
            $t->id => [
                'body' => $t->body_html,
                'title' => $t->name,
                'type' => $t->type_key,
            ],
        ];
    });
@endphp

@push('scripts')
    <script>
        (function () {
            const previewUrl = @json(route('contracts.preview-template'));
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const templateBodies = @json($templateBodies);

            const select = document.getElementById('contract_template_id');
            const clientSelect = document.getElementById('contract_client_id');
            const title = document.getElementById('contract_title');
            const previewBox = document.getElementById('contract-model-preview');
            const previewEmpty = document.getElementById('contract-model-preview-empty');
            const startsOn = document.getElementById('contract_starts_on');
            const endsOn = document.getElementById('contract_ends_on');

            function currentBody() {
                const ta = document.getElementById('contract_body_html');
                if (!ta) return '';
                if (typeof jQuery !== 'undefined' && jQuery(ta).next('.note-editor').length) {
                    return jQuery(ta).summernote('code');
                }
                return ta.value || '';
            }

            function setBody(html) {
                const ta = document.getElementById('contract_body_html');
                if (!ta) return;
                if (typeof jQuery !== 'undefined' && jQuery(ta).next('.note-editor').length) {
                    jQuery(ta).summernote('code', html);
                } else {
                    ta.value = html;
                }
            }

            function refreshPreview() {
                if (!previewBox) return;
                const templateId = select?.value || '';
                const clientId = clientSelect?.value || '';
                const body = currentBody();

                if (!templateId && !body.trim()) {
                    previewBox.classList.add('hidden');
                    previewBox.innerHTML = '';
                    previewEmpty?.classList.remove('hidden');
                    return;
                }

                previewEmpty?.classList.add('hidden');
                previewBox.classList.remove('hidden');
                previewBox.innerHTML = '<p class="text-sm text-slate-500">' + @json(__('Loading…')) + '</p>';

                const payload = {
                    contract_template_id: templateId || null,
                    client_id: clientId || null,
                    title: title?.value || '',
                    body_html: body,
                    starts_on: startsOn?.value || null,
                    ends_on: endsOn?.value || null,
                };

                fetch(previewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrf,
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(payload),
                })
                    .then(function (r) {
                        if (!r.ok) throw new Error('preview');
                        return r.json();
                    })
                    .then(function (data) {
                        previewBox.innerHTML = data.html || '<p class="text-sm text-slate-500">—</p>';
                    })
                    .catch(function () {
                        previewBox.innerHTML = '<p class="text-sm text-rose-600">' + @json(__('Could not load sample text. Try again.')) + '</p>';
                    });
            }

            window.flowdeskRefreshContractPreview = refreshPreview;

            select?.addEventListener('change', function () {
                const id = select.value;
                const meta = id ? templateBodies[id] : null;
                if (meta) {
                    if (title && !title.value.trim()) {
                        title.value = meta.title || '';
                    }
                    setBody(meta.body || '');
                }
                refreshPreview();
            });

            clientSelect?.addEventListener('change', refreshPreview);
            title?.addEventListener('change', refreshPreview);
            startsOn?.addEventListener('change', refreshPreview);
            endsOn?.addEventListener('change', refreshPreview);

            document.getElementById('contract-refresh-preview')?.addEventListener('click', refreshPreview);

            setTimeout(refreshPreview, 400);
        })();
    </script>
@endpush

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('New contract') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('contract_create_intro') }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-12xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('contracts.store') }}" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,380px)]" id="contract-create-form">
                @csrf
                <div class="space-y-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-flow.form-card tone="indigo" icon="fa-solid fa-user" :title="__('contract_form_parties')">
                            <div class="space-y-3">
                                <div>
                                    <x-input-label for="contract_client_id" :value="__('Client')" />
                                    <select id="contract_client_id" name="client_id" class="flow-input mt-1 block w-full" required>
                                        <option value="">{{ __('Select client') }}</option>
                                        @foreach ($clients as $client)
                                            <option value="{{ $client->id }}" @selected(old('client_id', $prefillClientId) === (string) $client->id)>{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                                </div>
                                <div>
                                    <x-input-label for="contract_title" :value="__('Title')" />
                                    <x-text-input id="contract_title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" />
                                    <x-input-error :messages="$errors->get('title')" class="mt-1" />
                                </div>
                            </div>
                        </x-flow.form-card>

                        <x-flow.form-card tone="violet" icon="fa-solid fa-layer-group" :title="__('contract_form_period')">
                            <div class="space-y-3">
                                <div>
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <x-input-label for="contract_template_id" :value="__('Company contract model')" />
                                        <a href="{{ route('contract-templates.index') }}" class="text-xs font-medium text-indigo-700 hover:underline dark:text-indigo-300">{{ __('Manage templates') }}</a>
                                    </div>
                                    <select id="contract_template_id" name="contract_template_id" class="flow-input mt-1 block w-full">
                                        <option value="">{{ __('Blank / custom') }}</option>
                                        @foreach ($templates as $template)
                                            <option
                                                value="{{ $template->id }}"
                                                data-title="{{ $template->name }}"
                                                data-type="{{ $template->type_key }}"
                                                @selected(old('contract_template_id') === (string) $template->id)
                                            >{{ $template->name }} ({{ __('contract_type_'.$template->type_key) }})</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('contract_model_select_help') }}</p>
                                    <x-input-error :messages="$errors->get('contract_template_id')" class="mt-1" />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="contract_starts_on" :value="__('Starts on')" />
                                        <x-text-input id="contract_starts_on" name="starts_on" type="date" class="mt-1 block w-full" :value="old('starts_on')" />
                                        <x-input-error :messages="$errors->get('starts_on')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="contract_ends_on" :value="__('Ends on')" />
                                        <x-text-input id="contract_ends_on" name="ends_on" type="date" class="mt-1 block w-full" :value="old('ends_on')" />
                                        <x-input-error :messages="$errors->get('ends_on')" class="mt-1" />
                                    </div>
                                </div>
                            </div>
                        </x-flow.form-card>
                    </div>

                    <x-flow.form-card tone="amber" icon="fa-regular fa-note-sticky" :title="__('Notes')">
                        <textarea id="contract_notes" name="notes" rows="2" class="flow-input block w-full">{{ old('notes') }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-1" />
                    </x-flow.form-card>

                    <x-flow.form-card tone="emerald" icon="fa-solid fa-pen-nib" :title="__('contract_body_label')">
                        @include('contracts.partials.ai-generate', [
                            'textareaId' => 'contract_body_html',
                            'titleInputId' => 'contract_title',
                            'typeSelectId' => 'contract_type_key',
                            'clientSelectId' => 'contract_client_id',
                            'templateSelectId' => 'contract_template_id',
                        ])
                        @include('contracts.partials.sample-modal', [
                            'modalName' => 'contract-sample-contract_body_html',
                            'defaultType' => 'service',
                        ])
                        <textarea id="contract_body_html" name="body_html" rows="10" class="flow-input mt-2 block w-full text-sm text-slate-900 dark:text-slate-100" placeholder="{{ __('contract_body_optional_from_template') }}">{{ old('body_html') }}</textarea>
                        <x-input-error :messages="$errors->get('body_html')" class="mt-1" />
                    </x-flow.form-card>

                    <div class="flex justify-end gap-2">
                        <a href="{{ route('contracts.index') }}"><x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button></a>
                        <x-primary-button type="submit">{{ __('Create contract') }}</x-primary-button>
                    </div>
                </div>

                <x-flow.form-card class="lg:sticky lg:top-6 lg:self-start" tone="sky" icon="fa-regular fa-eye" :title="__('Contract preview')" :hint="__('contract_create_preview_help')">
                    <div class="mb-3 flex justify-end">
                        <button type="button" id="contract-refresh-preview" class="text-xs font-medium text-sky-800 hover:underline dark:text-sky-300">
                            {{ __('Refresh') }}
                        </button>
                    </div>
                    <div id="contract-model-preview-empty" class="rounded-lg border border-dashed border-sky-300/80 bg-white/70 px-4 py-10 text-center text-sm text-slate-500 dark:border-sky-800/50 dark:bg-slate-900/40 dark:text-slate-400">
                        {{ __('contract_preview_empty') }}
                    </div>
                    <div id="contract-model-preview" class="prose prose-sm max-h-[70vh] max-w-none overflow-y-auto rounded-xl border border-sky-200/70 bg-white p-4 dark:border-sky-900/40 dark:bg-slate-900 dark:prose-invert flow-rich-text hidden"></div>
                </x-flow.form-card>
            </form>
        </div>
    </div>
</x-app-layout>
