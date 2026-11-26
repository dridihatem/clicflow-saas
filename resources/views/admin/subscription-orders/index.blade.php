<x-admin-layout :title="__('admin_subscription_orders_title')">
    <div class="mb-6">
        <x-flow.show-action-button :href="route('admin.dashboard')">{{ __('Dashboard') }}</x-flow.show-action-button>
    </div>

    <x-flow.page-header :title="__('admin_subscription_orders_title')" :description="__('admin_subscription_orders_intro')" />

    <form method="GET" class="flow-panel mt-6 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label for="reference" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Reference') }}</label>
            <input id="reference" name="reference" type="search" value="{{ $selectedReference }}" class="flow-input mt-1 min-w-[14rem] font-mono" />
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</label>
            <select id="status" name="status" class="flow-input-select mt-1">
                <option value="">{{ __('All') }}</option>
                @foreach ($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}" @selected($selectedStatus === $statusCase->value)>{{ $statusCase->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">{{ __('Search') }}</button>
    </form>

    @if ($orders->isEmpty())
        <p class="mt-6 text-sm text-slate-400">{{ __('admin_subscription_orders_empty') }}</p>
    @else
        <div class="flow-panel mt-8 overflow-hidden p-0">
            <x-flow.table>
                <thead class="bg-white/5 text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-3 py-3 text-start">{{ __('Order') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Company') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Plan') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Method') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Total') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-slate-200">
                    @foreach ($orders as $row)
                        <tr>
                            <td class="px-3 py-3 font-mono text-xs font-semibold text-white">{{ $row->order_number }}</td>
                            <td class="px-3 py-3">{{ $row->company?->name }}</td>
                            <td class="px-3 py-3">{{ $row->plan?->name }} · {{ $row->period_months }} mo</td>
                            <td class="px-3 py-3">{{ $row->payment_method ?: '—' }}</td>
                            <td class="px-3 py-3">{{ $row->status->label() }}</td>
                            <td class="px-3 py-3 text-end tabular-nums font-semibold text-white">{{ flowdesk_format_minor($row->amount_minor, $row->currency) }} {{ $row->currency }}</td>
                            <td class="px-3 py-3 text-end">
                                <a href="{{ route('admin.subscription-orders.show', $row) }}" class="flow-secondary-button inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-flow.table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-admin-layout>
