@php
    $matched = $classification['matched'] ?? [];
    $unknown = $classification['unknown'] ?? [];
    $missing = $classification['missing_required'] ?? [];
    $mapping = $classification['mapping'] ?? [];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('data_import_preview', ['type' => $typeLabel]) }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prospect_import_preview_intro', ['count' => count($rows)]) }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
            @if ($manualMapping ?? false)
                <details class="group rounded-xl border border-slate-200/80 bg-white/60 dark:border-slate-700 dark:bg-slate-900/40">
                    <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-slate-700 hover:text-orange-600 dark:text-slate-200">{{ __('data_import_columns_heading') }}</summary>
                    <div class="px-2 pb-2">
                        @include('imports.partials.columns', [
                            'columns' => $columns,
                            'showDownload' => true,
                            'type' => $type,
                        ])
                    </div>
                </details>
            @else
            @include('imports.partials.columns', [
                'columns' => $columns,
                'showDownload' => true,
                'type' => $type,
            ])
            @endif

            @if (! empty($parsedPreview))
                <div class="flow-panel overflow-hidden p-0">
                    <div class="flex items-center gap-2 border-b border-slate-200/80 px-4 py-3 dark:border-slate-700/80">
                        <x-cf-icon name="check" tone="emerald" class="h-5 w-5" />
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('data_import_products_parsed_title') }}</p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('data_import_products_parsed_hint') }}</p>
                        </div>
                    </div>
                    <x-flow.table>
                        <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                            <tr>
                                <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('catalog_kind_label') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('Price') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('product_tax_rate') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('product_tax_rate_2') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('Unit') }}</th>
                                <th class="px-4 py-3 text-start">{{ __('product_category') }}</th>
                                <th class="px-4 py-3 text-end">{{ __('data_import_description_lines') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($parsedPreview as $p)
                                <tr>
                                    <td class="max-w-xs truncate px-4 py-2.5 font-medium text-slate-900 dark:text-white">{{ $p['name'] }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $p['kind'] === 'service' ? 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' : 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300' }}">{{ __('catalog_kind_'.$p['kind']) }}</span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-2.5 text-end font-semibold tabular-nums text-slate-900 dark:text-white"><span class="flowdesk-ltr-num">{{ $p['price'] }} {{ $p['currency'] }}</span></td>
                                    <td class="px-4 py-2.5 text-end tabular-nums text-slate-700 dark:text-slate-200">{{ $p['tax_rate'] === null ? '—' : flowdesk_format_percent($p['tax_rate']) }}</td>
                                    <td class="px-4 py-2.5 text-end tabular-nums text-slate-500 dark:text-slate-400">{{ $p['tax_rate_2'] === null ? '—' : flowdesk_format_percent($p['tax_rate_2']) }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $p['unit'] ? (\App\Support\CatalogUnits::label($p['unit']) ?: $p['unit']) : '—' }}</td>
                                    <td class="px-4 py-2.5 text-slate-600 dark:text-slate-300">{{ $p['category'] ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-end tabular-nums text-slate-500 dark:text-slate-400">{{ $p['description_lines'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-flow.table>
                </div>
            @endif

            @if ($manualMapping ?? false)
                @include('imports.partials.column-mapping')
            @else
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/30">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">{{ __('data_import_matched_columns') }}</p>
                    <p class="mt-2 text-sm text-emerald-900 dark:text-emerald-100">
                        {{ $matched === [] ? '—' : implode(', ', $matched) }}
                    </p>
                </div>
                <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 p-4 dark:border-amber-900/40 dark:bg-amber-950/30">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('data_import_missing_columns') }}</p>
                    <p class="mt-2 text-sm text-amber-900 dark:text-amber-100">
                        {{ $missing === [] ? '—' : implode(', ', $missing) }}
                    </p>
                </div>
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('data_import_ignored_columns') }}</p>
                    <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">
                        {{ $unknown === [] ? '—' : implode(', ', $unknown) }}
                    </p>
                </div>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            @if ($preview !== [])
                                @foreach (array_keys($preview[0]) as $col)
                                    <th class="px-4 py-3 text-start {{ in_array($col, $matched, true) ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-400' }}">
                                        {{ $col }}
                                        @if (isset($mapping[$col]) && $mapping[$col] !== $col)
                                            <span class="mt-0.5 flex items-center gap-1 text-[10px] font-medium normal-case tracking-normal text-orange-600 dark:text-orange-300">→ <code>{{ $mapping[$col] }}</code></span>
                                        @endif
                                    </th>
                                @endforeach
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-sm dark:divide-slate-700/80">
                        @foreach ($preview as $row)
                            <tr>
                                @foreach ($row as $col => $cell)
                                    <td class="max-w-xs truncate px-4 py-3 {{ in_array($col, $matched, true) ? 'text-slate-800 dark:text-slate-100' : 'text-slate-400 dark:text-slate-500' }}">{{ $cell ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-flow.table>
            </div>

            @if (count($rows) > count($preview))
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_import_preview_truncated', ['shown' => count($preview), 'total' => count($rows)]) }}</p>
            @endif

            <form method="POST" action="{{ route('import.store', $type) }}" class="flex flex-wrap gap-3">
                @csrf
                <input type="hidden" name="raw" value="{{ $raw }}">
                <input type="hidden" name="filename" value="{{ $filename }}">
                <x-primary-button type="submit">{{ __('prospect_import_confirm') }}</x-primary-button>
                <a href="{{ route('import.preview.form', $type) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Cancel') }}</a>
            </form>
            @endif
        </div>
    </div>
</x-app-layout>
