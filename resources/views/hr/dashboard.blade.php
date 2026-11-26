<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('hr_dashboard_title') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full space-y-6 sm:px-6 lg:px-8">
            <x-flow.page-header :title="__('hr_dashboard_title')" :description="__('hr_dashboard_intro')">
                <x-slot name="actions">
                    <a href="{{ route('hr.employees.create') }}">
                        <x-primary-button type="button" class="!normal-case inline-flex items-center gap-2">
                            <i class="fa-solid fa-user-plus text-sm" aria-hidden="true"></i>
                            {{ __('hr_add_employee') }}
                        </x-primary-button>
                    </a>
                </x-slot>
            </x-flow.page-header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flow-panel relative overflow-hidden p-5">
                    <div class="pointer-events-none absolute inset-y-0 end-0 w-1/3 bg-gradient-to-l from-indigo-500/10 to-transparent"></div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('hr_stat_employees') }}</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $activeEmployees }} <span class="text-base font-medium text-slate-400">/ {{ $employeesCount }}</span></p>
                </div>
                <div class="flow-panel relative overflow-hidden p-5">
                    <div class="pointer-events-none absolute inset-y-0 end-0 w-1/3 bg-gradient-to-l from-cyan-500/10 to-transparent"></div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('hr_stat_departments') }}</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-slate-900 dark:text-white">{{ $departmentsCount }}</p>
                </div>
                <div class="flow-panel relative overflow-hidden p-5">
                    <div class="pointer-events-none absolute inset-y-0 end-0 w-1/3 bg-gradient-to-l from-amber-500/10 to-transparent"></div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('hr_stat_pending_leave') }}</p>
                    <p class="mt-1 text-3xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ $pendingLeave }}</p>
                </div>
                <div class="flow-panel relative overflow-hidden p-5">
                    <div class="pointer-events-none absolute inset-y-0 end-0 w-1/3 bg-gradient-to-l from-emerald-500/10 to-transparent"></div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('hr_stat_latest_payroll') }}</p>
                    <p class="mt-1 text-lg font-semibold text-slate-900 dark:text-white">{{ $latestPayroll?->title ?? '—' }}</p>
                </div>
            </div>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="flow-panel p-6 sm:p-8">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_recent_employees') }}</h3>
                        <a href="{{ route('hr.employees.index') }}" class="text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">{{ __('View all') }}</a>
                    </div>
                    <ul class="mt-4 divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @forelse ($recentEmployees as $employee)
                            <li class="flex items-center justify-between gap-3 py-3 text-sm">
                                <div>
                                    <a href="{{ route('hr.employees.show', $employee) }}" class="font-medium text-slate-900 hover:text-indigo-600 dark:text-white dark:hover:text-indigo-400">{{ $employee->full_name }}</a>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $employee->job_title ?? '—' }} · {{ $employee->department?->name ?? __('hr_no_department') }}</p>
                                </div>
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $employee->status->badgeClass() }}">{{ $employee->status->label() }}</span>
                            </li>
                        @empty
                            <li class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('hr_no_employees_yet') }}</li>
                        @endforelse
                    </ul>
                </div>

                <div class="flow-panel p-6 sm:p-8">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('hr_quick_actions') }}</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ([
                            ['route' => 'hr.employees.index', 'label' => __('Employees'), 'hint' => __('hr_manage_employees_hint'), 'icon' => 'fa-id-badge'],
                            ['route' => 'hr.departments.index', 'label' => __('hr_departments'), 'hint' => __('hr_manage_departments_hint'), 'icon' => 'fa-sitemap'],
                            ['route' => 'hr.leave.index', 'label' => __('hr_leave'), 'hint' => __('hr_manage_leave_hint'), 'icon' => 'fa-plane-departure'],
                            ['route' => 'hr.payroll.index', 'label' => __('hr_payroll'), 'hint' => __('hr_manage_payroll_hint'), 'icon' => 'fa-money-check-dollar'],
                            ['route' => 'hr.advances.index', 'label' => __('hr_salary_advances'), 'hint' => __('hr_advances_intro'), 'icon' => 'fa-hand-holding-dollar'],
                            ['route' => 'hr.settings.index', 'label' => __('hr_settings'), 'hint' => __('hr_settings_intro'), 'icon' => 'fa-sliders'],
                        ] as $action)
                            <a href="{{ route($action['route']) }}" class="group rounded-xl border border-slate-200/80 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/30">
                                <div class="flex items-start gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-indigo-100 group-hover:text-indigo-700 dark:bg-slate-800 dark:text-slate-300 dark:group-hover:bg-indigo-950/60 dark:group-hover:text-indigo-300">
                                        <i class="fa-solid {{ $action['icon'] }} text-sm" aria-hidden="true"></i>
                                    </span>
                                    <div>
                                        <p class="font-medium text-slate-900 dark:text-white">{{ $action['label'] }}</p>
                                        <p class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $action['hint'] }}</p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
