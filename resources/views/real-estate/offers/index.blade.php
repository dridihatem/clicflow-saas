<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-6xl px-4 space-y-4">
    <div class="flex justify-between items-center gap-3">
        <h1 class="text-2xl font-semibold">{{ __('Real estate offers') }}</h1>
        <a href="{{ route('real-estate.offers.create') }}"><x-primary-button type="button">{{ __('Add offer') }}</x-primary-button></a>
    </div>
    <form method="GET" class="flex flex-wrap gap-2">
        <select name="property_type" class="rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600">
            <option value="">{{ __('All types') }}</option>
            @foreach($propertyTypes as $t)<option value="{{ $t->value }}" @selected(request('property_type')===$t->value)>{{ $t->label() }}</option>@endforeach
        </select>
        <select name="transaction_type" class="rounded-md border-slate-300 text-sm dark:bg-slate-800 dark:border-slate-600">
            <option value="">{{ __('All transactions') }}</option>
            @foreach($transactionTypes as $t)<option value="{{ $t->value }}" @selected(request('transaction_type')===$t->value)>{{ $t->label() }}</option>@endforeach
        </select>
        <x-secondary-button>{{ __('Filter') }}</x-secondary-button>
    </form>
    <div class="flow-panel overflow-hidden p-0">
        <x-flow.table>
            <thead><tr class="text-xs uppercase text-slate-500">
                <th class="px-4 py-3 text-start">{{ __('Title') }}</th>
                <th class="px-4 py-3 text-start">{{ __('Type') }}</th>
                <th class="px-4 py-3 text-start">{{ __('Transaction') }}</th>
                <th class="px-4 py-3 text-start">{{ __('Price') }}</th>
                <th class="px-4 py-3 text-end">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody class="divide-y">
            @forelse($offers as $offer)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $offer->title }}</td>
                    <td class="px-4 py-3">{{ $offer->property_type->label() }}</td>
                    <td class="px-4 py-3">{{ $offer->transaction_type->label() }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ flowdesk_format_minor($offer->price_minor, $offer->currency) }} {{ $offer->currency }}</td>
                    <td class="px-4 py-3 text-end"><a href="{{ route('real-estate.offers.show', $offer) }}" class="text-indigo-600 text-sm font-semibold">{{ __('View') }}</a></td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('No offers yet.') }}</td></tr>
            @endforelse
            </tbody>
        </x-flow.table>
    </div>
    <div>{{ $offers->links() }}</div>
</div></div>
</x-app-layout>
