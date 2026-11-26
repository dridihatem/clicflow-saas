<x-admin-layout :title="__('Companies')">
    <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#EA580C]">{{ __('Platform administration') }}</p>
            <h1 class="mt-1 font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold tracking-tight text-slate-900">{{ __('Companies') }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                {{ __('Each company is an isolated workspace: team members manage clients, projects, proposals (quotes), and invoices.') }}
            </p>
        </div>
        <a
            href="{{ route('admin.companies.create') }}"
            class="inline-flex items-center gap-2 rounded-xl bg-[#EA580C] px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-orange-600/25 transition hover:bg-[#C2410C]"
        >
            <x-cf-icon name="plus" size="sm" />
            <span>{{ __('Create company') }}</span>
        </a>
    </div>

    <div class="mt-6 flex flex-wrap gap-2">
        @php
            $filters = [
                'all' => __('All'),
                'active' => __('Active subscription'),
                'ended' => __('Ended subscription'),
            ];
        @endphp
        @foreach ($filters as $key => $label)
            <a
                href="{{ route('admin.companies.index', ['subscription' => $key]) }}"
                @class([
                    'inline-flex items-center rounded-xl border px-3.5 py-1.5 text-sm font-semibold transition',
                    'border-[#EA580C] bg-[#EA580C] text-white shadow-sm shadow-orange-600/20' => $subscriptionFilter === $key,
                    'border-slate-200 bg-white text-slate-700 hover:border-orange-200 hover:text-[#C2410C]' => $subscriptionFilter !== $key,
                ])
            >{{ $label }}</a>
        @endforeach
    </div>

    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-sm shadow-slate-900/5">
        <x-flow.table>
            <thead class="bg-[#FFF7ED] text-start text-xs font-semibold uppercase tracking-wide text-orange-900/70">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('Name') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Subdomain') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Plan') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Subscription') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Period end') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Users') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Created') }}</th>
                    <th class="px-4 py-3 text-start"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 text-slate-800">
                @forelse ($companies as $company)
                    @php
                        $latestSub = $company->subscriptions->first();
                        $ended = $latestSub?->isEnded() ?? false;
                    @endphp
                    <tr class="transition hover:bg-orange-50/40">
                        <td class="px-4 py-3 font-medium text-start">
                            <a href="{{ route('admin.companies.show', $company) }}" class="hover:text-[#C2410C]">{{ $company->name }}</a>
                        </td>
                        <td class="px-4 py-3 font-mono text-sm text-start text-slate-600">{{ $company->subdomain }}</td>
                        <td class="px-4 py-3 text-sm text-start">{{ $company->plan?->name ?? $latestSub?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-start">
                            @if ($latestSub)
                                <span @class([
                                    'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' => $ended,
                                    'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' => ! $ended,
                                ])>
                                    {{ $latestSub->lifecycleLabel() }}
                                </span>
                            @else
                                <span class="text-sm text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-start tabular-nums">
                            @if ($latestSub?->current_period_end)
                                <span @class(['text-rose-600 font-semibold' => $ended && $latestSub->current_period_end->isPast()])>
                                    {{ $latestSub->current_period_end->format('Y-m-d') }}
                                </span>
                            @elseif ($latestSub?->trial_ends_at)
                                <span @class(['text-rose-600 font-semibold' => $ended && $latestSub->trial_ends_at->isPast()])>
                                    {{ $latestSub->trial_ends_at->format('Y-m-d') }}
                                    <span class="text-xs text-slate-400">({{ __('trial') }})</span>
                                </span>
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 text-start tabular-nums">{{ number_format($company->users_count) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-500 text-start">{{ $company->created_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-end">
                            <a
                                href="{{ route('admin.companies.show', $company) }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-orange-50 hover:text-[#EA580C]"
                                title="{{ __('View company') }}"
                            >
                                <x-cf-icon name="eye" size="sm" />
                                <span class="sr-only">{{ __('View company') }}</span>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-12 text-center text-sm text-slate-500">{{ __('No companies found.') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </x-flow.table>
    </div>
    <div class="mt-6">{{ $companies->links() }}</div>
</x-admin-layout>
