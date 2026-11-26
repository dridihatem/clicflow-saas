@php
    $aiCreditCost = (int) ($creditCost ?? ($flowdeskAiTaskCredits['project_study'] ?? 200));
    $hasContent = collect([
        $study->executive_summary,
        $study->technical_study,
        $study->financial_study,
        $study->methodology,
        $study->risks,
        $study->conclusion,
    ])->contains(fn ($v) => filled($v));
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $study->title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ __('project_study_status_'.$study->status) }}
                    @if ($study->client)
                        · {{ $study->client->name }}
                    @endif
                    @if ($study->generated_at)
                        · AI {{ $study->generated_at->format('Y-m-d H:i') }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                @if ($study->client_id)
                    <form method="POST" action="{{ route('project-studies.proposal', $study) }}" class="inline">
                        @csrf
                        <input type="hidden" name="mode" value="manual" />
                        <button
                            type="submit"
                            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-slate-200/80 bg-white px-2.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-500/40 dark:hover:text-indigo-300"
                            title="{{ __('project_study_create_quote_help') }}"
                        >
                            <i class="fa-solid fa-file-invoice text-sm" aria-hidden="true"></i>
                            {{ __('project_study_create_quote_manual') }}
                        </button>
                    </form>
                    @if ($aiAvailable ?? false)
                        <form method="POST" action="{{ route('project-studies.proposal', $study) }}" class="inline">
                            @csrf
                            <input type="hidden" name="mode" value="ai" />
                            <button
                                type="submit"
                                class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-teal-200/80 bg-teal-50 px-2.5 text-xs font-semibold text-teal-800 shadow-sm transition hover:border-teal-300 hover:bg-teal-100 dark:border-teal-800/50 dark:bg-teal-950/40 dark:text-teal-200"
                                title="{{ __('project_study_create_quote_ai_help') }}"
                            >
                                <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
                                {{ __('project_study_create_quote_ai') }}
                            </button>
                        </form>
                    @endif
                @else
                    <span
                        class="inline-flex h-9 cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200/80 bg-slate-50 px-2.5 text-xs font-semibold text-slate-400 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-500"
                        title="{{ __('project_study_quote_needs_client') }}"
                    >
                        <i class="fa-solid fa-file-invoice text-sm" aria-hidden="true"></i>
                        {{ __('project_study_create_quote_manual') }}
                    </span>
                @endif
                <a
                    href="{{ route('project-studies.pdf', $study) }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                    title="{{ __('Export PDF') }}"
                    aria-label="{{ __('Export PDF') }}"
                >
                    <i class="fa-solid fa-file-pdf text-sm" aria-hidden="true"></i>
                </a>
                <a
                    href="{{ route('project-studies.edit', ['projectStudy' => $study, 'mode' => 'manual']) }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                    title="{{ __('Edit') }}"
                    aria-label="{{ __('Edit') }}"
                >
                    <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                </a>
                <form method="post" action="{{ route('project-studies.destroy', $study) }}" class="inline" onsubmit="return confirm(@js(__('Delete this project study?')))">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                        title="{{ __('Delete') }}"
                        aria-label="{{ __('Delete') }}"
                    >
                        <i class="fa-regular fa-trash-can text-sm" aria-hidden="true"></i>
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-12xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-6 rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900">{{ session('error') }}</div>
            @endif

            <div class="mb-8">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_edit_choose') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('project_study_edit_choose_help') }}</p>
            </div>

            <div class="mb-10 grid gap-4 sm:grid-cols-3">
                @if ($aiAvailable)
                    <a
                        href="{{ route('project-studies.edit', ['projectStudy' => $study, 'mode' => 'ai']) }}"
                        class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-teal-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-teal-600"
                    >
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-600 text-white">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_ai') }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_ai_help') }}</p>
                        <p class="mt-3 text-[11px] font-semibold text-teal-700 dark:text-teal-300">{{ $aiCreditCost }} {{ __('credits') }}</p>
                    </a>
                @endif

                <a
                    href="{{ route('project-studies.edit', ['projectStudy' => $study, 'mode' => 'manual']) }}"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-indigo-600"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white">
                        <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_edit') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_edit_help') }}</p>
                </a>

                <a
                    href="{{ route('project-studies.edit', ['projectStudy' => $study, 'mode' => 'scratch']) }}"
                    class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-400 hover:shadow-md dark:border-slate-700 dark:bg-slate-900 dark:hover:border-slate-500"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-700 text-white dark:bg-slate-600">
                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_study_card_manual') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_study_card_manual_help') }}</p>
                </a>
            </div>

            @if ($hasContent)
                <div class="space-y-4">
                    @foreach ([
                        'executive_summary' => __('project_study_section_executive'),
                        'technical_study' => __('project_study_section_technical'),
                        'financial_study' => __('project_study_section_financial'),
                        'methodology' => __('project_study_section_methodology'),
                        'risks' => __('project_study_section_risks'),
                        'conclusion' => __('project_study_section_conclusion'),
                    ] as $field => $label)
                        @if (filled($study->{$field}))
                            <div class="flow-panel p-6">
                                <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</h3>
                                <div class="mt-3 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-100">{{ $study->{$field} }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            @else
                <div class="flow-panel p-8 text-center">
                    <p class="text-sm text-slate-500">{{ __('project_study_empty_body') }}</p>
                </div>
            @endif

            @if (! $study->client_id)
                <div class="mt-8 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ __('project_study_quote_needs_client') }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
