@php
    $company = auth()->user()->company;
    $gates = $flowdeskPlanGates ?? [];
@endphp
<div class="space-y-6">

    <div class="flow-panel p-6 space-y-3">
        <h2 class="text-lg font-semibold">{{ __('Real estate hub') }}</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Offers, demands, gallery images and contracts (achat, vente, location, location vacance).') }}</p>
        @if($gates['real_estate'] ?? false)
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('real-estate.offers.index') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('Offers') }}</a>
                <a href="{{ route('real-estate.demands.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold dark:border-slate-600">{{ __('Demands') }}</a>
                <a href="{{ route('real-estate.contracts.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold dark:border-slate-600">{{ __('Contracts') }}</a>
                <a href="{{ route('clients.index') }}" class="rounded-lg border px-4 py-2 text-sm font-semibold dark:border-slate-600">{{ __('Clients') }}</a>
            </div>
        @else
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Activate Real estate from the module store to manage listings.') }}</p>
        @endif
    </div>
</div>
