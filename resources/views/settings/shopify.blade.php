<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-2xl px-4 space-y-6">
<h1 class="flex items-center gap-3 text-2xl font-semibold"><x-integration-logo name="shopify" /> {{ __('Shopify sync') }}</h1>
@if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('settings.shopify.update') }}" class="flow-panel p-6 space-y-4">
@csrf @method('PUT')
<div><x-input-label for="shop_domain" :value="__('Shop domain')" /><x-text-input id="shop_domain" name="shop_domain" class="mt-1 w-full" :value="old('shop_domain', $integration?->credentials['shop_domain'] ?? '')" placeholder="mystore.myshopify.com" required /></div>
<div><x-input-label for="access_token" :value="__('Admin API access token')" /><x-text-input id="access_token" name="access_token" class="mt-1 w-full" :value="old('access_token', $integration?->credentials['access_token'] ?? '')" required /></div>
<label class="inline-flex gap-2 text-sm"><input type="checkbox" name="auto_push" value="1" @checked(old('auto_push', $integration?->auto_push ?? true))> {{ __('Auto-push price & stock changes') }}</label>
<x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
<form method="POST" action="{{ route('settings.shopify.sync') }}">@csrf<x-secondary-button>{{ __('integrations_import_now') }}</x-secondary-button></form>
<div class="flow-panel p-6">
<h2 class="font-semibold mb-3">{{ __('Recent sync logs') }}</h2>
<ul class="space-y-2 text-sm">@forelse($logs as $log)<li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>@empty<li class="text-slate-500">{{ __('No logs yet.') }}</li>@endforelse</ul>
</div>
</div></div>
</x-app-layout>
