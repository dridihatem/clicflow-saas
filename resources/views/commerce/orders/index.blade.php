<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold">{{ __('Orders') }}</h2></x-slot>
    <div class="py-8"><div class="mx-auto max-w-6xl px-4 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">{{ __('Commerce orders') }}</h1>
                <p class="text-sm text-slate-500">{{ __('Paid invoice revenue') }}: <span class="font-semibold tabular-nums">{{ flowdesk_format_minor($paidRevenue, auth()->user()->company->default_currency ?? flowdesk_default_currency()) }}</span></p>
            </div>
            <a href="{{ route('commerce.orders.create') }}"><x-primary-button type="button">{{ __('New order') }}</x-primary-button></a>
        </div>
        <div class="flow-panel overflow-hidden p-0">
            <x-flow.table>
                <thead><tr class="text-xs uppercase text-slate-500">
                    <th class="px-4 py-3 text-start">{{ __('Date') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Source') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Client') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Total') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('Invoice') }}</th>
                    <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody class="divide-y">
                @forelse($orders as $order)
                    <tr>
                        <td class="px-4 py-3 text-sm">{{ $order->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $order->source }}</td>
                        <td class="px-4 py-3">{{ $order->client?->name ?: '—' }}</td>
                        <td class="px-4 py-3 tabular-nums">{{ flowdesk_format_minor($order->total_minor, $order->currency) }} {{ $order->currency }}</td>
                        <td class="px-4 py-3">@if($order->invoice)<a class="text-indigo-600" href="{{ route('invoices.show', $order->invoice) }}">{{ $order->invoice->number ?: $order->invoice->id }}</a>@else—@endif</td>
                        <td class="px-4 py-3 text-end"><a class="text-sm font-semibold text-indigo-600" href="{{ route('commerce.orders.show', $order) }}">{{ __('View') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No orders yet.') }}</td></tr>
                @endforelse
                </tbody>
            </x-flow.table>
        </div>
        <div>{{ $orders->links() }}</div>
    </div></div>
</x-app-layout>
