<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-2xl px-4">
<form method="POST" action="{{ route('real-estate.demands.store') }}" class="flow-panel p-6 space-y-4">
@csrf
<h1 class="text-lg font-semibold">{{ __('Add demand') }}</h1>
<select name="client_id" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600"><option value="">{{ __('Client') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
<div><p class="text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Property types') }}</p>@foreach($propertyTypes as $t)<label class="mr-3 text-sm"><input type="checkbox" name="property_types[]" value="{{ $t->value }}"> {{ $t->label() }}</label>@endforeach</div>
<div><p class="text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Transactions') }}</p>@foreach($transactionTypes as $t)<label class="mr-3 text-sm"><input type="checkbox" name="transaction_types[]" value="{{ $t->value }}"> {{ $t->label() }}</label>@endforeach</div>
<div class="grid gap-3 sm:grid-cols-3">
<x-text-input name="budget_min" type="number" step="0.01" placeholder="{{ __('Min') }}" />
<x-text-input name="budget_max" type="number" step="0.01" placeholder="{{ __('Max') }}" />
<x-text-input name="currency" :value="auth()->user()->company->default_currency ?? flowdesk_default_currency()" maxlength="3" required />
</div>
<textarea name="notes" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600" placeholder="{{ __('Notes') }}"></textarea>
<x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
</div></div>
</x-app-layout>
