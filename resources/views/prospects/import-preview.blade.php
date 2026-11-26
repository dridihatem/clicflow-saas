<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('prospect_import_preview') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prospect_import_preview_intro', ['count' => count($rows)]) }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl w-full sm:px-6 lg:px-8 space-y-6">
            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Phone') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('prospect_company_name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('prospect_source_label') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-sm dark:divide-slate-700/80">
                        @foreach ($preview as $row)
                            <tr>
                                <td class="px-4 py-3">{{ $row['name'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['email'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['phone'] ?? $row['whatsapp'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['company_name'] ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $row['source'] ?? 'import' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-flow.table>
            </div>
            @if (count($rows) > count($preview))
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_import_preview_truncated', ['shown' => count($preview), 'total' => count($rows)]) }}</p>
            @endif

            <form method="POST" action="{{ route('prospects.import.store') }}" class="flex flex-wrap gap-3">
                @csrf
                <input type="hidden" name="raw" value="{{ $raw }}">
                <input type="hidden" name="filename" value="{{ $filename }}">
                <x-primary-button type="submit">{{ __('prospect_import_confirm') }}</x-primary-button>
                <a href="{{ route('prospects.import') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Cancel') }}</a>
            </form>
        </div>
    </div>
</x-app-layout>
