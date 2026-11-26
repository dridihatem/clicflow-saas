@php
    /** @var \App\Models\Client|null $client */
    $bf = $client ?? null;
    $categorySuggestions = \App\Models\Client::query()->withoutGlobalScopes()
        ->where('company_id', auth()->user()?->company_id)
        ->whereNotNull('category')->where('category', '!=', '')
        ->distinct()->orderBy('category')->limit(50)->pluck('category');
@endphp
<div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4 dark:border-orange-900/30 dark:bg-orange-950/10">
    <div class="mb-3 flex items-center gap-2">
        <x-cf-icon name="building" size="sm" tone="orange" class="text-orange-600" />
        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_business_heading') }}</h3>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label for="client_company_name" :value="__('client_company_name_label')" />
            <x-text-input id="client_company_name" name="company_name" type="text" maxlength="191" class="mt-2 block w-full" :value="old('company_name', $bf?->company_name)" placeholder="{{ __('client_company_name_placeholder') }}" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="client_contact_name" :value="__('client_contact_name_label')" />
            <x-text-input id="client_contact_name" name="contact_name" type="text" maxlength="191" class="mt-2 block w-full" :value="old('contact_name', $bf?->contact_name)" placeholder="{{ __('client_contact_name_placeholder') }}" />
            <x-input-error :messages="$errors->get('contact_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="client_category" :value="__('client_category_label')" />
            <x-text-input id="client_category" name="category" type="text" maxlength="100" list="client_category_list" class="mt-2 block w-full" :value="old('category', $bf?->category)" placeholder="{{ __('client_category_placeholder') }}" autocomplete="off" />
            <datalist id="client_category_list">
                @foreach ($categorySuggestions as $cat)
                    <option value="{{ $cat }}"></option>
                @endforeach
            </datalist>
            <x-input-error :messages="$errors->get('category')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="client_status" :value="__('client_status_label')" />
            @php $currentStatus = old('status', $bf?->status?->value ?? \App\Enums\ClientStatus::Active->value); @endphp
            <select id="client_status" name="status" class="flow-input mt-2 block w-full">
                @foreach (\App\Enums\ClientStatus::cases() as $statusOption)
                    <option value="{{ $statusOption->value }}" @selected($currentStatus === $statusOption->value)>{{ $statusOption->label() }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('status')" class="mt-2" />
        </div>
    </div>
</div>
