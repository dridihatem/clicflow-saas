@php
    $budgetDisplay = $study->budget_minor !== null
        ? flowdesk_major_amount_for_input((int) $study->budget_minor, (string) $study->currency)
        : '';
    $aiCreditCost = (int) ($creditCost ?? ($flowdeskAiTaskCredits['project_study'] ?? 200));
    $defaultMode = old('_mode', request('mode', 'manual'));
    if (! in_array($defaultMode, ['ai', 'manual', 'scratch'], true)) {
        $defaultMode = 'manual';
    }
    if ($defaultMode === 'ai' && ! ($aiAvailable ?? false)) {
        $defaultMode = 'manual';
    }
    $sectionFields = [
        'executive_summary' => __('project_study_section_executive'),
        'technical_study' => __('project_study_section_technical'),
        'financial_study' => __('project_study_section_financial'),
        'methodology' => __('project_study_section_methodology'),
        'risks' => __('project_study_section_risks'),
        'conclusion' => __('project_study_section_conclusion'),
    ];
    $scratchTemplate = implode("\n\n", [
        '1) '.__('project_study_section_executive'),
        '',
        '2) '.__('project_study_section_technical'),
        '',
        '3) '.__('project_study_section_financial'),
        '',
        '4) '.__('project_study_section_methodology'),
        '',
        '5) '.__('project_study_section_risks'),
        '',
        '6) '.__('project_study_section_conclusion'),
        '',
    ]);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit project study') }}</h2>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ $study->title }}</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div
            class="mx-auto max-w-12xl space-y-6 sm:px-6 lg:px-8"
            x-data="{ mode: @js($defaultMode) }"
        >
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
            @endif

            <div>
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_edit_choose') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('project_study_edit_choose_help') }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                @if ($aiAvailable ?? false)
                    <button
                        type="button"
                        class="group rounded-2xl border p-5 text-start shadow-sm transition"
                        :class="mode === 'ai'
                            ? 'border-teal-400 bg-teal-50/80 ring-2 ring-teal-500/30 dark:border-teal-500 dark:bg-teal-950/40'
                            : 'border-slate-200 bg-white hover:border-teal-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-teal-600'"
                        x-on:click="mode = 'ai'"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-600 text-white">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_ai') }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_ai_help') }}</p>
                        <p class="mt-3 text-[11px] font-semibold text-teal-700 dark:text-teal-300">{{ $aiCreditCost }} {{ __('credits') }}</p>
                    </button>
                @endif

                <button
                    type="button"
                    class="group rounded-2xl border p-5 text-start shadow-sm transition"
                    :class="mode === 'manual'
                        ? 'border-indigo-400 bg-indigo-50/80 ring-2 ring-indigo-500/30 dark:border-indigo-500 dark:bg-indigo-950/40'
                        : 'border-slate-200 bg-white hover:border-indigo-300 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-600'"
                    x-on:click="mode = 'manual'"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_edit') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_edit_help') }}</p>
                </button>

                <button
                    type="button"
                    class="group rounded-2xl border p-5 text-start shadow-sm transition"
                    :class="mode === 'scratch'
                        ? 'border-slate-500 bg-slate-100 ring-2 ring-slate-400/40 dark:border-slate-400 dark:bg-slate-800'
                        : 'border-slate-200 bg-white hover:border-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500'"
                    x-on:click="mode = 'scratch'"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-700 text-white dark:bg-slate-600">
                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_manual') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_manual_help') }}</p>
                </button>
            </div>

            {{-- AI card panel --}}
            <div x-show="mode === 'ai'" x-cloak class="space-y-4">
                <section class="rounded-2xl border border-teal-200/80 bg-gradient-to-br from-teal-50/90 via-white to-slate-50 p-6 shadow-sm dark:border-teal-900/40 dark:from-teal-950/40 dark:via-slate-900 dark:to-slate-950">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_ai') }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_ai_full_help') }}</p>
                        </div>
                    </div>
                    <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">{{ __('project_study_ai_uses_brief') }}</p>
                    @if (filled($study->executive_summary))
                        <div class="mt-3 max-h-40 overflow-y-auto rounded-xl border border-teal-200/60 bg-white/80 p-3 text-xs whitespace-pre-wrap text-slate-700 dark:border-teal-800 dark:bg-slate-900/60 dark:text-slate-300">{{ $study->executive_summary }}</div>
                    @else
                        <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">{{ __('project_study_ai_needs_brief') }}</p>
                    @endif
                    <form method="post" action="{{ route('project-studies.generate', $study) }}" class="mt-5">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-teal-600 disabled:opacity-50 dark:bg-teal-600"
                            @disabled(! filled($study->executive_summary))
                        >
                            <i class="fa-solid fa-wand-magic-sparkles text-xs" aria-hidden="true"></i>
                            {{ __('Generate with AI') }}
                            <span class="opacity-80">({{ $aiCreditCost }} {{ __('credits') }})</span>
                        </button>
                    </form>
                    <p class="mt-3 text-xs text-slate-500">
                        <button type="button" class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400" x-on:click="mode = 'manual'">{{ __('project_study_switch_to_edit_brief') }}</button>
                    </p>
                </section>
            </div>

            {{-- Manual edit form --}}
            <form
                method="post"
                action="{{ route('project-studies.update', $study) }}"
                class="space-y-6"
                x-show="mode === 'manual'"
                x-cloak
            >
                @csrf
                @method('PUT')

                @include('project-studies.partials.edit-identity-fields', [
                    'study' => $study,
                    'clients' => $clients,
                    'projects' => $projects,
                    'budgetDisplay' => $budgetDisplay,
                    'idPrefix' => 'manual_',
                ])

                <section class="flow-panel space-y-5 p-6 sm:p-8">
                    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('project_study_card_edit') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('project_study_card_edit_panel_help') }}</p>
                    </div>

                    @foreach ($sectionFields as $field => $label)
                        <div>
                            <x-input-label :for="'manual_'.$field" :value="$label" />
                            <x-ai-voice-wrap :target-id="'manual_'.$field" class="mt-1">
                                <textarea id="manual_{{ $field }}" name="{{ $field }}" rows="8" class="flow-input block w-full">{{ old($field, $study->{$field}) }}</textarea>
                            </x-ai-voice-wrap>
                        </div>
                    @endforeach
                </section>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('project-studies.show', $study) }}">
                        <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </form>

            {{-- Write manually form --}}
            <form
                method="post"
                action="{{ route('project-studies.update', $study) }}"
                class="space-y-6"
                x-show="mode === 'scratch'"
                x-cloak
            >
                @csrf
                @method('PUT')

                @include('project-studies.partials.edit-identity-fields', [
                    'study' => $study,
                    'clients' => $clients,
                    'projects' => $projects,
                    'budgetDisplay' => $budgetDisplay,
                    'idPrefix' => 'scratch_',
                ])

                <section class="flow-panel space-y-5 p-6 sm:p-8">
                    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('project_study_card_manual') }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ __('project_study_card_manual_panel_help') }}</p>
                    </div>

                    <div>
                        <x-input-label for="scratch_executive_summary" :value="__('project_study_section_executive')" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('project_study_scratch_hint') }}</p>
                        <x-ai-voice-wrap target-id="scratch_executive_summary" class="mt-1">
                            <textarea
                                id="scratch_executive_summary"
                                name="executive_summary"
                                rows="16"
                                class="flow-input block w-full font-mono text-sm leading-relaxed"
                                placeholder="{{ $scratchTemplate }}"
                            >{{ old('executive_summary', filled($study->executive_summary) ? $study->executive_summary : '') }}</textarea>
                        </x-ai-voice-wrap>
                    </div>

                    @foreach (['technical_study', 'financial_study', 'methodology', 'risks', 'conclusion'] as $field)
                        <input type="hidden" name="{{ $field }}" value="{{ old($field, $study->{$field}) }}">
                    @endforeach
                </section>

                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a href="{{ route('project-studies.show', $study) }}">
                        <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                    </a>
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
