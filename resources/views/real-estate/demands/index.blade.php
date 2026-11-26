<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-5xl px-4 space-y-4">
<div class="flex justify-between"><h1 class="text-2xl font-semibold">{{ __('Demands') }}</h1><a href="{{ route('real-estate.demands.create') }}"><x-primary-button type="button">{{ __('Add demand') }}</x-primary-button></a></div>
@forelse($demands as $demand)
<div class="flow-panel p-5 text-sm">
<p class="font-semibold">{{ $demand->client?->name ?: __('Open demand') }} · {{ $demand->status }}</p>
<p class="text-slate-500 mt-1">{{ implode(', ', $demand->property_types ?? []) }} · {{ implode(', ', $demand->transaction_types ?? []) }}</p>
<p class="mt-1">{{ __('Budget') }}:
@if($demand->budget_min_minor){{ flowdesk_format_minor($demand->budget_min_minor, $demand->currency) }}@else—@endif
–
@if($demand->budget_max_minor){{ flowdesk_format_minor($demand->budget_max_minor, $demand->currency) }} {{ $demand->currency }}@else—@endif
</p>
@if($demand->notes)<p class="mt-2">{{ $demand->notes }}</p>@endif
</div>
@empty
<p class="text-sm text-slate-500">{{ __('No demands yet.') }}</p>
@endforelse
<div>{{ $demands->links() }}</div>
</div></div>
</x-app-layout>
