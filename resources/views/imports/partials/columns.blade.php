@props([
    'columns' => [],
    'showDownload' => false,
    'type' => null,
])

<div class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('data_import_columns_heading') }}</p>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('data_import_columns_help') }}</p>
        </div>
        @if ($showDownload && $type)
            <a
                href="{{ route('import.example', $type) }}"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-500/40 dark:hover:text-indigo-300"
            >
                <i class="fa-solid fa-download text-[10px]" aria-hidden="true"></i>
                {{ __('data_import_download_example') }}
            </a>
        @endif
    </div>

    <div class="mt-3 overflow-x-auto">
        <table class="min-w-full text-start text-sm">
            <thead>
                <tr class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                    <th class="py-1.5 pe-3 text-start">{{ __('data_import_column_name') }}</th>
                    <th class="py-1.5 pe-3 text-start">{{ __('Required') }}</th>
                    <th class="py-1.5 text-start">{{ __('Description') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/70 dark:divide-slate-700/70">
                @foreach ($columns as $column)
                    <tr>
                        <td class="py-2 pe-3 align-top">
                            <code class="rounded bg-white px-1.5 py-0.5 text-[12px] font-semibold text-indigo-700 dark:bg-slate-800 dark:text-indigo-300">{{ $column['key'] }}</code>
                            @if (! empty($column['aliases']))
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ __('data_import_aliases') }}:
                                    {{ implode(', ', $column['aliases']) }}
                                </p>
                            @endif
                        </td>
                        <td class="py-2 pe-3 align-top text-xs">
                            @if ($column['required'])
                                <span class="font-semibold text-amber-700 dark:text-amber-300">{{ __('Yes') }}</span>
                            @else
                                <span class="text-slate-500 dark:text-slate-400">{{ __('No') }}</span>
                            @endif
                        </td>
                        <td class="py-2 align-top text-xs text-slate-600 dark:text-slate-300">{{ $column['description'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
