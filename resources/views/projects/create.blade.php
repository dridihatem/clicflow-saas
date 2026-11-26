@php
    $initialCreateTab = 'details';
    if ($errors->hasAny(['final_price', 'negotiated_price', 'final_deadline'])) {
        $initialCreateTab = 'budget';
    } elseif ($errors->hasAny(['provider_id', 'team_user_ids', 'team_user_ids.*'])) {
        $initialCreateTab = 'people';
    } elseif ($errors->isNotEmpty()) {
        $initialCreateTab = 'details';
    }
@endphp
@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet" />
@endpush
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js" crossorigin="anonymous"></script>
@endpush

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ __('New project') }}</h2>
    </x-slot>

    <div
        class="py-10"
        x-data='{
            tab: @json($initialCreateTab),
            descriptionBooted: false,
            bootSummernote(attempt = 0) {
                this.$nextTick(() => {
                    if (this.descriptionBooted) {
                        return;
                    }
                    const jq = window.jQuery;
                    if (!jq || !jq.fn.summernote || !jq("#description").length) {
                        if (attempt < 40) {
                            window.setTimeout(() => this.bootSummernote(attempt + 1), 50);
                        }
                        return;
                    }
                    if (jq("#description").next(".note-editor").length || jq("#description").data("summernote")) {
                        this.descriptionBooted = true;
                        return;
                    }
                    jq("#description").summernote({
                        height: 280,
                        dialogsInBody: true,
                        placeholder: @json(__('Summary, needs, and internal context — cahier des charges can be attached as a file below.')),
                    });
                    this.descriptionBooted = true;
                });
            },
            syncDescription() {
                const jq = window.jQuery;
                if (jq && jq("#description").length && jq("#description").data("summernote")) {
                    jq("#description").val(jq("#description").summernote("code"));
                }
            },
        }'
        x-init="$watch('tab', t => { if (t === 'details') bootSummernote() }); if (tab === 'details') { bootSummernote() }"
    >
        <div class="mx-auto w-full max-w-12xl space-y-6 sm:px-6 lg:px-8">            <div
                class="overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-sm ring-1 ring-slate-900/[0.03] dark:border-slate-700/70 dark:bg-slate-900/50 dark:ring-white/[0.04]"
            >
                <div class="relative overflow-hidden border-b border-slate-200/80 px-5 py-5 sm:px-7 sm:py-6 dark:border-slate-700/70">
                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-cyan-50 dark:from-indigo-950/40 dark:via-slate-900 dark:to-slate-900" aria-hidden="true"></div>
                    <div class="pointer-events-none absolute -end-10 -top-16 h-40 w-40 rounded-full bg-indigo-400/20 blur-3xl dark:bg-indigo-500/10" aria-hidden="true"></div>
                    <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                        <div class="min-w-0 max-w-xl">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-300">{{ __('Projects') }}</p>
                            <h3 class="mt-1 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('New project') }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                {{ __('Fill in the project details below. Only the title is required — everything else is optional.') }}
                            </p>
                        </div>

                        <nav
                            class="inline-flex w-full max-w-xl flex-col gap-1 rounded-2xl border border-white/70 bg-white/80 p-1.5 shadow-sm backdrop-blur sm:w-auto sm:flex-row dark:border-slate-600/50 dark:bg-slate-900/70"
                            aria-label="{{ __('Project sections') }}"
                        >
                            <button
                                type="button"
                                @click="tab = 'details'; bootSummernote();"
                                :class="tab === 'details'
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-600/25'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                                class="flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                            >
                                <i class="fa-regular fa-file-lines w-4 text-center opacity-90" aria-hidden="true"></i>
                                <span>{{ __('Project details') }}</span>
                            </button>
                            <button
                                type="button"
                                @click="tab = 'budget'"
                                :class="tab === 'budget'
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-600/25'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                                class="flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                            >
                                <i class="fa-solid fa-sack-dollar w-4 text-center opacity-90" aria-hidden="true"></i>
                                <span>{{ __('Budget & schedule') }}</span>
                            </button>
                            <button
                                type="button"
                                @click="tab = 'people'"
                                :class="tab === 'people'
                                    ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md shadow-indigo-600/25'
                                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'"
                                class="flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                            >
                                <i class="fa-solid fa-user-group w-4 text-center opacity-90" aria-hidden="true"></i>
                                <span>{{ __('People & organization') }}</span>
                            </button>
                        </nav>
                    </div>
                </div>

                <form
                    id="flowdesk-project-create-form"
                    method="POST"
                    action="{{ route('projects.store') }}"
                    enctype="multipart/form-data"
                    class="p-5 sm:p-7 lg:p-8"
                    @submit="syncDescription()"
                >
                    @csrf

                    <div x-show="tab === 'details'" class="space-y-6" x-cloak>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <x-input-label for="title" :value="__('Title')" />
                                <x-text-input id="title" name="title" type="text" class="mt-2 block w-full" value="{{ old('title') }}" required />
                                <x-input-error :messages="$errors->get('title')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="status" :value="__('Status')" />
                                <select id="status" name="status" class="flow-input-select mt-2 block w-full">
                                    @foreach (\App\Enums\ProjectStatus::cases() as $case)
                                        <option value="{{ $case->value }}" @selected(old('status', 'draft') === $case->value)>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="source" :value="__('Source')" />
                                <select id="source" name="source" class="flow-input-select mt-2 block w-full">
                                    @foreach (\App\Enums\ProjectSource::cases() as $case)
                                        <option value="{{ $case->value }}" @selected(old('source', 'internal') === $case->value)>{{ $case->label() }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Use “Manual / internal” unless this project came from a form, provider, or inquiry.') }}</p>
                                <x-input-error :messages="$errors->get('source')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-project-client-quick-add>
                                <div>
                                    <x-input-label for="client_id" :value="__('Client (prospect)')" />
                                    <select id="client_id" name="client_id" class="flow-input-select mt-2 block w-full">
                                        <option value="">{{ __('None') }}</option>
                                        @foreach ($clients as $client)
                                            <option
                                                value="{{ $client->id }}"
                                                @selected(old('client_id', request('client')) === $client->id)
                                                data-phone="{{ $client->phone }}"
                                                data-email="{{ $client->email }}"
                                            >{{ $client->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </x-project-client-quick-add>
                        </div>

                        <div>
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <x-input-label for="description" :value="__('Description')" />
                                <x-project-description-ai textarea-id="description" />
                            </div>
                            <textarea id="description" name="description" class="mt-2 block min-h-[120px] w-full">{{ old('description') }}</textarea>
                            <x-input-error :messages="$errors->get('description')" class="mt-2" />
                        </div>

                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-600/50 dark:bg-slate-800/40">
                            <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Cahier des charges (file)') }}</h4>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Upload a brief, SOW, signed quote, or full specification — optional.') }}</p>
                            <div class="mt-3">
                                <x-input-label for="attachment" :value="__('Attachment (optional)')" />
                                <input
                                    id="attachment"
                                    type="file"
                                    name="attachment"
                                    class="mt-2 block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-indigo-950/50 dark:file:text-indigo-200"
                                />
                                <div class="mt-3">
                                    <x-input-label for="attachment_category" :value="__('File category')" />
                                    <select id="attachment_category" name="attachment_category" class="flow-input-select mt-1 block w-full text-sm">
                                        @foreach (\App\Enums\ProjectFileCategory::cases() as $cat)
                                            <option value="{{ $cat->value }}" @selected(old('attachment_category', 'document') === $cat->value)>{{ $cat->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('PDF, Office, images, or ZIP. Max :mb MB per file.', ['mb' => number_format(config('flowdesk.project_files.max_file_kb', 12288) / 1024, 0)]) }}
                                </p>
                                <x-input-error :messages="$errors->get('attachment')" class="mt-2" />
                                <x-input-error :messages="$errors->get('attachment_category')" class="mt-2" />
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'budget'" class="space-y-6" x-cloak>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Internal amounts and the target end date (optional).') }}</p>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="final_price" :value="__('Final price (:cur)', ['cur' => auth()->user()->company?->default_currency ?? flowdesk_default_currency()])" />
                                <x-text-input id="final_price" name="final_price" type="text" inputmode="decimal" class="flowdesk-amount mt-2 block w-full" value="{{ old('final_price') }}" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Company-only; amount in your workspace default currency.') }}</p>
                                <x-input-error :messages="$errors->get('final_price')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="negotiated_price" :value="__('Negotiated price (:cur)', ['cur' => auth()->user()->company?->default_currency ?? flowdesk_default_currency()])" />
                                <x-text-input id="negotiated_price" name="negotiated_price" type="text" inputmode="decimal" class="flowdesk-amount mt-2 block w-full" value="{{ old('negotiated_price') }}" />
                                <x-input-error :messages="$errors->get('negotiated_price')" class="mt-2" />
                            </div>
                        </div>
                        <div class="max-w-sm">
                            <x-input-label for="final_deadline" :value="__('Final deadline')" />
                            <input id="final_deadline" type="date" name="final_deadline" value="{{ old('final_deadline') }}" class="flow-input-select mt-2 block w-full" />
                            <x-input-error :messages="$errors->get('final_deadline')" class="mt-2" />
                        </div>
                    </div>

                    <div x-show="tab === 'people'" class="space-y-6" x-cloak>
                        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Link a provider for commissions, and assign the internal team.') }}</p>
                        <div>
                            <x-input-label for="provider_id" :value="__('Provider (optional)')" />
                            <select id="provider_id" name="provider_id" class="flow-input-select mt-2 block w-full">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->id }}" @selected(old('provider_id') === $provider->id)>{{ $provider->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Link a business provider when commissions or partner workflows apply.') }}</p>
                        </div>
                        <fieldset class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-600/50 dark:bg-slate-800/40">
                            <legend class="px-1 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ __('Assign team') }}</legend>
                            <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ __('Select workspace members responsible for this project.') }}</p>
                            <div class="max-h-52 space-y-2 overflow-y-auto pe-1">
                                @forelse ($teamUsers as $user)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl px-2 py-2 hover:bg-white/90 dark:hover:bg-slate-800/70">
                                        <input
                                            type="checkbox"
                                            name="team_user_ids[]"
                                            value="{{ $user->id }}"
                                            class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                                            @checked(in_array((int) $user->id, array_map('intval', (array) old('team_user_ids', [])), true))
                                        />
                                        <span class="text-sm text-slate-800 dark:text-slate-200">
                                            {{ $user->name }}
                                            <span class="text-slate-500">({{ $user->email }})</span>
                                            @if ($user->skills() !== [])
                                                <span class="mt-0.5 block text-[11px] text-cyan-700 dark:text-cyan-300">{{ $user->skillsLabel() }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @empty
                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No team members yet.') }}</p>
                                @endforelse
                            </div>
                            <x-input-error :messages="$errors->get('team_user_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('team_user_ids.*')" class="mt-2" />
                        </fieldset>
                    </div>

                    <div class="mt-8 flex flex-col-reverse gap-3 border-t border-slate-200/80 pt-6 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700/60">
                        <a
                            href="{{ route('projects.index') }}"
                            class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >{{ __('Cancel') }}</a>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <button
                                type="button"
                                x-show="tab !== 'details'"
                                x-cloak
                                @click="tab = tab === 'people' ? 'budget' : 'details'; if (tab === 'details') bootSummernote()"
                                class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                            >{{ __('Previous step') }}</button>
                            <button
                                type="button"
                                x-show="tab !== 'people'"
                                x-cloak
                                @click="tab = tab === 'details' ? 'budget' : 'people'"
                                class="inline-flex items-center justify-center rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-800 transition hover:bg-indigo-100 dark:border-indigo-500/30 dark:bg-indigo-950/40 dark:text-indigo-100"
                            >{{ __('Next') }}</button>
                            <x-primary-button class="!rounded-xl !px-5 !py-2.5 !normal-case !tracking-normal" data-nova-voice-save>
                                {{ __('Create project') }}
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
