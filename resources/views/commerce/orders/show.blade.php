<x-app-layout>
    <div class="py-8"><div class="mx-auto max-w-3xl px-4 space-y-4">
        <h1 class="text-2xl font-semibold">{{ __('Order') }} {{ $order->id }}</h1>
        @if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</div>@endif
        <div class="flow-panel p-6 space-y-3 text-sm">
            <p>{{ __('Source') }}: <strong>{{ $order->source }}</strong></p>
            <p>{{ __('Total') }}: <strong class="tabular-nums">{{ flowdesk_format_minor($order->total_minor, $order->currency) }} {{ $order->currency }}</strong></p>
            <ul class="divide-y">
                @foreach($order->items as $item)
                    <li class="py-2 flex justify-between gap-4"><span>{{ $item->description }} × {{ $item->quantity }}</span><span class="tabular-nums">{{ flowdesk_format_minor($item->total_minor, $order->currency) }}</span></li>
                @endforeach
            </ul>
            @if(!$order->invoice_id)
                <form method="POST" action="{{ route('commerce.orders.invoice', $order) }}">@csrf<x-primary-button>{{ __('Create invoice') }}</x-primary-button></form>
            @else
                <a class="text-indigo-600 font-semibold" href="{{ route('invoices.show', $order->invoice) }}">{{ __('View invoice') }}</a>
            @endif
        </div>
    </div></div>
</x-app-layout>
