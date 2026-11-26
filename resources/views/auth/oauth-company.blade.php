<x-guest-layout>
    <div class="mb-5 text-center lg:text-start">
        <span class="mkt-eyebrow">{{ __('Workspace') }}</span>
        <h1 class="mkt-display mt-2 text-xl font-semibold !text-[#EA580C]">{{ __('Create workspace') }}</h1>
        <p class="mt-2 text-sm text-slate-600">
            {{ __('Complete your workspace for') }} <strong class="text-slate-900">{{ $pending['email'] }}</strong>
        </p>
    </div>

    <form method="POST" action="{{ route('oauth.company.store') }}" class="space-y-4">
        @csrf

        <div>
            <x-input-label for="company_name" :value="__('Company name')" class="!text-slate-600" />
            <x-text-input id="company_name" class="mt-2 block w-full" type="text" name="company_name" :value="old('company_name')" required autofocus autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="country" :value="__('Country')" class="!text-slate-600" />
            @include('auth.partials.country-select', ['countries' => $countries, 'id' => 'country', 'name' => 'country', 'value' => old('country')])
            <x-input-error :messages="$errors->get('country')" class="mt-2" />
        </div>

        <div>
            @include('auth.partials.vat-percent-field')
        </div>

        @include('auth.partials.company-profile-fields', ['countries' => $countries, 'dialCodes' => $dialCodes])

        <div class="flex items-center justify-end pt-2">
            <button type="submit" class="mkt-btn mkt-btn-primary">
                {{ __('Create workspace') }}
            </button>
        </div>
    </form>

    @include('auth.partials.country-registration-data')
</x-guest-layout>
