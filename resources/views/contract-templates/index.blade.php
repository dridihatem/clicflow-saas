<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Contract templates') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('contract_templates_index_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('contracts.index') }}">
                    <x-secondary-button type="button" class="!normal-case">{{ __('Contracts') }}</x-secondary-button>
                </a>
                <a href="{{ route('contract-templates.create') }}">
                    <x-primary-button type="button" class="!normal-case inline-flex items-center gap-2">
                        <i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
                        {{ __('New template') }}
                    </x-primary-button>
                </a>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('contract_type_label') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-slate-800 dark:divide-slate-700/80 dark:text-slate-100">
                        @forelse ($templates as $template)
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-start">
                                    <a href="{{ route('contract-templates.edit', $template) }}" class="text-indigo-700 hover:underline dark:text-indigo-300">{{ $template->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-start">{{ __('contract_type_'.$template->type_key) }}</td>
                                <td class="px-4 py-3 text-start">{{ $template->is_active ? __('Active') : __('Inactive') }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        href="{{ route('contract-templates.edit', $template) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                        title="{{ __('Edit') }}"
                                        aria-label="{{ __('Edit') }}"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No templates yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>
        </div>
    </div>
</x-app-layout>
