<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-2xl px-4">
<form method="POST" action="{{ route('real-estate.contracts.store') }}" enctype="multipart/form-data" class="flow-panel p-6 space-y-4">
@csrf
<h1 class="text-lg font-semibold">{{ __('Add contract') }}</h1>
<select name="contract_type" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600" required>@foreach($contractTypes as $t)<option value="{{ $t->value }}">{{ $t->label() }}</option>@endforeach</select>
<select name="real_estate_offer_id" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600"><option value="">{{ __('Offer') }}</option>@foreach($offers as $o)<option value="{{ $o->id }}">{{ $o->title }}</option>@endforeach</select>
<select name="client_id" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600"><option value="">{{ __('Client') }}</option>@foreach($clients as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
<div class="grid gap-3 sm:grid-cols-2">
<x-text-input name="amount" type="number" step="0.01" placeholder="{{ __('Amount') }}" required />
<x-text-input name="currency" :value="auth()->user()->company->default_currency ?? flowdesk_default_currency()" maxlength="3" required />
</div>
<div class="grid gap-3 sm:grid-cols-2">
<x-text-input name="starts_on" type="date" />
<x-text-input name="ends_on" type="date" />
</div>
<input type="file" name="document" class="text-sm">
<textarea name="notes" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600"></textarea>
<x-primary-button>{{ __('Save') }}</x-primary-button>
</form>
</div></div>
</x-app-layout>
