<div class="space-y-4 p-1">
    <div>
        <h1 class="text-2xl font-semibold text-slate-900 dark:text-white">{{ __('retail-pos.title') }}</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('retail-pos.lead') }}</p>
    </div>

    @if($gates['ecommerce'] ?? false)
        @if(($gates['pos'] ?? false) && \Illuminate\Support\Facades\Route::has('pos.index'))
            <a href="{{ route('pos.index') }}" class="inline-flex rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-slate-950 hover:bg-amber-400">
                {{ __('Open fullscreen POS') }}
            </a>
        @endif
        @if(\Illuminate\Support\Facades\Route::has('commerce.products.index'))
            <a href="{{ route('commerce.products.index') }}" class="ms-2 inline-flex text-sm font-semibold text-indigo-600">{{ __('Manage products') }} →</a>
        @endif
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-100">
            <p class="font-semibold">{{ __('Products module required') }}</p>
            <p class="mt-1">{{ __('POS requires the E-commerce pack (products, inventory & POS).') }}</p>
            <a href="{{ route('modules.show', ['slug' => 'ecommerce']) }}" class="mt-3 inline-flex font-semibold underline">{{ __('Get E-commerce pack') }}</a>
        </div>
    @endif
</div>
