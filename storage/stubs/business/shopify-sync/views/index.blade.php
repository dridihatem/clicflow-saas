@php
    $company = auth()->user()->company;
    $gates = $flowdeskPlanGates ?? [];
@endphp
<div class="space-y-6">

    <div class="flow-panel p-6 space-y-3">
        <h2 class="text-lg font-semibold">{{ __('Shopify sync companion') }}</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Connect your Shopify Admin API to pull products and push price/inventory updates from FlowDesk.') }}</p>
        <ol class="list-decimal ps-5 text-sm space-y-1 text-slate-600 dark:text-slate-300">
            <li>{{ __('Create a custom app in Shopify Admin → Apps') }}</li>
            <li>{{ __('Copy the shop domain and Admin API access token') }}</li>
            <li>{{ __('Paste them in FlowDesk Shopify settings, then Pull products') }}</li>
        </ol>
        @if($gates['shopify'] ?? false)
            <a href="{{ route('settings.shopify') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('Open Shopify settings') }}</a>
        @else
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Purchase Shopify sync (or enable the plan feature) to connect your store.') }}</p>
        @endif
    </div>
</div>
