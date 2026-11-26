<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Contracts') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('contracts_index_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
                <a href="{{ route('contract-templates.index') }}">
                    <x-secondary-button type="button" class="!normal-case">{{ __('Contract templates') }}</x-secondary-button>
                </a>
                <a href="{{ route('contracts.create') }}">
                    <x-primary-button type="button" class="!normal-case inline-flex items-center gap-2">
                        <i class="fa-solid fa-file-signature text-sm" aria-hidden="true"></i>
                        {{ __('New contract') }}
                    </x-primary-button>
                </a>
            </div>

            <div class="flow-panel mb-8 p-6 sm:p-8">
                <form method="GET" action="{{ route('contracts.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <x-input-label for="contract_q" :value="__('Search')" />
                        <x-text-input id="contract_q" name="q" type="search" :value="$q" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="contract_status" :value="__('Status')" />
                        <select id="contract_status" name="status" class="flow-input mt-1 block w-full min-w-[10rem]">
                            <option value="">{{ __('All') }}</option>
                            @foreach (\App\Enums\ClientContractStatus::cases() as $statusCase)
                                <option value="{{ $statusCase->value }}" @selected($status === $statusCase->value)>{{ $statusCase->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="contract_type" :value="__('contract_type_label')" />
                        <select id="contract_type" name="type" class="flow-input mt-1 block w-full min-w-[10rem]">
                            <option value="">{{ __('All') }}</option>
                            @foreach (['service', 'nda', 'retainer', 'consulting', 'custom'] as $typeKey)
                                <option value="{{ $typeKey }}" @selected($type === $typeKey)>{{ __('contract_type_'.$typeKey) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="contract_client" :value="__('Client')" />
                        <select id="contract_client" name="client_id" class="flow-input mt-1 block w-full min-w-[12rem]">
                            <option value="">{{ __('All') }}</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected($clientId === (string) $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit">{{ __('Search') }}</x-secondary-button>
                </form>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Reference') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Title') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-slate-800 dark:divide-slate-700/80 dark:text-slate-100">
                        @forelse ($contracts as $contract)
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 text-start font-mono text-sm">{{ $contract->reference ?? '—' }}</td>
                                <td class="px-4 py-3 font-medium text-start">
                                    <a href="{{ route('contracts.show', $contract) }}" class="text-indigo-700 hover:underline dark:text-indigo-300">{{ $contract->title }}</a>
                                </td>
                                <td class="px-4 py-3 text-start">{{ $contract->client?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-start">{{ $contract->status->label() }}</td>
                                <td class="px-4 py-3 text-end">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                        <a
                                            href="{{ route('contracts.show', $contract) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                            title="{{ __('View') }}"
                                            aria-label="{{ __('View') }}"
                                        >
                                            <i class="fa-regular fa-eye text-sm" aria-hidden="true"></i>
                                        </a>
                                        @if ($contract->isEditable())
                                            <a
                                                href="{{ route('contracts.edit', $contract) }}"
                                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                                title="{{ __('Edit') }}"
                                                aria-label="{{ __('Edit') }}"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                        @if ($contract->canResend())
                                            <form method="POST" action="{{ route('contracts.resend', $contract) }}" class="inline">
                                                @csrf
                                                <button
                                                    type="submit"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                                    title="{{ __('Resend contract email') }}"
                                                    aria-label="{{ __('Resend contract email') }}"
                                                    onclick="return confirm(@js(__('contract_resend_confirm')))"
                                                >
                                                    <i class="fa-solid fa-envelope text-sm" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                        @if ($contract->status !== \App\Enums\ClientContractStatus::Signed)
                                            <form method="POST" action="{{ route('contracts.destroy', $contract) }}" class="inline" onsubmit="return confirm(@js(__('Are you sure?')))">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                                                    title="{{ __('Delete') }}"
                                                    aria-label="{{ __('Delete') }}"
                                                >
                                                    <i class="fa-regular fa-trash-can text-sm" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No contracts yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>

            <div class="mt-6">{{ $contracts->links() }}</div>
        </div>
    </div>
</x-app-layout>
