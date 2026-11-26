<x-admin-layout :title="__('admin_marketplace_orders_title')">
    <div class="mb-6">
        <x-flow.show-action-button :href="route('admin.dashboard')">{{ __('Dashboard') }}</x-flow.show-action-button>
    </div>

    <x-flow.page-header :title="__('admin_marketplace_orders_title')" :description="__('admin_marketplace_orders_intro')" />

    <form method="GET" class="flow-panel mt-6 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label for="reference" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('admin_marketplace_orders_filter_reference') }}</label>
            <input
                id="reference"
                name="reference"
                type="search"
                value="{{ $selectedReference }}"
                placeholder="{{ __('admin_marketplace_orders_filter_reference_placeholder') }}"
                class="flow-input mt-1 min-w-[14rem] font-mono"
            />
        </div>
        <div>
            <label for="status" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Status') }}</label>
            <select id="status" name="status" class="flow-input-select mt-1">
                <option value="">{{ __('admin_marketplace_orders_filter_all') }}</option>
                @foreach ($statuses as $statusCase)
                    <option value="{{ $statusCase->value }}" @selected($selectedStatus === $statusCase->value)>{{ $statusCase->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">
            <i class="fa-solid fa-magnifying-glass text-xs" aria-hidden="true"></i>
            {{ __('Search') }}
        </button>
        @if ($selectedReference !== '' || $selectedStatus !== '')
            <a href="{{ route('admin.marketplace-orders.index') }}" class="text-sm font-semibold text-slate-400 hover:text-white">{{ __('Clear filters') }}</a>
        @endif
    </form>

    @if ($orders->isEmpty())
        <p class="mt-6 text-sm text-slate-400">{{ __('admin_marketplace_orders_empty') }}</p>
    @else
        <div class="flow-panel mt-8 overflow-hidden p-0">
            <x-flow.table>
                <thead class="bg-white/5 text-start text-xs font-semibold uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-3 py-3 text-start">{{ __('Order') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('admin_marketplace_order_payment_reference') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Customer') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Status') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Total') }}</th>
                        <th class="px-3 py-3 text-start">{{ __('Date') }}</th>
                        <th class="px-3 py-3 text-end">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/10 text-slate-200">
                    @foreach ($orders as $row)
                        <tr>
                            <td class="px-3 py-3 text-start">
                                <p class="font-mono text-xs font-semibold text-white">{{ $row->order_number }}</p>
                                <p class="text-xs text-slate-500">{{ trans_choice('marketing_cart_items_count', $row->items_count, ['count' => $row->items_count]) }}</p>
                            </td>
                            <td class="px-3 py-3 text-start">
                                <p class="font-mono text-xs font-bold text-[#a78bfa]">{{ $row->paymentReferenceLabel() }}</p>
                            </td>
                            <td class="px-3 py-3 text-start">
                                <p class="font-medium text-white">{{ $row->customer_name }}</p>
                                <p class="text-xs text-slate-500">{{ $row->customer_email }}</p>
                            </td>
                            <td class="px-3 py-3 text-start">
                                @php
                                    $statusClass = match ($row->status) {
                                        \App\Enums\MarketplaceOrderStatus::Paid => 'bg-emerald-500/15 text-emerald-300',
                                        \App\Enums\MarketplaceOrderStatus::Pending => 'bg-amber-500/15 text-amber-300',
                                        \App\Enums\MarketplaceOrderStatus::Cancelled => 'bg-slate-500/20 text-slate-300',
                                        default => 'bg-rose-500/15 text-rose-300',
                                    };
                                @endphp
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">{{ $row->status->label() }}</span>
                            </td>
                            <td class="px-3 py-3 text-end font-semibold"><span class="flowdesk-ltr-num tabular-nums font-semibold">{{ $row->formattedTotal() }}</span></td>
                            <td class="px-3 py-3 text-slate-400 text-start">{{ $row->created_at?->translatedFormat('j M Y H:i') }}</td>
                            <td class="px-3 py-3 text-end">
                                <a href="{{ route('admin.marketplace-orders.show', $row) }}" class="flow-secondary-button inline-flex items-center rounded-lg border px-3 py-1.5 text-xs font-semibold shadow-sm">{{ __('View') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-flow.table>
        </div>

        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-admin-layout>
