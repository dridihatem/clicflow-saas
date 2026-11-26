<x-app-layout>
    <div class="py-8"><div class="mx-auto max-w-xl px-4">
        <form method="POST" action="{{ route('commerce.orders.store') }}" class="flow-panel space-y-4 p-6">
            @csrf
            <h1 class="text-lg font-semibold">{{ __('New commerce order') }}</h1>
            @if($errors->any())<p class="text-sm text-rose-600">{{ $errors->first() }}</p>@endif
            <div>
                <x-input-label for="variant_id" :value="__('Product variant')" />
                <select id="variant_id" name="variant_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800" required>
                    @foreach($variants as $variant)
                        <option value="{{ $variant->id }}">{{ $variant->label() }} — {{ $variant->stock_qty }} {{ __('in stock') }}</option>
                    @endforeach
                </select>
            </div>
            <div><x-input-label for="quantity" :value="__('Quantity')" /><x-text-input id="quantity" name="quantity" type="number" min="1" value="1" class="mt-1 w-full" required /></div>
            <div>
                <x-input-label for="client_id" :value="__('Client')" />
                <select id="client_id" name="client_id" class="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                    <option value="">{{ __('Walk-in / none') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" data-phone="{{ $client->phone }}" data-email="{{ $client->email }}">{{ $client->name }}</option>
                    @endforeach
                </select>
            </div>
            <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="create_invoice" value="1" checked> {{ __('Create invoice') }}</label>
            <label class="inline-flex gap-2 text-sm"><input type="checkbox" name="create_delivery" value="1"> {{ __('Create delivery') }}</label>
            <x-primary-button>{{ __('Create order') }}</x-primary-button>
        </form>
    </div></div>
</x-app-layout>
