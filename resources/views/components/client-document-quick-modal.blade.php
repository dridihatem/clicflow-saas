@props([
    'intro',
])

{{-- Quick add client: right slide-over (same fields / endpoint as before, plus postal code). --}}
<div x-show="quickOpen" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="quickOpen = false" data-quick-client-aside>
    <div class="absolute inset-0 bg-slate-900/30" @click="quickOpen = false" x-show="quickOpen" x-transition.opacity></div>
    <aside class="absolute inset-y-0 end-0 flex w-full max-w-md flex-col bg-white shadow-2xl dark:bg-slate-900"
        x-show="quickOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
        role="dialog" aria-modal="true" aria-labelledby="quick_client_title" @click.stop>
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-700">
            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-orange-100 text-[var(--flow-primary)] dark:bg-orange-950/40"><x-cf-icon name="user-plus" size="sm" /></span>
            <div class="min-w-0">
                <h3 id="quick_client_title" class="font-semibold text-slate-900 dark:text-white">{{ __('Quick add client') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $intro }}</p>
            </div>
            <button type="button" class="ms-auto rounded-md p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" @click="quickOpen = false" title="{{ __('Close') }}"><x-cf-icon name="xmark" size="sm" /></button>
        </div>

        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5">
            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_name">{{ __('Name') }} *</label>
                <input id="quick_client_name" type="text" x-model="quickName" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_email">{{ __('Email') }}</label>
                    <input id="quick_client_email" type="email" x-model="quickEmail" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_phone">{{ __('Phone') }}</label>
                    <input id="quick_client_phone" type="text" x-model="quickPhone" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_source">{{ __('Source') }}</label>
                <select id="quick_client_source" x-model="quickSource" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                    <option value="">{{ __('— Optional —') }}</option>
                    @foreach (\App\Enums\ClientSource::cases() as $sourceCase)
                        <option value="{{ $sourceCase->value }}">{{ $sourceCase->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_address">{{ __('Address') }}</label>
                <input id="quick_client_address" type="text" x-model="quickAddressLine1" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_postal">{{ __('client_postal_code_label') }}</label>
                    <input id="quick_client_postal" type="text" x-model="quickAddressPostal" maxlength="20" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_city">{{ __('City') }}</label>
                    <input id="quick_client_city" type="text" x-model="quickAddressCity" autocomplete="off" class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                </div>
                <div>
                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300" for="quick_client_country">{{ __('Country') }}</label>
                    <input id="quick_client_country" type="text" x-model="quickAddressCountry" maxlength="8" autocomplete="off" placeholder="FR, TN…" class="mt-1 block w-full rounded-lg border-slate-300 uppercase dark:border-slate-600 dark:bg-slate-800" />
                </div>
            </div>
            <p class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300" x-show="quickError" x-text="quickError" x-cloak></p>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
            <button type="button" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800" @click="quickOpen = false">{{ __('Cancel') }}</button>
            <button
                type="button"
                class="inline-flex items-center gap-2 rounded-lg bg-flow-primary px-4 py-2 text-sm font-semibold text-white hover:bg-flow-primary-hover disabled:opacity-50"
                :disabled="quickLoading || !quickName.trim()"
                @click="submitQuickClient()"
            >
                <x-cf-icon name="check" size="sm" />
                <span x-show="!quickLoading">{{ __('Save') }}</span>
                <span x-show="quickLoading" x-cloak>{{ __('Saving…') }}</span>
            </button>
        </div>
    </aside>
</div>
