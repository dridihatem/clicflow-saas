<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-2xl px-4">
<form method="POST" action="{{ route('real-estate.offers.store') }}" class="flow-panel p-6 space-y-4">
@csrf
<h1 class="text-lg font-semibold">{{ __('Add offer') }}</h1>
<div><x-input-label for="title" :value="__('Title')" /><x-text-input id="title" name="title" class="mt-1 w-full" required /></div>
<div class="grid gap-4 sm:grid-cols-2">
<div><x-input-label for="property_type" :value="__('Property type')" /><select id="property_type" name="property_type" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600" required>@foreach($propertyTypes as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
<div><x-input-label for="transaction_type" :value="__('Transaction')" /><select id="transaction_type" name="transaction_type" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600" required>@foreach($transactionTypes as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select></div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
<div><x-input-label for="price" :value="__('Price')" /><x-text-input id="price" name="price" type="number" step="0.01" class="mt-1 w-full" required /></div>
<div><x-input-label for="currency" :value="__('Currency')" /><x-text-input id="currency" name="currency" class="mt-1 w-full" :value="auth()->user()->company->default_currency ?? flowdesk_default_currency()" maxlength="3" required /></div>
</div>
<div><x-input-label for="address" :value="__('Address')" /><x-text-input id="address" name="address" class="mt-1 w-full" /></div>
<div><x-input-label for="city" :value="__('City')" /><x-text-input id="city" name="city" class="mt-1 w-full" /></div>
<div><x-input-label for="client_id" :value="__('Client')" /><select id="client_id" name="client_id" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600"><option value="">—</option>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
<div><x-input-label for="description" :value="__('Description')" /><textarea id="description" name="description" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600" placeholder="{{ __('Description') }}"></textarea></div>
<input type="hidden" name="status" value="published">
<x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
</div></div>
</x-app-layout>
