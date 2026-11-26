<x-admin-layout :title="__('Overview')">
    @php
        $adminName = Auth::guard('admin')->user()?->name ?? '';
        $hour = now()->hour;
        $greeting = $hour < 12
            ? __('Good morning, :name', ['name' => $adminName])
            : ($hour < 18
                ? __('Good afternoon, :name', ['name' => $adminName])
                : __('Good evening, :name', ['name' => $adminName]));
        $today = now()->locale(app()->getLocale())->translatedFormat('l j F Y');

        $trendFor = function (int $current, int $previous): array {
            if ($previous === 0 && $current === 0) {
                return ['dir' => 'flat', 'label' => __('No movement this month')];
            }
            if ($previous === 0) {
                return ['dir' => 'up', 'label' => __('New this month: :count', ['count' => number_format($current)])];
            }
            $delta = $current - $previous;
            $pct = (int) round(abs($delta) / max($previous, 1) * 100);
            if ($delta > 0) {
                return ['dir' => 'up', 'label' => __('Up :percent% vs last month', ['percent' => $pct])];
            }
            if ($delta < 0) {
                return ['dir' => 'down', 'label' => __('Down :percent% vs last month', ['percent' => $pct])];
            }

            return ['dir' => 'flat', 'label' => __('Same as last month')];
        };
        $companiesTrend = $trendFor((int) $companiesThisMonth, (int) $companiesLastMonth);
        $revenueTrend = $trendFor((int) $revenueThisMonthMinor, (int) $revenueLastMonthMinor);
        $revenueThisMonthMajor = (int) round(((int) $revenueThisMonthMinor) / 100);
        $maxCompaniesByPlan = max(1, (int) collect($companiesByPlan ?? [])->max());
        $hasChartData = collect($reportsChart['companiesCreated'] ?? [])->sum() > 0
            || collect($reportsChart['counts'] ?? [])->sum() > 0
            || collect($reportsChart['paidMinor'] ?? [])->sum() > 0;
    @endphp

    {{-- Header --}}
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#EA580C]">{{ __('Platform administration') }}</p>
            <h1 class="mt-1 font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold tracking-tight text-slate-900">{{ $greeting }}</h1>
            <p class="mt-1.5 text-sm text-slate-500">
                <x-cf-icon name="fa-calendar-day" size="sm" class="me-1.5 text-[#EA580C]" />
                <time datetime="{{ now()->toDateString() }}">{{ $today }}</time>
            </p>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                {{ __('Manage customer companies, subscription plans, and invoice payments recorded across all workspaces. Company accounts run projects, quotes (proposals), and invoices on their own tenant.') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.companies.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#EA580C] px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-orange-600/25 transition hover:bg-[#C2410C]">
                <x-cf-icon name="fa-plus" size="sm" class="text-sm" />
                <span>{{ __('Create company') }}</span>
            </a>
            <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                <x-cf-icon name="fa-building" size="sm" class="text-xs" />
                <span>{{ __('Companies') }}</span>
            </a>
            <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                <x-cf-icon name="fa-credit-card" size="sm" class="text-xs" />
                <span>{{ __('Subscription plans') }}</span>
            </a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @php
            $kpis = [
                [
                    'label' => __('Companies'),
                    'value' => number_format($companiesCount),
                    'icon' => 'fa-solid fa-building', 'tone' => 'orange',
                    'trend' => $companiesTrend,
                    'href' => route('admin.companies.index'),
                ],
                [
                    'label' => __('Users'),
                    'value' => number_format($usersCount),
                    'icon' => 'fa-solid fa-users', 'tone' => 'violet',
                    'trend' => ['dir' => 'flat', 'label' => __('Workspace accounts')],
                    'href' => route('admin.users.index'),
                ],
                [
                    'label' => __('Active subscriptions'),
                    'value' => number_format($activeSubscriptions),
                    'icon' => 'fa-solid fa-circle-check', 'tone' => 'emerald',
                    'trend' => ['dir' => 'flat', 'label' => trans_choice(':count active trial|:count active trials', (int) $trialSubscriptions, ['count' => number_format($trialSubscriptions)])],
                    'href' => route('admin.subscription-orders.index'),
                ],
                [
                    'label' => __('Revenue this month'),
                    'value' => number_format($revenueThisMonthMajor),
                    'icon' => 'fa-solid fa-chart-line', 'tone' => 'sky',
                    'trend' => $revenueTrend,
                    'href' => route('admin.payments.index'),
                ],
                [
                    'label' => __('Estimated MRR'),
                    'value' => number_format((int) $mrrMajor),
                    'icon' => 'fa-solid fa-rotate', 'tone' => 'amber',
                    'trend' => ['dir' => 'flat', 'label' => __('From active paid plans')],
                    'href' => route('admin.plans.index'),
                ],
            ];
        @endphp

        @foreach ($kpis as $kpi)
            <a href="{{ $kpi['href'] }}" class="cf-kpi-link group rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm shadow-slate-900/5 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $kpi['label'] }}</p>
                    <x-cf-icon :name="$kpi['icon']" variant="tile" :tone="$kpi['tone'] ?? 'orange'" class="cf-stat-tile" />
                </div>
                <p class="mt-3 font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold tracking-tight text-slate-900">{{ $kpi['value'] }}</p>
                <p @class([
                    'mt-2 inline-flex items-center gap-1.5 text-xs font-medium',
                    'text-emerald-600' => $kpi['trend']['dir'] === 'up',
                    'text-rose-600' => $kpi['trend']['dir'] === 'down',
                    'text-slate-500' => $kpi['trend']['dir'] === 'flat',
                ])>
                    @if ($kpi['trend']['dir'] === 'up')
                        <x-cf-icon name="fa-arrow-trend-up" size="sm" class="" />
                    @elseif ($kpi['trend']['dir'] === 'down')
                        <x-cf-icon name="fa-arrow-trend-down" size="sm" class="" />
                    @endif
                    <span>{{ $kpi['trend']['label'] }}</span>
                </p>
            </a>
        @endforeach
    </div>

    {{-- Charts --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5 lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900">{{ __('Revenue (completed payments)') }}</h2>
                    <p class="mt-0.5 text-xs text-slate-500">{{ __('Last 12 months') }}</p>
                </div>
                <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-[#C2410C] hover:text-[#EA580C]">{{ __('Invoice payments') }}</a>
            </div>
            <div
                id="flowdesk-dashboard-charts-root"
                class="mt-5 grid gap-4 lg:grid-cols-2"
                data-chart='@json($reportsChart)'
                data-label-invoices="{{ __('Payments') }}"
                data-label-paid="{{ __('Revenue (major units)') }}"
                data-label-companies="{{ __('Companies created') }}"
            >
                @if (! $hasChartData)
                    <div class="lg:col-span-2 flex h-[220px] flex-col items-center justify-center rounded-2xl border border-dashed border-orange-200 bg-orange-50/40 px-6 text-center">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white text-[#EA580C] shadow-sm ring-1 ring-orange-100">
                            <x-cf-icon name="fa-chart-column" size="sm" class="" />
                        </span>
                        <p class="mt-3 text-sm font-semibold text-slate-800">{{ __('No revenue data yet.') }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ __('Completed payments will appear here over the last 12 months.') }}</p>
                    </div>
                @else
                    <div class="h-[260px] rounded-2xl border border-slate-100 bg-slate-50/60 p-3">
                        <canvas id="chart-dashboard-invoices"></canvas>
                    </div>
                    <div class="h-[260px] rounded-2xl border border-slate-100 bg-slate-50/60 p-3">
                        <canvas id="chart-dashboard-revenue"></canvas>
                    </div>
                @endif
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
            <h2 class="text-base font-semibold text-slate-900">{{ __('New companies') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Last 12 months') }}</p>
            @if (collect($reportsChart['companiesCreated'] ?? [])->sum() === 0)
                <div class="mt-5 flex h-[280px] flex-col items-center justify-center rounded-2xl border border-dashed border-orange-200 bg-orange-50/40 px-6 text-center">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-white text-[#EA580C] shadow-sm ring-1 ring-orange-100">
                        <x-cf-icon name="fa-building" size="sm" class="" />
                    </span>
                    <p class="mt-3 text-sm font-semibold text-slate-800">{{ __('No companies yet.') }}</p>
                    <a href="{{ route('admin.companies.create') }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[#C2410C] hover:text-[#EA580C]">
                        <x-cf-icon name="fa-plus" size="sm" class="text-xs" />
                        {{ __('Create the first workspace') }}
                    </a>
                </div>
            @else
                <div class="mt-5 h-[280px]">
                    <canvas id="chart-admin-companies"></canvas>
                </div>
            @endif
        </div>
    </div>

    {{-- Recent companies --}}
    <div class="mt-8 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Recent companies') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Latest workspaces created on the platform.') }}</p>
            </div>
            <a href="{{ route('admin.companies.index') }}" class="text-sm font-semibold text-[#C2410C] hover:text-[#EA580C]">{{ __('View all') }}</a>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full table-fixed text-start text-sm">
                <thead class="border-b border-slate-100 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-3 text-start">{{ __('Company') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Plan') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Users') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Created') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($recentCompanies as $company)
                        <tr class="hover:bg-orange-50/40">
                            <td class="px-3 py-3 text-start">
                                <a href="{{ route('admin.companies.show', $company) }}" class="font-semibold text-slate-900 hover:text-[#EA580C]">{{ $company->name }}</a>
                                <div class="font-mono text-xs text-slate-500">{{ $company->subdomain }}</div>
                            </td>
                            <td class="px-3 py-3 text-start text-slate-700">{{ $company->plan?->name ?? __('No plan') }}</td>
                            <td class="px-3 py-3 text-start">
                                @if ($company->is_enabled)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Enabled') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                        {{ __('Disabled') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-end tabular-nums text-slate-700">{{ number_format((int) $company->users_count) }}</td>
                            <td class="px-3 py-3 text-start text-slate-600">{{ optional($company->created_at)->locale(app()->getLocale())->translatedFormat('d M Y') }}</td>
                            <td class="px-3 py-3 text-end">
                                <a
                                    href="{{ route('admin.companies.show', $company) }}"
                                    class="inline-flex items-center justify-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-slate-700 shadow-sm transition hover:border-orange-200 hover:text-[#EA580C]"
                                    title="{{ __('View company') }}"
                                    aria-label="{{ __('View company') }}"
                                >
                                    <x-cf-icon name="fa-arrow-up-right-from-square" size="sm" class="text-xs" />
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-12 text-center">
                                <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-orange-50 text-[#EA580C] ring-1 ring-orange-100">
                                    <x-cf-icon name="fa-building" size="sm" class="" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-slate-800">{{ __('No companies yet.') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Create the first workspace to get started.') }}</p>
                                <a href="{{ route('admin.companies.create') }}" class="mt-4 inline-flex items-center gap-2 rounded-xl bg-[#EA580C] px-4 py-2 text-sm font-semibold text-white hover:bg-[#C2410C]">
                                    <x-cf-icon name="fa-plus" size="sm" class="text-xs" />
                                    {{ __('Create company') }}
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Plan breakdowns --}}
    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
            <h2 class="text-base font-semibold text-slate-900">{{ __('Revenue by plan') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Completed payments grouped by current company plan.') }}</p>

            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-100">
                <table class="min-w-full table-fixed text-start divide-y divide-slate-100 text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-start">{{ __('Plan') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Active subscriptions') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Payments') }}</th>
                            <th class="px-4 py-3 text-end">{{ __('Revenue (major units)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse ($revenueByPlan as $row)
                            @php($subs = $subsByPlan[$row->plan_id] ?? null)
                            <tr class="hover:bg-orange-50/40">
                                <td class="px-4 py-3 text-start">
                                    <div class="font-semibold text-slate-900">{{ $row->plan_name ?? __('No plan') }}</div>
                                    <div class="font-mono text-xs text-slate-500">{{ $row->plan_slug ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ number_format((int) ($subs->subs_count ?? 0)) }}</td>
                                <td class="px-4 py-3 text-end tabular-nums">{{ number_format((int) ($row->payments_count ?? 0)) }}</td>
                                <td class="px-4 py-3 text-end font-semibold tabular-nums text-slate-900">
                                    {{ number_format((int) round(((int) ($row->total_minor ?? 0)) / 100), 0) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">{{ __('No data yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
            <h2 class="text-base font-semibold text-slate-900">{{ __('Companies by plan') }}</h2>
            <p class="mt-0.5 text-xs text-slate-500">{{ __('Current companies grouped by assigned plan.') }}</p>

            <div class="mt-4 space-y-2">
                @forelse ($plans as $p)
                    @php($count = (int) ($companiesByPlan[$p->id] ?? 0))
                    <div class="rounded-xl border border-slate-100 bg-slate-50/70 px-4 py-3">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <div class="font-semibold text-slate-900">{{ $p->name }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $p->slug }}</div>
                            </div>
                            <div class="font-semibold tabular-nums text-slate-900">{{ number_format($count) }}</div>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200/80">
                            <div class="h-full rounded-full bg-[#EA580C]" style="width: {{ max(0, min(100, (int) round(($count / $maxCompaniesByPlan) * 100))) }}%"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-dashed border-orange-200 bg-orange-50/40 px-4 py-10 text-center text-sm text-slate-500">
                        {{ __('No plans yet.') }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="mt-8 rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ __('Quick actions') }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Common admin tasks') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.companies.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-[#EA580C] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#C2410C]">
                    <x-cf-icon name="fa-plus" size="sm" class="text-sm" />
                    <span>{{ __('Create company') }}</span>
                </a>
                <a href="{{ route('admin.plans.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                    <x-cf-icon name="fa-credit-card" size="sm" class="text-xs" />
                    <span>{{ __('Subscription plans') }}</span>
                </a>
                <a href="{{ route('admin.payment-gateways.edit') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                    <x-cf-icon name="fa-plug-circle-bolt" size="sm" class="text-xs" />
                    <span>{{ __('Payment gateways') }}</span>
                </a>
                <a href="{{ route('admin.themes.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                    <x-cf-icon name="fa-palette" size="sm" class="text-xs" />
                    <span>{{ __('Theme library') }}</span>
                </a>
                <a href="{{ route('admin.email-template-models.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]">
                    <x-cf-icon name="fa-envelope-open" size="sm" class="text-xs" />
                    <span>{{ __('admin_email_template_models_nav') }}</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Shortcut cards --}}
    <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <a href="{{ route('admin.platform-appearance.edit') }}" class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-[#EA580C] ring-1 ring-orange-100">
                <x-cf-icon name="fa-palette" size="sm" class="" />
            </span>
            <h3 class="mt-4 font-semibold text-slate-900">{{ __('Workspace theme') }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ __('Default layout, colors, fonts, and CSS for all companies.') }}</p>
        </a>
        <a href="{{ route('admin.plans.index') }}" class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-[#EA580C] ring-1 ring-orange-100">
                <x-cf-icon name="fa-layer-group" size="sm" class="" />
            </span>
            <h3 class="mt-4 font-semibold text-slate-900">{{ __('Plans & features') }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ __('Pricing and per-plan feature limits (projects, users, AI credits, …).') }}</p>
        </a>
        <a href="{{ route('admin.companies.index') }}" class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-[#EA580C] ring-1 ring-orange-100">
                <x-cf-icon name="fa-building" size="sm" class="" />
            </span>
            <h3 class="mt-4 font-semibold text-slate-900">{{ __('Companies') }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ __('View workspaces and subscription status.') }}</p>
        </a>
        <a href="{{ route('admin.email-template-models.index') }}" class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5 transition hover:-translate-y-0.5 hover:border-orange-200 hover:shadow-md">
            <span class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-orange-50 text-[#EA580C] ring-1 ring-orange-100">
                <x-cf-icon name="fa-envelope-open" size="sm" class="" />
            </span>
            <h3 class="mt-4 font-semibold text-slate-900">{{ __('admin_email_template_models_title') }}</h3>
            <p class="mt-1.5 text-sm leading-relaxed text-slate-600">{{ __('admin_email_template_models_card_blurb') }}</p>
        </a>
    </div>

    <p class="mt-8 text-sm text-slate-500">
        {{ __('Use the navigation above. Your company workspace (projects, quotes, invoices) is separate — sign in with a company user on your tenant URL.') }}
    </p>
    @push('scripts')
        @vite(['resources/js/dashboard-charts.js'])
    @endpush
</x-admin-layout>
