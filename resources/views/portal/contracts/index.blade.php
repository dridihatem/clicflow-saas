<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Contracts') }}</h2>
    </x-slot>

    <div class="portal-page py-8">
        <div class="mx-auto max-w-12xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="portal-surface portal-enter">
                <x-flow.table>
                    <thead class="bg-slate-50/80 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/50 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Reference') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Title') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($contracts as $contract)
                            <tr class="transition hover:bg-teal-50/40 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-mono text-sm text-slate-800 dark:text-slate-200">{{ $contract->reference ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('portal.contracts.show', $contract) }}" class="font-medium text-teal-700 hover:underline dark:text-teal-300">{{ $contract->title }}</a>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $contract->status->label() }}</td>
                                <td class="px-4 py-3 text-end">
                                    <a
                                        href="{{ route('portal.contracts.show', $contract) }}"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-teal-200 hover:text-teal-700 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-teal-500/40 dark:hover:text-teal-300"
                                        title="{{ __('View') }}"
                                        aria-label="{{ __('View') }}"
                                    >
                                        <i class="fa-regular fa-eye text-sm" aria-hidden="true"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No contracts yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>
            @if ($contracts->hasPages())
                <div class="mt-6">{{ $contracts->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
