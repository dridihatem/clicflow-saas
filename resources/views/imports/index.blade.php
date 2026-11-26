<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Data import') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('data_import_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-12xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($types as $type)
                    <div class="flow-panel flex flex-col p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $type['label'] }}</p>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $type['help'] }}</p>
                            </div>
                            <a
                                href="{{ route('import.example', $type['key']) }}"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                title="{{ __('data_import_download_example') }}"
                                aria-label="{{ __('data_import_download_example') }}"
                            >
                                <i class="fa-solid fa-file-csv text-sm" aria-hidden="true"></i>
                            </a>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-1.5">
                            @foreach ($type['columns'] as $column)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                                    {{ $column['key'] }}@if ($column['required'])*@endif
                                </span>
                            @endforeach
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <a href="{{ route('import.preview.form', $type['key']) }}">
                                <x-primary-button type="button" class="!normal-case">{{ __('data_import_start') }}</x-primary-button>
                            </a>
                            <a href="{{ route('import.example', $type['key']) }}">
                                <x-secondary-button type="button" class="!normal-case">{{ __('data_import_download_example') }}</x-secondary-button>
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
