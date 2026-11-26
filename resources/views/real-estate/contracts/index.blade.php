<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-5xl px-4 space-y-4">
<div class="flex justify-between"><h1 class="text-2xl font-semibold">{{ __('Contracts') }}</h1><a href="{{ route('real-estate.contracts.create') }}"><x-primary-button type="button">{{ __('Add contract') }}</x-primary-button></a></div>
@forelse($contracts as $contract)
<div class="flow-panel p-5 text-sm flex flex-wrap justify-between gap-3">
<div>
<p class="font-semibold">{{ $contract->contract_type->label() }} · {{ $contract->status }}</p>
<p class="text-slate-500">{{ $contract->offer?->title ?: '—' }} · {{ $contract->client?->name ?: '—' }}</p>
</div>
<p class="font-semibold tabular-nums">{{ flowdesk_format_minor($contract->amount_minor, $contract->currency) }} {{ $contract->currency }}</p>
</div>
@empty
<p class="text-sm text-slate-500">{{ __('No contracts yet.') }}</p>
@endforelse
<div>{{ $contracts->links() }}</div>
</div></div>
</x-app-layout>
