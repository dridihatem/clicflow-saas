@php
    $isCompany = $area === 'company';
    $badgeStyles = [
        'internal' => 'bg-slate-100 text-slate-700 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700',
        'client' => 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-950/40 dark:text-orange-200 dark:ring-orange-900/60',
        'project' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-900/60',
        'team' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-900/60',
        'providers' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-900/60',
        'members' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-200 dark:ring-sky-900/60',
    ];
    $typeIcons = ['image' => 'image', 'pdf' => 'file-pdf', 'document' => 'file-lines', 'sheet' => 'chart-column', 'presentation' => 'chart-pie', 'archive' => 'layer-group', 'other' => 'file-lines'];
    $hasFilters = collect($filters)->filter(fn ($v) => $v !== '')->isNotEmpty();
    $sizeLabel = $stats['size'] >= 1048576 ? number_format($stats['size'] / 1048576, 1, ',', ' ').' Mo' : number_format($stats['size'] / 1024, 0, ',', ' ').' Ko';
    $kicker = match ($area) { 'portal' => __('Client portal'), 'provider' => __('Provider portal'), default => __('vault_kicker_company') };
    $intro = match ($area) { 'portal' => __('vault_intro_portal'), 'provider' => __('vault_intro_provider'), default => __('vault_intro_company') };
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <x-cf-icon name="vault" variant="tile" tone="orange" class="cf-stat-tile" />
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ $kicker }}</p>
                    <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('vault_nav') }}</h2>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
            @if ($isCompany && $access->isManager(auth()->user()))
                <a href="{{ route('vault.trash') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:border-orange-300 hover:text-orange-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <x-cf-icon name="trash" size="xs" />
                    {{ __('vault_trash_title') }}
                    @if ($trashCount)<span class="rounded-full bg-slate-100 px-2 text-xs text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $trashCount }}</span>@endif
                </a>
            @endif
            <a href="{{ route($rp.'index', array_merge(request()->except('edit', 'page'), ['upload' => 1])) }}"
               x-data @click.prevent="$dispatch('vault-upload-open')"
               class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/40">
                <x-cf-icon name="upload" size="xs" />
                {{ __('vault_upload_button') }}
            </a>
            </div>
        </div>
    </x-slot>

    <div class="{{ $area === 'portal' ? 'portal-page ' : '' }}py-8">
        <div class="mx-auto w-full max-w-12xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                    <ul class="list-disc space-y-0.5 ps-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="flow-panel overflow-hidden p-0">
                <div class="flex flex-col gap-4 border-b border-slate-200/80 bg-gradient-to-r from-orange-50/80 via-white to-white px-5 py-5 dark:border-slate-700/80 dark:from-orange-950/20 dark:via-slate-900/50 dark:to-slate-900/40 ">
                    <p class="max-w-4xl text-sm text-slate-600 dark:text-slate-300">{{ $intro }}</p>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                        @foreach (array_slice([
                            ['vault_stat_files', $stats['total'], 'layer-group', 'orange'],
                            ['vault_stat_size', $sizeLabel, 'download', 'blue'],
                            ['vault_stat_shared_client', $stats['shared_client'], 'user', 'emerald'],
                            ['vault_stat_from_clients', $stats['from_clients'], 'upload', 'violet'],
                        ], 0, $isCompany ? 4 : 2) as [$label, $value, $icon, $tone])
                            <div class="flex items-center gap-2 rounded-xl bg-white/80 px-3 py-2 ring-1 ring-slate-200/80 dark:bg-slate-900/60 dark:ring-slate-700">
                                <x-cf-icon :name="$icon" variant="tile" :tone="$tone" size="sm" />
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $value }}</p>
                                    <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ __($label) }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <form method="GET" action="{{ route($rp.'index') }}" class="grid grid-cols-1 gap-3 border-b border-slate-200/80 px-5 py-4 dark:border-slate-700/80 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                    <div class="lg:col-span-2">
                        <x-input-label for="vault_q" :value="__('Search')" class="!text-xs" />
                        <x-text-input id="vault_q" name="q" type="search" :value="$filters['q']" class="mt-1 block w-full" placeholder="{{ __('vault_search_placeholder') }}" />
                    </div>
                    @if ($isCompany)
                        <div>
                            <x-input-label for="vault_client" :value="__('Client')" class="!text-xs" />
                            <select id="vault_client" name="client_id" class="flow-input mt-1 block w-full">
                                <option value="">{{ __('vault_filter_all') }}</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}" @selected($filters['client_id'] === (string) $c->id)>{{ $c->company_name ?: $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <x-input-label for="vault_project" :value="__('Project')" class="!text-xs" />
                        <select id="vault_project" name="project_id" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" @selected($filters['project_id'] === (string) $p->id)>{{ $p->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="vault_type" :value="__('vault_filter_type')" class="!text-xs" />
                        <select id="vault_type" name="type" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach (['image', 'pdf', 'document', 'sheet', 'presentation', 'archive'] as $t)
                                <option value="{{ $t }}" @selected($filters['type'] === $t)>{{ __('vault_type_'.$t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="vault_uploader" :value="__('vault_filter_uploader')" class="!text-xs" />
                        <select id="vault_uploader" name="uploader" class="flow-input mt-1 block w-full">
                            <option value="">{{ __('vault_filter_all') }}</option>
                            @foreach (['staff', 'client', 'provider'] as $u)
                                <option value="{{ $u }}" @selected($filters['uploader'] === $u)>{{ __('vault_uploader_'.$u) }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if (count($folders))
                        <div>
                            <x-input-label for="vault_folder" :value="__('vault_field_folder')" class="!text-xs" />
                            <select id="vault_folder" name="folder" class="flow-input mt-1 block w-full">
                                <option value="">{{ __('vault_filter_all') }}</option>
                                @foreach ($folders as $f)
                                    <option value="{{ $f }}" @selected($filters['folder'] === $f)>{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <x-input-label for="vault_from" :value="__('vault_filter_from')" class="!text-xs" />
                        <x-text-input id="vault_from" name="from" type="date" :value="$filters['from']" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <x-input-label for="vault_to" :value="__('vault_filter_to')" class="!text-xs" />
                        <x-text-input id="vault_to" name="to" type="date" :value="$filters['to']" class="mt-1 block w-full" />
                    </div>
                    <div class="flex items-center gap-3">
                        <x-secondary-button type="submit" class="inline-flex items-center gap-2">
                            <x-cf-icon name="filter" size="xs" />
                            {{ __('vault_filter_apply') }}
                        </x-secondary-button>
                        @if ($hasFilters)
                            <a href="{{ route($rp.'index') }}" class="text-sm font-medium text-slate-500 hover:text-orange-600 dark:text-slate-400">{{ __('vault_filter_reset') }}</a>
                        @endif
                    </div>
                </form>

                @if ($files->isEmpty())
                    <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                        <x-cf-icon name="vault" variant="tile" tone="slate" size="md" />
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $hasFilters ? __('vault_empty_filtered') : __('vault_empty_title') }}</p>
                        <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ __('vault_empty_help') }}</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200/80 text-sm dark:divide-slate-700/80">
                            <thead class="bg-slate-50/90 text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                                <tr>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_file') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_linked') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_visibility') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_uploader') }}</th>
                                    <th class="px-4 py-3 text-start">{{ __('vault_col_date') }}</th>
                                    <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @foreach ($files as $file)
                                    @php $type = $file->typeKey(); @endphp
                                    <tr class="hover:bg-orange-50/30 dark:hover:bg-slate-800/40">
                                        <td class="px-4 py-3">
                                            <div class="flex items-center gap-3">
                                                <x-cf-icon :name="$typeIcons[$type] ?? 'file-lines'" variant="tile" :tone="$type === 'pdf' ? 'rose' : ($type === 'image' ? 'violet' : 'blue')" size="sm" />
                                                <div class="min-w-0">
                                                    <p class="max-w-xs truncate font-semibold text-slate-900 dark:text-white" title="{{ $file->original_name }}">{{ $file->original_name }}</p>
                                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                                        {{ strtoupper((string) $file->extension) }} · {{ $file->humanSize() }}
                                                        @if ($file->folder) · <span class="inline-flex items-center gap-1"><x-cf-icon name="folder-open" size="xs" />{{ $file->folder }}</span>@endif
                                                    </p>
                                                    @if ($file->description)
                                                        <p class="mt-0.5 max-w-sm truncate text-xs text-slate-500 dark:text-slate-400">{{ $file->description }}</p>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                            @if ($file->project)<p class="font-medium">{{ __('Project') }} : {{ $file->project->title }}</p>@endif
                                            @if ($isCompany && $file->client)<p>{{ __('Client') }} : {{ $file->client->company_name ?: $file->client->name }}</p>@endif
                                            @if (! $file->project && ! ($isCompany && $file->client))<span class="text-slate-400">—</span>@endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex max-w-[16rem] flex-wrap gap-1">
                                                @foreach ($access->badges($file) as $badge)
                                                    @if ($isCompany || in_array($badge, $area === 'portal' ? ['client', 'project'] : ['project', 'providers'], true))
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $badgeStyles[$badge] }}"
                                                              @if ($badge === 'members') title="{{ $file->sharedUsers->pluck('name')->join(', ') }}" @endif>{{ __('vault_badge_'.$badge) }}</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                            @if ($isCompany && $file->client_notified_at)
                                                <p class="mt-1 text-[11px] text-slate-400">{{ __('vault_notified_at', ['date' => $file->client_notified_at->format('d/m/Y H:i')]) }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                                            <p class="font-medium">{{ $file->uploader?->name ?? '—' }}</p>
                                            <p class="text-slate-400">{{ __('vault_uploader_'.$file->uploader_type) }}</p>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{{ $file->created_at?->format('d/m/Y H:i') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-end">
                                            <div class="inline-flex items-center gap-1">
                                                @if ($file->isPreviewable())
                                                    <a href="{{ route($rp.'preview', $file->id) }}" target="_blank" rel="noopener" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-orange-600 dark:hover:bg-slate-800" title="{{ __('vault_action_preview') }}"><x-cf-icon name="eye" size="xs" /></a>
                                                @endif
                                                <a href="{{ route($rp.'download', $file->id) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-orange-600 dark:hover:bg-slate-800" title="{{ __('vault_action_download') }}"><x-cf-icon name="download" size="xs" /></a>
                                                @if ($isCompany && $access->canEditSharing(auth()->user(), $file))
                                                    <a href="{{ route($rp.'index', array_merge(request()->except('upload'), ['edit' => $file->id])) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-orange-600 dark:hover:bg-slate-800" title="{{ __('vault_action_share') }}"><x-cf-icon name="user-group" size="xs" /></a>
                                                @endif
                                                @if ($isCompany && $access->canEditSharing(auth()->user(), $file))
                                                    <a href="{{ route($rp.'index', array_merge(request()->except('upload', 'edit'), ['links' => $file->id])) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-orange-600 dark:hover:bg-slate-800" title="{{ __('vault_action_links') }}"><x-cf-icon name="link" size="xs" /></a>
                                                @endif
                                                @if ($access->canDelete(auth()->user(), $file))
                                                    <form method="POST" action="{{ route($rp.'destroy', $file->id) }}" onsubmit="return confirm(@js(__('vault_delete_confirm')))">
                                                        @csrf @method('DELETE')
                                                        <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40" title="{{ __('vault_action_delete') }}"><x-cf-icon name="trash" size="xs" /></button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($files->hasPages())
                        <div class="border-t border-slate-200/80 px-5 py-3 dark:border-slate-700/80">{{ $files->links() }}</div>
                    @endif
                @endif
            </div>

            @if ($isCompany && $legacy->isNotEmpty())
                <div class="flow-panel p-5">
                    <div class="mb-3 flex items-center gap-2">
                        <x-cf-icon name="projects" variant="tile" tone="blue" size="sm" />
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('vault_legacy_title') }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('vault_legacy_help') }}</p>
                        </div>
                    </div>
                    <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                        @foreach ($legacy as $pf)
                            <li class="flex items-center justify-between gap-3 py-2">
                                <span class="min-w-0 truncate text-slate-700 dark:text-slate-200">{{ $pf->original_name }} <span class="text-xs text-slate-400">· {{ $pf->project?->title }}</span></span>
                                @if ($pf->project)
                                    <a href="{{ route('projects.vault.download', [$pf->project_id, $pf->id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-orange-600 hover:text-orange-500"><x-cf-icon name="download" size="xs" />{{ __('vault_action_download') }}</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flow-panel flex items-start gap-3 p-4 text-xs text-slate-500 dark:text-slate-400">
                <x-cf-icon name="info" size="sm" />
                <p>{{ __('vault_rules_footer', ['max' => (int) round($maxKb / 1024), 'types' => implode(', ', $allowed)]) }}</p>
            </div>
        </div>
    </div>

    @include('vault.partials.upload-dialog')
    @if ($linksFile)
        @include('vault.partials.links-dialog', ['file' => $linksFile])
    @endif
    @if ($editFile)
        @include('vault.partials.share-dialog', ['file' => $editFile])
    @endif
</x-app-layout>
