<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('prospect_import') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prospect_import_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full sm:px-6 lg:px-8">
            <div class="flow-panel p-6 sm:p-8">
                <form method="POST" action="{{ route('prospects.import.preview') }}" enctype="multipart/form-data" class="space-y-6">
                    @csrf
                    <div>
                        <x-input-label for="csv_file" :value="__('prospect_import_csv')" />
                        <input id="csv_file" name="csv_file" type="file" accept=".csv,.txt,.tsv,text/csv,text/plain" class="mt-2 block w-full text-sm text-slate-700 file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 dark:text-slate-200 dark:file:bg-indigo-950/50 dark:file:text-indigo-300" />
                        <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="contacts_input" :value="__('prospect_import_paste')" />
                        <textarea
                            id="contacts_input"
                            name="contacts_input"
                            rows="12"
                            class="mt-2 block w-full rounded-lg border-slate-300 font-mono text-sm shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            placeholder="name,email,phone,whatsapp,company,job_title,linkedin,website&#10;Ada Lovelace,ada@example.com,+33123456789,+33123456789,Analytical Engines,Engineer,https://linkedin.com/in/ada,https://example.com"
                        >{{ old('contacts_input') }}</textarea>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_import_help') }}</p>
                        <x-input-error :messages="$errors->get('contacts_input')" class="mt-2" />
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <x-primary-button type="submit">{{ __('prospect_import_preview') }}</x-primary-button>
                        <a href="{{ route('prospects.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
