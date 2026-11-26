<x-app-layout>
<div class="py-8"><div class="mx-auto max-w-4xl px-4 space-y-6">
@if(session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm">{{ session('status') }}</div>@endif
<form method="POST" action="{{ route('real-estate.offers.update', $offer) }}" class="flow-panel p-6 space-y-4">
@csrf @method('PUT')
<div><x-input-label for="title" :value="__('Title')" /><x-text-input id="title" name="title" class="mt-1 w-full" :value="$offer->title" required /></div>
<div class="grid gap-4 sm:grid-cols-2">
<div><x-input-label :value="__('Type')" /><select name="property_type" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600">@foreach(\App\Enums\RealEstatePropertyType::cases() as $t)<option value="{{ $t->value }}" @selected($offer->property_type===$t)>{{ $t->label() }}</option>@endforeach</select></div>
<div><x-input-label :value="__('Transaction')" /><select name="transaction_type" class="mt-1 w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600">@foreach(\App\Enums\RealEstateTransactionType::cases() as $t)<option value="{{ $t->value }}" @selected($offer->transaction_type===$t)>{{ $t->label() }}</option>@endforeach</select></div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
<div><x-input-label :value="__('Price')" /><x-text-input name="price" type="number" step="0.01" class="mt-1 w-full" :value="number_format($offer->price_minor/100, 2, '.', '')" required /></div>
<div><x-input-label :value="__('Currency')" /><x-text-input name="currency" class="mt-1 w-full" :value="$offer->currency" maxlength="3" required /></div>
</div>
<div><x-text-input name="address" class="w-full" :value="$offer->address" placeholder="{{ __('Address') }}" /></div>
<div><x-text-input name="city" class="w-full" :value="$offer->city" placeholder="{{ __('City') }}" /></div>
<div><textarea name="description" class="w-full rounded-md border-slate-300 dark:bg-slate-800 dark:border-slate-600">{{ $offer->description }}</textarea></div>
<input type="hidden" name="status" value="{{ $offer->status }}">
<x-primary-button>{{ __('Update offer') }}</x-primary-button>
</form>
<div class="flow-panel p-6 space-y-4">
<h2 class="font-semibold">{{ __('Images') }}</h2>
<div class="grid gap-3 sm:grid-cols-3">
@foreach($offer->images as $image)
<div class="relative">
<img src="{{ $image->url() }}" alt="" class="h-36 w-full rounded-lg object-cover">
<form method="POST" action="{{ route('real-estate.images.destroy', $image) }}" class="mt-2">@csrf @method('DELETE')<button class="text-xs text-rose-600">{{ __('Remove') }}</button></form>
</div>
@endforeach
</div>
<form method="POST" action="{{ route('real-estate.offers.images.store', $offer) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-end">
@csrf
<input type="file" name="image" accept="image/*" required class="text-sm">
<x-secondary-button>{{ __('Upload image') }}</x-secondary-button>
</form>
</div>
</div></div>
</x-app-layout>
