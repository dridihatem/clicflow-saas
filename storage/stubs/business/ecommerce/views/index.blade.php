<div class="space-y-4 p-1">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ __('ecommerce.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('ecommerce.lead') }}</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ __('Products') }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Catalog with color/size variants.') }}</p>
            @if(($gates['ecommerce'] ?? false) && \Illuminate\Support\Facades\Route::has('commerce.products.index'))
                <a href="{{ route('commerce.products.index') }}" class="mt-3 inline-flex text-sm font-semibold text-indigo-600">{{ __('Open products') }} →</a>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">{{ __('Inventory') }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Stock tracked on every sale.') }}</p>
            @if(($gates['ecommerce'] ?? false) && \Illuminate\Support\Facades\Route::has('commerce.orders.index'))
                <a href="{{ route('commerce.orders.index') }}" class="mt-3 inline-flex text-sm font-semibold text-indigo-600">{{ __('Open orders') }} →</a>
            @endif
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">{{ __('Retail POS') }}</p>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('Fullscreen register for cash & card.') }}</p>
            @if(($gates['pos'] ?? false) && \Illuminate\Support\Facades\Route::has('pos.index'))
                <a href="{{ route('pos.index') }}" class="mt-3 inline-flex text-sm font-semibold text-amber-600">{{ __('Open POS') }} →</a>
            @elseif(!($gates['ecommerce'] ?? false))
                <p class="mt-3 text-xs text-rose-600">{{ __('Add the E-commerce pack to unlock products and POS.') }}</p>
            @endif
        </div>
    </div>
</div>
