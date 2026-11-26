@props([
    'storeUrl' => null,
    'selectId' => 'client_id',
    'intro' => null,
])
@php
    $quickUrl = $storeUrl ?? route('clients.quick-store');
    $introText = $intro ?? __('Saves immediately and adds to the list for this project.');
    $instanceId = 'qc'.str_replace('-', '', \Illuminate\Support\Str::uuid()->toString());
    $clientQuickConfig = [
        'quickUrl' => $quickUrl,
        'selectId' => $selectId,
        'csrf' => csrf_token(),
        'i18n' => [
            'required' => __('The client name is required.'),
            'pageExpired' => __('Page expired, please refresh and try again.'),
            'notAllowed' => __('You are not allowed to create clients.'),
            'couldNot' => __('Could not create client.'),
            'network' => __('Network error.'),
        ],
    ];
    $clientQuickConfigId = 'client-quick-'.$instanceId;
    $clientQuickConfigJson = json_encode(
        $clientQuickConfig,
        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
    );
@endphp
<script>
    window._flowdeskClientQuick = window._flowdeskClientQuick || Object.create(null);
    window._flowdeskClientQuick[{!! json_encode($clientQuickConfigId) !!}] = {!! $clientQuickConfigJson !!};
</script>
<div
    class="mt-1 flex flex-wrap items-end gap-2"
    x-data="projectClientQuickAdd((window._flowdeskClientQuick && window._flowdeskClientQuick['{{ $clientQuickConfigId }}']) || {})"
>
    <div class="min-w-0 flex-1">
        {{ $slot }}
    </div>
    <button
        type="button"
        class="inline-flex shrink-0 items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
        @click="openModal()"
    >
        <i class="fa-solid fa-user-plus me-1.5 text-[11px]" aria-hidden="true"></i>
        {{ __('Quick add client') }}
    </button>

    <template x-teleport="body">
        <div
            x-show="modalOpen"
            x-cloak
            data-wizard-ignore-validation
            class="fixed inset-0 z-[300] flex items-center justify-center bg-slate-900/50 p-4"
            @keydown.escape.window="if (modalOpen) modalOpen = false"
            @click.self="modalOpen = false"
        >
            <div
                class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-600 dark:bg-slate-900"
                @click.stop
            >
                <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Quick add client') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $introText }}</p>

                <div class="mt-4 space-y-4" data-wizard-ignore-validation>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_name">{{ __('Name') }} *</label>
                        <input
                            id="{{ $instanceId }}_name"
                            type="text"
                            x-model="quickName"
                            autocomplete="off"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_email">{{ __('Email') }}</label>
                            <input
                                id="{{ $instanceId }}_email"
                                type="email"
                                x-model="quickEmail"
                                autocomplete="off"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_phone">{{ __('Phone') }}</label>
                            <input
                                id="{{ $instanceId }}_phone"
                                type="text"
                                x-model="quickPhone"
                                autocomplete="off"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_source">{{ __('Source') }}</label>
                        <select
                            id="{{ $instanceId }}_source"
                            x-model="quickSource"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        >
                            <option value="">{{ __('— Optional —') }}</option>
                            @foreach (\App\Enums\ClientSource::cases() as $sourceCase)
                                <option value="{{ $sourceCase->value }}">{{ $sourceCase->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_address">{{ __('Address') }}</label>
                        <input
                            id="{{ $instanceId }}_address"
                            type="text"
                            x-model="quickAddressLine1"
                            autocomplete="off"
                            class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_city">{{ __('City') }}</label>
                            <input
                                id="{{ $instanceId }}_city"
                                type="text"
                                x-model="quickAddressCity"
                                autocomplete="off"
                                class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="{{ $instanceId }}_country">{{ __('Country') }}</label>
                            <input
                                id="{{ $instanceId }}_country"
                                type="text"
                                x-model="quickAddressCountry"
                                maxlength="8"
                                autocomplete="off"
                                placeholder="FR, US…"
                                class="mt-1 block w-full rounded-lg border-slate-300 uppercase shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                            />
                        </div>
                    </div>

                    <p x-show="err" x-text="err" x-cloak class="text-sm text-rose-600 dark:text-rose-400"></p>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-slate-300 px-4 py-2 text-sm dark:border-slate-600 dark:text-slate-200"
                        @click="modalOpen = false"
                    >
                        {{ __('Cancel') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
                        x-bind:disabled="busy || !quickName.trim()"
                        @click="quickSubmit()"
                    >
                        <span x-show="!busy">{{ __('Save') }}</span>
                        <span x-show="busy" x-cloak>{{ __('Saving…') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
