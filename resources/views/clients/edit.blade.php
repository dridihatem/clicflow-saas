@php
    $addr = is_array($client->address) ? $client->address : [];
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit client') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $client->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl w-full sm:px-6 lg:px-8">
            <div class="flow-panel p-6 sm:p-8">
                <form method="POST" action="{{ route('clients.update', $client) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="client_name" :value="__('Name')" />
                        <x-text-input id="client_name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $client->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="client_email" :value="__('Email')" />
                            <x-text-input id="client_email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $client->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="client_phone" :value="__('Phone')" />
                            <x-text-input id="client_phone" name="phone" type="text" class="mt-2 block w-full" :value="old('phone', $client->phone)" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                    </div>
                    @include('clients.partials.business-fields', ['client' => $client ?? null])
                    <div>
                        <x-input-label for="client_source" :value="__('client_source_label')" />
                        <select id="client_source" name="source" class="flow-input mt-2 block w-full">
                            <option value="">{{ __('client_source_placeholder') }}</option>
                            @foreach (\App\Enums\ClientSource::cases() as $sourceCase)
                                <option value="{{ $sourceCase->value }}" @selected(old('source', $client->source) === $sourceCase->value)>{{ $sourceCase->label() }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('source')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="client_line1" :value="__('Address line')" />
                        <x-text-input id="client_line1" name="address_line1" type="text" class="mt-2 block w-full" :value="old('address_line1', $addr['line1'] ?? '')" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <x-input-label for="client_postal_code" :value="__('client_postal_code_label')" />
                            <x-text-input id="client_postal_code" name="address_postal_code" type="text" class="mt-2 block w-full" maxlength="20" autocomplete="postal-code" :value="old('address_postal_code', $addr['postal_code'] ?? ($addr['zip'] ?? ''))" />
                            <x-input-error :messages="$errors->get('address_postal_code')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="client_city" :value="__('City')" />
                            <x-text-input id="client_city" name="address_city" type="text" class="mt-2 block w-full" :value="old('address_city', $addr['city'] ?? '')" />
                        </div>
                        <div>
                            <x-input-label for="client_country" :value="__('Country (ISO)')" />
                            <x-text-input id="client_country" name="address_country" type="text" class="mt-2 block w-full uppercase" maxlength="8" :value="old('address_country', $addr['country'] ?? '')" placeholder="US, FR…" />
                        </div>
                    </div>
                    <a href="{{ route('clients.show', ['client' => $client, 'tab' => 'portal']) }}" class="flex items-center gap-3 rounded-xl border border-orange-200/80 bg-orange-50/60 p-4 transition hover:border-orange-300 hover:bg-orange-50 dark:border-orange-900/40 dark:bg-orange-950/20">
                        <x-cf-icon name="key" variant="tile" tone="orange" class="cf-stat-tile" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_portal_tab_label') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('client_portal_edit_moved_hint') }}</span>
                        </span>
                        <x-cf-icon name="arrow-right" size="sm" class="text-orange-600" />
                    </a>

                    <div class="flex justify-end gap-3 pt-2">
                        <a href="{{ route('clients.index') }}">
                            <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                        </a>
                        <x-primary-button type="submit" class="!normal-case">{{ __('Save changes') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
