<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('data_import_upload', ['type' => $typeLabel]) }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('data_import_upload_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl space-y-6 sm:px-6 lg:px-8">
            @include('imports.partials.columns', [
                'columns' => $columns,
                'showDownload' => true,
                'type' => $type,
            ])

            <div class="flow-panel p-6 sm:p-8">
                <form method="POST" action="{{ route('import.preview', $type) }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div>
                        <x-input-label for="csv_file" :value="__('prospect_import_csv')" />
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" class="mt-2 block w-full text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:text-slate-200 dark:file:bg-indigo-950/50 dark:file:text-indigo-300" />
                        <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="import_input" :value="__('prospect_import_paste')" />
                        <textarea id="import_input" name="import_input" rows="12" class="mt-2 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="{{ __('data_import_paste_placeholder') }}">{{ old('import_input', $exampleCsv) }}</textarea>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('data_import_help_'.$type) }}</p>
                        <x-input-error :messages="$errors->get('import_input')" class="mt-2" />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-primary-button type="submit">{{ __('prospect_import_preview') }}</x-primary-button>
                        <a href="{{ route('import.example', $type) }}">
                            <x-secondary-button type="button" class="!normal-case">{{ __('data_import_download_example') }}</x-secondary-button>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
