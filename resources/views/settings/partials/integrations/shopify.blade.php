@php
    $integration = $integration ?? null;
    $logs = $logs ?? collect();
@endphp

<div class="space-y-5">
    <form method="POST" action="{{ route('settings.shopify.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <x-input-label for="shopify_shop_domain" :value="__('Shop domain')" />
            <x-text-input id="shopify_shop_domain" name="shop_domain" class="mt-1 w-full" :value="old('shop_domain', $integration?->credentials['shop_domain'] ?? '')" placeholder="mystore.myshopify.com" required />
        </div>
        <div>
            <x-input-label for="shopify_access_token" :value="__('Admin API access token')" />
            <x-text-input id="shopify_access_token" name="access_token" class="mt-1 w-full" :value="old('access_token', $integration?->credentials['access_token'] ?? '')" required />
        </div>
        <label class="inline-flex gap-2 text-sm">
            <input type="checkbox" name="auto_push" value="1" @checked(old('auto_push', $integration?->auto_push ?? true))>
            {{ __('Auto-push price & stock changes') }}
        </label>
        <x-primary-button>{{ __('Save') }}</x-primary-button>
    </form>

    <form method="POST" action="{{ route('settings.shopify.sync') }}">@csrf<x-secondary-button>{{ __('integrations_import_now') }}</x-secondary-button></form>

    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Recent sync logs') }}</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($logs as $log)
                <li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>
            @empty
                <li class="text-slate-500">{{ __('No logs yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
