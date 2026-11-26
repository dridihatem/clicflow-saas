@props(['project', 'active' => 'overview'])

<nav class="flex flex-wrap gap-2 border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
    <a
        href="{{ route('portal.projects.show', $project) }}"
        @class([
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-teal-700 text-white shadow-sm shadow-teal-700/20 dark:bg-teal-500 dark:text-slate-950' => $active === 'overview',
            'text-slate-600 hover:bg-teal-50 hover:text-teal-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-teal-200' => $active !== 'overview',
        ])
    >
        <i class="fa-solid fa-circle-info text-xs opacity-80" aria-hidden="true"></i>
        {{ __('Overview') }}
    </a>
    <a
        href="{{ route('portal.projects.kanban', $project) }}"
        @class([
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-teal-700 text-white shadow-sm shadow-teal-700/20 dark:bg-teal-500 dark:text-slate-950' => $active === 'kanban',
            'text-slate-600 hover:bg-teal-50 hover:text-teal-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-teal-200' => $active !== 'kanban',
        ])
    >
        <i class="fa-solid fa-table-columns text-xs opacity-80" aria-hidden="true"></i>
        {{ __('Task board') }}
    </a>
    <a
        href="{{ route('portal.projects.gantt', $project) }}"
        @class([
            'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition',
            'bg-teal-700 text-white shadow-sm shadow-teal-700/20 dark:bg-teal-500 dark:text-slate-950' => $active === 'gantt',
            'text-slate-600 hover:bg-teal-50 hover:text-teal-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-teal-200' => $active !== 'gantt',
        ])
    >
        <i class="fa-solid fa-chart-gantt text-xs opacity-80" aria-hidden="true"></i>
        {{ __('Gantt timeline') }}
    </a>
</nav>
