<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Clients') }}</h2>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
                @can('workspace.manage_clients')
                    <a href="{{ route('clients.account-requests.index') }}">
                        <x-secondary-button type="button" class="!normal-case inline-flex items-center gap-2">
                            <i class="fa-solid fa-inbox text-sm" aria-hidden="true"></i>
                            {{ __('Client signup requests') }}
                        </x-secondary-button>
                    </a>
                @endcan
                <a href="{{ route('clients.create') }}">
                    <x-primary-button type="button" class="!normal-case inline-flex items-center gap-2">
                        <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                        {{ __('Add client') }}
                    </x-primary-button>
                </a>
            </div>

            <div class="flow-panel mb-8 p-6 sm:p-8">
                <form method="GET" action="{{ route('clients.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0 flex-1 sm:max-w-md">
                        <x-input-label for="client_q" :value="__('Search')" />
                        <x-text-input id="client_q" name="q" type="search" :value="$q" class="mt-1 block w-full" placeholder="{{ __('client_search_placeholder') }}" />
                    </div>
                    <div class="w-full sm:w-44">
                        <x-input-label for="client_filter_status" :value="__('client_status_label')" />
                        <select id="client_filter_status" name="status" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('client_filter_all_statuses') }}</option>
                            @foreach (\App\Enums\ClientStatus::cases() as $statusOption)
                                <option value="{{ $statusOption->value }}" @selected(($statusFilter ?? '') === $statusOption->value)>{{ $statusOption->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full sm:w-52">
                        <x-input-label for="client_filter_category" :value="__('client_category_label')" />
                        <select id="client_filter_category" name="category" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('client_filter_all_categories') }}</option>
                            @foreach (($categories ?? collect()) as $cat)
                                <option value="{{ $cat }}" @selected(($categoryFilter ?? '') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-secondary-button type="submit" class="inline-flex items-center gap-2">
                        <x-cf-icon name="filter" size="xs" />
                        {{ __('Search') }}
                    </x-secondary-button>
                    @if ($q !== '' || ($statusFilter ?? '') !== '' || ($categoryFilter ?? '') !== '')
                        <a href="{{ route('clients.index') }}" class="text-sm font-medium text-slate-500 hover:text-orange-600 dark:text-slate-400">{{ __('client_filter_reset') }}</a>
                    @endif
                </form>
            </div>

            <div class="flow-panel overflow-hidden p-0">
                <x-flow.table>
                    <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('client_company_contact_column') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('client_category_label') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Client code') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Email') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Phone') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('client_status_label') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('client_source_label') }}</th>
                            <th class="px-4 py-3 text-start">{{ __('Created') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-slate-800 dark:divide-slate-700/80 dark:text-slate-100">
                        @forelse ($clients as $client)
                            @php
                                $canDelete = ($client->invoices_count ?? 0) === 0;
                            @endphp
                            <tr class="transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3 font-medium text-start">
                                    <a href="{{ route('clients.show', $client) }}" class="text-indigo-700 hover:underline dark:text-indigo-300">{{ $client->name }}</a>
                                </td>
                                <td class="px-4 py-3 text-sm text-start">
                                    @if ($client->company_name || $client->contact_name)
                                        <div class="font-medium text-slate-900 dark:text-white">{{ $client->company_name ?? '—' }}</div>
                                        @if ($client->contact_name)
                                            <div class="mt-0.5 inline-flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"><x-cf-icon name="user" size="xs" />{{ $client->contact_name }}</div>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-start">
                                    @if ($client->category)
                                        <a href="{{ route('clients.index', ['category' => $client->category]) }}" class="inline-flex items-center rounded-full bg-orange-50 px-2.5 py-0.5 text-xs font-medium text-orange-700 ring-1 ring-orange-200 hover:bg-orange-100 dark:bg-orange-950/40 dark:text-orange-300 dark:ring-orange-900/50">{{ $client->category }}</a>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-sm text-indigo-700 dark:text-indigo-300 text-start">{{ $client->code ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-start">{{ $client->email ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-start"><span class="flowdesk-ltr-num tabular-nums">{{ $client->phone ?? '—' }}</span></td>
                                <td class="px-4 py-3 text-sm text-start">
                                    @php
                                        $statusCase = $client->status instanceof \App\Enums\ClientStatus
                                            ? $client->status
                                            : \App\Enums\ClientStatus::tryFrom((string) ($client->status ?? 'active')) ?? \App\Enums\ClientStatus::Active;
                                    @endphp
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusCase->badgeClass() }}">{{ $statusCase->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-start">
                                    @php
                                        $sourceCase = $client->source ? \App\Enums\ClientSource::tryFrom($client->source) : null;
                                    @endphp
                                    @if ($sourceCase)
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">{{ $sourceCase->label() }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-500 dark:text-slate-400 text-start">{{ $client->created_at?->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-end">
                                    <div class="inline-flex flex-wrap items-center justify-end gap-1">
                                        <a
                                            href="{{ route('clients.show', $client) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                            title="{{ __('View') }}"
                                        >
                                            <i class="fa-solid fa-eye text-sm" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('chat.open.client', $client) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                            title="{{ __('Chat') }}"
                                        >
                                            <i class="fa-regular fa-comments text-sm" aria-hidden="true"></i>
                                        </a>
                                        <a
                                            href="{{ route('clients.edit', $client) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                                            title="{{ __('Edit') }}"
                                        >
                                            <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                                        </a>
                                        @if ($canDelete)
                                            <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline" onsubmit="return confirm({{ json_encode(__('Delete this client?')) }})">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                                                    title="{{ __('Delete') }}"
                                                >
                                                    <i class="fa-regular fa-trash-can text-sm" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span
                                                class="inline-flex items-center gap-1 rounded-lg border border-slate-100 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400"
                                                title="{{ __('Locked: this client has invoices and cannot be deleted.') }}"
                                            >
                                                <i class="fa-solid fa-lock text-[10px]" aria-hidden="true"></i>
                                                {{ __('Locked') }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-12 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No clients yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-flow.table>
            </div>

            <div class="mt-6">
                {{ $clients->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
