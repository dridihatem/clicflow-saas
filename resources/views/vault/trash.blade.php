@php $hasFilters = collect($filters)->filter(fn ($v) => $v !== '')->isNotEmpty(); @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <x-cf-icon name="trash" variant="tile" tone="rose" class="cf-stat-tile" />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('vault_nav') }}</p>
                    <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('vault_trash_title') }}</h2>
                </div>
            </div>
            <a href="{{ route('vault.index') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-orange-300 hover:text-orange-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <x-cf-icon name="vault" size="xs" />
                {{ __('vault_trash_back') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-12xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            <div class="flow-panel overflow-hidden p-0">
                <div class="flex items-start gap-3 border-b border-slate-200/80 bg-gradient-to-r from-rose-50/80 via-white to-white px-5 py-4 dark:border-slate-700/80 dark:from-rose-950/20 dark:via-slate-900/50">
                    <x-cf-icon name="info" size="sm" />
                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('vault_trash_intro', ['days' => $retentionDays]) }}</p>
                </div>

                <form method="GET" action="{{ route('vault.trash') }}" class="grid grid-cols-1 gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/80 sm:grid-cols-2 lg:grid-cols-7 lg:items-end">
                    <div class="lg:col-span-2">
                        <x-input-label for="t_q" :value="__('Search')" class="!text-xs" />
                        <x-text-input id="t_q" name="q" type="search" :value="$filters['q']" class="mt-1 block w-full" placeholder="{{ __('vault_search_placeholder') }}" />
                    </div>
                    <div>
                        <x-input-label for="t_client" :value="__('Client')" class="!text-xs" />
                        <select id="t_client" name="client_id" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach ($clients as $c)<option value="{{ $c->id }}" @selected($filters['client_id'] === (string) $c->id)>{{ $c->company_name ?: $c->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="t_project" :value="__('Project')" class="!text-xs" />
                        <select id="t_project" name="project_id" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach ($projects as $p)<option value="{{ $p->id }}" @selected($filters['project_id'] === (string) $p->id)>{{ $p->title }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="t_by" :value="__('vault_trash_deleted_by')" class="!text-xs" />
                        <select id="t_by" name="deleted_by" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach ($deleters as $d)<option value="{{ $d->id }}" @selected($filters['deleted_by'] === (string) $d->id)>{{ $d->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="t_from" :value="__('vault_filter_from')" class="!text-xs" />
                        <x-text-input id="t_from" name="from" type="date" :value="$filters['from']" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-secondary-button type="submit" class="inline-flex items-center gap-2"><x-cf-icon name="filter" size="xs" />{{ __('vault_filter_apply') }}</x-secondary-button>
                        @if ($hasFilters)<a href="{{ route('vault.trash') }}" class="text-sm font-medium text-slate-500 hover:text-orange-600">{{ __('vault_filter_reset') }}</a>@endif
                    </div>
                </form>

                @if ($files->isEmpty())
                    <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <x-cf-icon name="trash" variant="tile" tone="slate" size="md" />
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('vault_trash_empty') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200/80 text-sm dark:divide-slate-700/80">
                            <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_file') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_linked') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_uploader') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_trash_deleted_by') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_trash_deleted_at') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_trash_purge_on') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($files as $file)
                                    @php $purgeOn = $file->deleted_at?->copy()->addDays($retentionDays); @endphp
                                    <tr>
                                        <td class="px-4 py-3">
                                            <p class="max-w-xs truncate font-semibold text-slate-900 dark:text-white">{{ $file->original_name }}</p>
                                            <p class="text-xs text-slate-500">{{ strtoupper((string) $file->extension) }} · {{ $file->humanSize() }}@if ($file->folder) · {{ $file->folder }}@endif</p>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                            @if ($file->project)<p>{{ __('Project') }} : {{ $file->project->title }}</p>@endif
                                            @if ($file->client)<p>{{ __('Client') }} : {{ $file->client->company_name ?: $file->client->name }}</p>@endif
                                            @if (! $file->project && ! $file->client)<span class="text-slate-400">—</span>@endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">{{ $file->uploader?->name ?? '—' }}<br><span class="text-slate-400">{{ __('vault_uploader_'.$file->uploader_type) }}</span></td>
                                        <td class="px-4 py-3 text-xs font-medium text-slate-700 dark:text-slate-200">{{ $file->deleter?->name ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500">{{ $file->deleted_at?->format('d/m/Y H:i') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs {{ $purgeOn && $purgeOn->diffInDays(now(), true) <= 3 ? 'font-semibold text-rose-600' : 'text-slate-500' }}">{{ $purgeOn?->format('d/m/Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end">
                                            <div class="inline-flex items-center gap-2">
                                                <form method="POST" action="{{ route('vault.restore', $file->id) }}">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 px-2.5 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-50"><x-cf-icon name="rotate-left" size="xs" />{{ __('vault_trash_restore') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('vault.force-destroy', $file->id) }}" onsubmit="return confirm(@js(__('vault_trash_force_confirm', ['name' => $file->original_name])))">
                                                    @csrf @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center gap-1 rounded-lg border border-rose-200 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50"><x-cf-icon name="trash" size="xs" />{{ __('vault_trash_force') }}</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($files->hasPages())<div class="border-t border-slate-200/80 px-5 py-3">{{ $files->links() }}</div>@endif
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
