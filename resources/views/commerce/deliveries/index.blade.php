<x-app-layout>
    <div class="py-8"><div class="mx-auto max-w-5xl px-4 space-y-4">
        <h1 class="text-2xl font-semibold">{{ __('Deliveries') }}</h1>
        <div class="space-y-4">
            @forelse($deliveries as $delivery)
                <div class="flow-panel p-5">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ __('Order') }} {{ $delivery->commerce_order_id }}</p>
                            <p class="text-sm text-slate-500">{{ __('Status') }}: {{ $delivery->status }}</p>
                        </div>
                        <form method="POST" action="{{ route('commerce.deliveries.update', $delivery) }}" class="flex flex-wrap gap-2 items-end">
                            @csrf @method('PUT')
                            <select name="status" class="rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800">
                                @foreach(['pending','shipped','delivered','cancelled'] as $st)
                                    <option value="{{ $st }}" @selected($delivery->status===$st)>{{ $st }}</option>
                                @endforeach
                            </select>
                            <x-text-input name="carrier" :value="$delivery->carrier" placeholder="{{ __('Carrier') }}" class="text-sm" />
                            <x-text-input name="tracking_number" :value="$delivery->tracking_number" placeholder="{{ __('Tracking') }}" class="text-sm" />
                            <x-secondary-button>{{ __('Save') }}</x-secondary-button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">{{ __('No deliveries yet.') }}</p>
            @endforelse
        </div>
        <div>{{ $deliveries->links() }}</div>
    </div></div>
</x-app-layout>
