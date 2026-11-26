@php
    $company = auth()->user()->company;
    $gates = $flowdeskPlanGates ?? [];
@endphp
<div class="space-y-6">

    <div class="flow-panel p-6 space-y-3">
        <h2 class="text-lg font-semibold">{{ __('WooCommerce sync companion') }}</h2>
        <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('Connect WooCommerce REST keys to sync products, prices and stock with FlowDesk.') }}</p>
        <ol class="list-decimal ps-5 text-sm space-y-1 text-slate-600 dark:text-slate-300">
            <li>{{ __('WooCommerce → Settings → Advanced → REST API → Add key') }}</li>
            <li>{{ __('Copy Consumer key & secret and your store URL') }}</li>
            <li>{{ __('Save in FlowDesk, then Pull products') }}</li>
        </ol>
        @if($gates['woocommerce'] ?? false)
            <a href="{{ route('settings.woocommerce') }}" class="inline-flex rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">{{ __('Open WooCommerce settings') }}</a>
        @else
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ __('Purchase WooCommerce sync (or enable the plan feature) to connect your store.') }}</p>
        @endif
    </div>
</div>
