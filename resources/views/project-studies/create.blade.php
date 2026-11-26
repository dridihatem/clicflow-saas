@php
    $aiCreditCost = (int) ($aiCreditCost ?? ($flowdeskAiTaskCredits['project_study_draft'] ?? 100));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('New project study') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('project_study_create_intro') }}</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-12xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            <form
                method="post"
                action="{{ route('project-studies.store') }}"
                class="space-y-6"
                x-data="projectStudyCreateForm({{ \Illuminate\Support\Js::from([
                    'projects' => $projectOptions ?? [],
                    'aiUrl' => route('project-studies.ai.draft'),
                    'aiAvailable' => (bool) ($aiAvailable ?? false),
                    'errFallback' => __('project_study_ai_error'),
                    'promptRequired' => __('project_study_ai_prompt_required'),
                ]) }})"
            >
                @csrf

                @if ($aiAvailable ?? false)
                    <section class="rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50/90 via-white to-slate-50 p-6 shadow-sm dark:border-teal-900/40 dark:from-teal-950/40 dark:via-slate-900 dark:to-slate-950">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white shadow-sm">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            </span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('project_study_ai_heading') }}</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_ai_subtitle') }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-teal-100 px-2.5 py-1 text-[11px] font-semibold text-teal-800 dark:bg-teal-900/50 dark:text-teal-200">
                                {{ $aiCreditCost }} {{ __('credits') }}
                            </span>
                        </div>

                        <label for="project_study_ai_prompt" class="mt-5 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            {{ __('project_study_ai_prompt_label') }}
                        </label>
                        <x-ai-voice-wrap target-id="project_study_ai_prompt" submit-button-id="project_study_ai_generate_btn" class="mt-1.5">
                            <textarea
                                id="project_study_ai_prompt"
                                rows="5"
                                class="block w-full rounded-xl border border-slate-300 bg-white text-sm shadow-sm focus:border-teal-500 focus:ring-teal-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                placeholder="{{ __('project_study_ai_prompt_placeholder') }}"
                                x-model="aiPrompt"
                            ></textarea>
                        </x-ai-voice-wrap>

                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <button
                                type="button"
                                id="project_study_ai_generate_btn"
                                class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-600 disabled:opacity-50 dark:bg-teal-600 dark:hover:bg-teal-500"
                                x-on:click="generateDraft()"
                                x-bind:disabled="aiBusy"
                            >
                                <i class="fa-solid fa-wand-magic-sparkles text-xs" aria-hidden="true"></i>
                                <span x-show="!aiBusy">{{ __('project_study_ai_generate') }}</span>
                                <span x-show="aiBusy" x-cloak>{{ __('project_study_ai_generating') }}</span>
                            </button>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('AI-generated content — review before sharing with clients.') }}</p>
                        </div>
                        <p x-show="aiError" x-text="aiError" x-cloak class="mt-2 text-sm text-rose-600 dark:text-rose-400"></p>
                        <p x-show="budgetRationale" x-text="budgetRationale" x-cloak class="mt-2 text-xs text-teal-800 dark:text-teal-300"></p>
                    </section>
                @endif

                <section class="flow-panel space-y-6 p-6 sm:p-8">
                    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('project_study_section_identity') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_section_identity_help') }}</p>
                    </div>

                    <div>
                        <x-input-label for="title" :value="__('Title')" />
                        <x-text-input id="title" name="title" class="mt-1 block w-full" x-model="title" required maxlength="255" placeholder="{{ __('project_study_title_placeholder') }}" />
                        <x-input-error class="mt-2" :messages="$errors->get('title')" />
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="client_id" :value="__('Client')" />
                        <x-project-client-quick-add
                            select-id="client_id"
                            :intro="__('Saves immediately and adds to the list for this project study.')"
                        >
                            <select id="client_id" name="client_id" class="flow-input mt-1 block w-full">
                                <option value="">—</option>
                                @foreach ($clients as $client)
                                    <option value="{{ $client->id }}" @selected(old('client_id', $prefillClientId) === (string) $client->id)>{{ $client->name }}</option>
                                @endforeach
                            </select>
                        </x-project-client-quick-add>
                            <x-input-error class="mt-2" :messages="$errors->get('client_id')" />
                        </div>

                        <div>
                            <x-input-label for="project_id" :value="__('Project')" />
                            <select
                                id="project_id"
                                name="project_id"
                                class="flow-input mt-1 block w-full"
                                x-on:change="onProjectChange($event.target.value)"
                            >
                                <option value="">—</option>
                                @foreach ($projects as $project)
                                    <option value="{{ $project->id }}" @selected(old('project_id') === (string) $project->id)>{{ $project->title }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-slate-500">{{ __('project_study_project_help') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('project_id')" />
                        </div>
                    </div>
                </section>

                <section class="flow-panel space-y-6 p-6 sm:p-8">
                    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('project_study_create_financial') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_create_financial_help') }}</p>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="budget" :value="__('Budget')" />
                            <x-text-input id="budget" name="budget" type="number" step="0.01" min="0" class="mt-1 block w-full" x-model="budget" />
                            <p class="mt-1 text-xs text-slate-500">{{ __('project_study_budget_help') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('budget')" />
                        </div>
                        <div>
                            <x-input-label for="currency" :value="__('Currency')" />
                            <x-text-input id="currency" name="currency" class="mt-1 block w-full" x-model="currency" maxlength="3" />
                            <x-input-error class="mt-2" :messages="$errors->get('currency')" />
                        </div>
                    </div>
                </section>

                <section class="flow-panel space-y-4 p-6 sm:p-8">
                    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('project_study_brief') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_brief_help') }}</p>
                    </div>

                    <x-ai-voice-wrap target-id="brief" class="mt-1">
                        <textarea
                            id="brief"
                            name="brief"
                            rows="14"
                            class="flow-input block w-full font-mono text-sm leading-relaxed"
                            placeholder="{{ __('project_study_brief_template_placeholder') }}"
                            x-model="brief"
                        ></textarea>
                    </x-ai-voice-wrap>
                    <x-input-error class="mt-2" :messages="$errors->get('brief')" />
                </section>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('project-studies.index') }}">
                        <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">{{ __('Create') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function projectStudyCreateForm(config) {
                const projects = Array.isArray(config.projects) ? config.projects : [];
                const byId = Object.create(null);
                projects.forEach(function (p) { byId[p.id] = p; });

                return {
                    title: @json(old('title', '')),
                    brief: @json(old('brief', '')),
                    budget: @json(old('budget', '')),
                    currency: @json(old('currency', $currency)),
                    aiPrompt: '',
                    aiBusy: false,
                    aiError: null,
                    budgetRationale: '',
                    aiUrl: config.aiUrl,
                    aiAvailable: !!config.aiAvailable,
                    errFallback: config.errFallback || '',
                    promptRequired: config.promptRequired || '',
                    onProjectChange(projectId) {
                        const p = byId[projectId];
                        if (!p) return;
                        const titleEl = document.getElementById('title');
                        if (titleEl && !(titleEl.value || '').trim() && p.title) {
                            this.title = p.title;
                            titleEl.value = p.title;
                        }
                        const clientEl = document.getElementById('client_id');
                        if (clientEl && p.client_id && !(clientEl.value || '').trim()) {
                            clientEl.value = p.client_id;
                            clientEl.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                        if (p.budget && !(this.budget || '').toString().trim()) {
                            this.budget = p.budget;
                        }
                        if (p.description && !(this.aiPrompt || '').trim()) {
                            this.aiPrompt = p.description;
                        }
                    },
                    async generateDraft() {
                        this.aiError = null;
                        this.budgetRationale = '';
                        const promptEl = document.getElementById('project_study_ai_prompt');
                        const promptText = (promptEl && promptEl.value ? promptEl.value : this.aiPrompt || '').trim();
                        this.aiPrompt = promptText;
                        if (!promptText) {
                            this.aiError = this.promptRequired;
                            return;
                        }
                        const token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
                        if (!token) {
                            this.aiError = this.errFallback;
                            return;
                        }
                        this.aiBusy = true;
                        try {
                            const clientEl = document.getElementById('client_id');
                            let clientName = '';
                            if (clientEl && clientEl.selectedOptions && clientEl.selectedOptions[0]) {
                                clientName = (clientEl.selectedOptions[0].textContent || '').trim();
                                if (clientName === '—') clientName = '';
                            }
                            const projectEl = document.getElementById('project_id');
                            const res = await fetch(this.aiUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': token,
                                },
                                body: JSON.stringify({
                                    subject: promptText,
                                    title: this.title || '',
                                    client_name: clientName,
                                    currency: this.currency || '',
                                    project_id: projectEl ? (projectEl.value || null) : null,
                                }),
                            });
                            const data = await res.json().catch(function () { return {}; });
                            if (!res.ok) {
                                this.aiError = data.message || this.errFallback;
                                return;
                            }
                            if (data.title && !(this.title || '').trim()) {
                                this.title = data.title;
                            }
                            if (data.brief) {
                                this.brief = data.brief;
                            }
                            if (data.budget) {
                                this.budget = data.budget;
                            }
                            if (data.currency) {
                                this.currency = data.currency;
                            }
                            if (data.budget_rationale) {
                                this.budgetRationale = data.budget_rationale;
                            }
                        } catch (e) {
                            this.aiError = (e && e.message) ? e.message : this.errFallback;
                        } finally {
                            this.aiBusy = false;
                        }
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
