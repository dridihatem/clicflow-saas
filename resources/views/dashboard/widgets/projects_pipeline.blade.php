@props(['metrics' => []])
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Project> $projects */
    $projects = $metrics['dashboard_projects'] ?? collect();
@endphp
<section class="flow-dashboard-panel overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-flow-border bg-flow-surface-muted/40 px-5 py-4">
        <div class="flex items-start gap-3">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-cyan-500/10 text-cyan-600 dark:bg-cyan-500/15 dark:text-cyan-400">
                <i class="fa-solid fa-folder-open text-sm" aria-hidden="true"></i>
            </div>
            <div>
                <h3 class="text-sm font-semibold tracking-tight text-flow-text">{{ __('Projects') }}</h3>
                <p class="mt-0.5 text-xs text-flow-text-muted">{{ __('dashboard_projects_pipeline_lead') }}</p>
            </div>
        </div>
        <a href="{{ route('projects.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('view_all') }}</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full table-fixed text-start text-sm">
            <thead>
                <tr class="border-b border-flow-border bg-flow-surface-muted/30 text-[11px] font-semibold uppercase tracking-wider text-flow-text-muted">
                    <th class="px-5 py-3 text-start">{{ __('Project') }}</th>
                    <th class="px-5 py-3 text-start">{{ __('Client') }}</th>
                    <th class="px-5 py-3 text-start">{{ __('Status') }}</th>
                    <th class="px-5 py-3 text-start">{{ __('Final deadline') }}</th>
                    <th class="px-5 py-3 w-24 text-start"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-flow-border">
                @forelse ($projects as $project)
                    @php
                        $d = $project->final_deadline;
                        $completed = $project->status === \App\Enums\ProjectStatus::Completed;
                        $overdue = $d && ! $completed && today()->gt($d);
                        $dueToday = $d && ! $completed && $d->isToday();
                        $dueSoon = $d && ! $completed && ! $overdue && ! $dueToday && $d->gt(today()) && $d->lte(today()->copy()->addDays(7));
                        $statusVariant = match ($project->status) {
                            \App\Enums\ProjectStatus::Draft => 'slate',
                            \App\Enums\ProjectStatus::Pending => 'warning',
                            \App\Enums\ProjectStatus::Approved => 'indigo',
                            \App\Enums\ProjectStatus::InProgress => 'primary',
                            \App\Enums\ProjectStatus::Completed => 'success',
                        };
                    @endphp
                    <tr class="transition-colors hover:bg-flow-surface-muted/50">
                        <td class="px-5 py-3.5 text-start">
                            <a href="{{ route('projects.show', $project) }}" class="font-medium text-flow-text hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $project->title }}
                            </a>
                        </td>
                        <td class="px-5 py-3.5 text-start text-flow-text-muted">{{ $project->client?->name ?? '—' }}</td>
                        <td class="px-5 py-3.5 text-start">
                            <x-flow.badge :variant="$statusVariant">{{ $project->status->label() }}</x-flow.badge>
                        </td>
                        <td class="px-5 py-3.5 text-start">
                            @if ($d)
                                <span @class([
                                    'inline-flex items-center gap-1.5 font-medium tabular-nums',
                                    'text-rose-600 dark:text-rose-400' => $overdue,
                                    'text-amber-700 dark:text-amber-300' => $dueToday,
                                    'text-amber-600/90 dark:text-amber-400/90' => $dueSoon,
                                    'text-flow-text-muted' => ! $overdue && ! $dueToday && ! $dueSoon,
                                ])>
                                    @if ($overdue)
                                        <i class="fa-solid fa-circle-exclamation text-xs" aria-hidden="true"></i>
                                        {{ $d->format('M j, Y') }}
                                        <span class="sr-only">{{ __('Overdue') }}</span>
                                        <span class="ms-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-rose-800 dark:bg-rose-950/60 dark:text-rose-200">{{ __('Overdue') }}</span>
                                    @elseif ($dueToday)
                                        <i class="fa-regular fa-clock text-xs" aria-hidden="true"></i>
                                        {{ $d->format('M j, Y') }}
                                        <span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-900 dark:bg-amber-950/50 dark:text-amber-100">{{ __('Today') }}</span>
                                    @elseif ($dueSoon)
                                        {{ $d->format('M j, Y') }}
                                        <span class="ms-1 text-[10px] font-semibold uppercase tracking-wide text-amber-700/80 dark:text-amber-400">{{ __('soon') }}</span>
                                    @else
                                        {{ $d->format('M j, Y') }}
                                    @endif
                                </span>
                            @else
                                <span class="text-flow-text-muted">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3.5 text-end">
                            <a href="{{ route('projects.show', $project) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('Open') }}</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-5 py-12 text-center">
                            <p class="text-sm font-medium text-flow-text">{{ __('dashboard_no_projects_yet') }}</p>
                            <p class="mt-1 text-xs text-flow-text-muted">{{ __('dashboard_projects_empty_lead') }}</p>
                            @if (auth()->user()->hasAnyRole(['company_admin', 'team_member']))
                                <a href="{{ route('projects.create') }}" class="mt-4 inline-flex text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ __('New project') }}</a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
