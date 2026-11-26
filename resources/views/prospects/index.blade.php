<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Prospects') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('prospects_index_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('prospects.import') }}">
                    <x-secondary-button type="button" class="!normal-case inline-flex items-center gap-2">
                        <i class="fa-solid fa-file-import text-sm" aria-hidden="true"></i>
                        {{ __('prospect_import') }}
                    </x-secondary-button>
                </a>
                <a href="{{ route('prospects.create') }}">
                    <x-primary-button type="button" class="!normal-case inline-flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                        {{ __('prospect_add') }}
                    </x-primary-button>
                </a>
            </div>

            <div class="flow-panel mb-8 p-6 sm:p-8">
                <form method="GET" action="{{ route('prospects.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <x-input-label for="prospect_q" :value="__('Search')" />
                        <x-text-input id="prospect_q" name="q" type="search" :value="$q" class="mt-1 block w-full" placeholder="{{ __('prospect_search_placeholder') }}" />
                    </div>
                    <div>
                        <x-input-label for="prospect_status_filter" :value="__('prospect_status_label')" />
                        <select id="prospect_status_filter" name="status" class="flow-input mt-1 block w-full min-w-[10rem]">
                            <option value="">{{ __('All') }}</option>
                            @foreach (\App\Enums\ProspectStatus::cases() as $statusCase)
                                <option value="{{ $statusCase->value }}" @selected($status === $statusCase->value)>{{ $statusCase->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="prospect_source_filter" :value="__('prospect_source_label')" />
                        <select id="prospect_source_filter" name="source" class="flow-input mt-1 block w-full min-w-[10rem]">
                            <option value="">{{ __('All') }}</option>
                            @foreach (\App\Enums\ProspectSource::cases() as $sourceCase)
                                <option value="{{ $sourceCase->value }}" @selected($source === $sourceCase->value)>{{ $sourceCase->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass text-xs" aria-hidden="true"></i>
                        {{ __('Search') }}
                    </x-secondary-button>
                </form>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Phone') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('prospect_company_name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('prospect_status_label') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('prospect_source_label') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-slate-800 dark:divide-slate-700/80 dark:text-slate-100">
                        @forelse ($prospects as $prospect)
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-start">
                                    <a href="{{ route('prospects.show', $prospect) }}" class="text-indigo-700 hover:underline dark:text-indigo-300">{{ $prospect->name }}</a>
                                    @if ($prospect->verified_at)
                                        <span class="ms-1 inline-flex items-center rounded-full bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">{{ __('prospect_badge_verified') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-start">{{ $prospect->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-start"><span class="flowdesk-ltr-num tabular-nums">{{ $prospect->phone ?? $prospect->whatsapp ?? '—' }}</span></td>
                                <td class="px-4 py-3 text-sm text-start">{{ $prospect->company_name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-start">{{ $prospect->status?->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-start">{{ $prospect->source?->label() ?? '—' }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        href="{{ route('prospects.show', $prospect) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                                        title="{{ __('View') }}"
                                    >
                                        <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('prospects_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>

            @if ($prospects->hasPages())
                <div class="mt-6">{{ $prospects->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
