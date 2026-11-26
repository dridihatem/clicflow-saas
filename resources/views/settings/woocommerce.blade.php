<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-2xl px-4 space-y-6">
<h1 class="flex items-center gap-3 text-2xl font-semibold"><x-integration-logo name="woocommerce" /> {{ __('WooCommerce sync') }}</h1>
@if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('settings.woocommerce.update') }}" class="flow-panel p-6 space-y-4">
@csrf @method('PUT')
<div><x-input-label for="store_url" :value="__('Store URL')" /><x-text-input id="store_url" name="store_url" class="mt-1 w-full" :value="old('store_url', $integration?->credentials['store_url'] ?? '')" placeholder="https://shop.example.com" required /></div>
<div><x-input-label for="consumer_key" :value="__('Consumer key')" /><x-text-input id="consumer_key" name="consumer_key" class="mt-1 w-full" :value="old('consumer_key', $integration?->credentials['consumer_key'] ?? '')" required /></div>
<div><x-input-label for="consumer_secret" :value="__('Consumer secret')" /><x-text-input id="consumer_secret" name="consumer_secret" class="mt-1 w-full" :value="old('consumer_secret', $integration?->credentials['consumer_secret'] ?? '')" required /></div>
<label class="inline-flex gap-2 text-sm"><input type="checkbox" name="auto_push" value="1" @checked(old('auto_push', $integration?->auto_push ?? true))> {{ __('Auto-push price & stock changes') }}</label>
<x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
<form method="POST" action="{{ route('settings.woocommerce.sync') }}">@csrf<x-secondary-button>{{ __('integrations_import_now') }}</x-secondary-button></form>
<div class="flow-panel p-6">
<h2 class="font-semibold mb-3">{{ __('Recent sync logs') }}</h2>
<ul class="space-y-2 text-sm">@forelse($logs as $log)<li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>@empty<li class="text-slate-500">{{ __('No logs yet.') }}</li>@endforelse</ul>
</div>
</div></div>
</x-app-layout>
