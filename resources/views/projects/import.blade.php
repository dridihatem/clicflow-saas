<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('project_import_page_title') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('project_import_page_intro') }}</p>
            </div>
        </div>
    </x-slot>

    @php
        $activeType = in_array($type ?? 'excel', ['excel', 'pdf'], true) ? $type : 'excel';
    @endphp

    <div class="py-10" x-data="{ importType: @js($activeType) }">
        <div class="mx-auto max-w-12xl space-y-6 sm:px-6 lg:px-8">
            <div class="grid gap-4 sm:grid-cols-2">
                <button
                    type="button"
                    @click="importType = 'excel'"
                    :class="importType === 'excel' ? 'border-indigo-400 ring-2 ring-indigo-500/30 dark:border-indigo-500' : 'border-slate-200 dark:border-slate-700'"
                    class="rounded-2xl border bg-white p-5 text-start shadow-sm transition hover:border-indigo-300 dark:bg-slate-900/50 dark:hover:border-indigo-600"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-emerald-600 text-white">
                        <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_import_excel_card') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_spreadsheet_import_intro') }}</p>
                </button>
                <button
                    type="button"
                    @click="importType = 'pdf'"
                    :class="importType === 'pdf' ? 'border-indigo-400 ring-2 ring-indigo-500/30 dark:border-indigo-500' : 'border-slate-200 dark:border-slate-700'"
                    class="rounded-2xl border bg-white p-5 text-start shadow-sm transition hover:border-indigo-300 dark:bg-slate-900/50 dark:hover:border-indigo-600"
                >
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-rose-600 text-white">
                        <i class="fa-solid fa-file-pdf" aria-hidden="true"></i>
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ __('project_import_pdf_card') }}</p>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('project_pdf_import_intro') }}</p>
                    @if ($aiAvailable ?? false)
                        <p class="mt-2 text-[11px] font-semibold text-rose-700 dark:text-rose-300">{{ (int) ($pdfCreditCost ?? 150) }} {{ __('credits') }}</p>
                    @endif
                </button>
            </div>

            <div x-show="importType === 'excel'" x-cloak class="space-y-4">
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('project_spreadsheet_tabs_heading') }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('project_spreadsheet_tabs_help') }}</p>
                        </div>
                        <a
                            href="{{ route('projects.import.example') }}"
                            class="inline-flex items-center gap-2 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-500/40 dark:hover:text-indigo-300"
                        >
                            <i class="fa-solid fa-download text-[10px]" aria-hidden="true"></i>
                            {{ __('project_spreadsheet_download_example') }}
                        </a>
                    </div>

                    <div class="mt-4 grid gap-4 lg:grid-cols-2">
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('project_spreadsheet_project_details') }}</p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($projectColumns ?? [] as $column)
                                    <li class="text-xs text-slate-600 dark:text-slate-300">
                                        <code class="rounded bg-white px-1 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-slate-800 dark:text-indigo-300">{{ $column['key'] }}</code>
                                        — {{ $column['description'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">{{ __('project_spreadsheet_task_table') }}</p>
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($taskColumns ?? [] as $column)
                                    <li class="text-xs text-slate-600 dark:text-slate-300">
                                        <code class="rounded bg-white px-1 py-0.5 text-[11px] font-semibold text-indigo-700 dark:bg-slate-800 dark:text-indigo-300">{{ $column['key'] }}</code>
                                        @if ($column['required'])
                                            <span class="font-semibold text-amber-700 dark:text-amber-300">*</span>
                                        @endif
                                        — {{ $column['description'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flow-panel p-6 sm:p-8">
                <form method="POST" action="{{ route('projects.import.store') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <input type="hidden" name="import_type" :value="importType" />

                    <div x-show="importType === 'excel'" x-cloak>
                        <x-input-label for="spreadsheet" :value="__('project_import_excel_file_label')" />
                        <input id="spreadsheet" name="spreadsheet" type="file" accept=".csv,.txt,.xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" class="mt-2 block w-full text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:text-slate-200 dark:file:bg-indigo-950/50 dark:file:text-indigo-300" />
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('project_spreadsheet_import_help') }}</p>
                        <x-input-error :messages="$errors->get('spreadsheet')" class="mt-2" />
                    </div>

                    <div x-show="importType === 'pdf'" x-cloak>
                        @if (! ($aiAvailable ?? false))
                            <div class="mb-4 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-950 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-100">
                                {{ __('project_pdf_import_ai_unavailable') }}
                            </div>
                        @endif
                        <x-input-label for="pdf" :value="__('project_import_pdf_file_label')" />
                        <input id="pdf" name="pdf" type="file" accept=".pdf,application/pdf" class="mt-2 block w-full text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-rose-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-rose-700 dark:text-slate-200 dark:file:bg-rose-950/50 dark:file:text-rose-300" />
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('project_pdf_import_help') }}</p>
                        <x-input-error :messages="$errors->get('pdf')" class="mt-2" />
                        <x-input-error :messages="$errors->get('import_type')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <x-primary-button type="submit" x-bind:disabled="importType === 'pdf' && !@js((bool) ($aiAvailable ?? false))">
                            <span x-show="importType === 'excel'">{{ __('project_import_excel_submit') }}</span>
                            <span x-show="importType === 'pdf'" x-cloak>{{ __('project_import_pdf_submit') }}</span>
                        </x-primary-button>
                        <a href="{{ route('projects.import.example') }}" x-show="importType === 'excel'" x-cloak>
                            <x-secondary-button type="button" class="!normal-case">{{ __('project_spreadsheet_download_example') }}</x-secondary-button>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
