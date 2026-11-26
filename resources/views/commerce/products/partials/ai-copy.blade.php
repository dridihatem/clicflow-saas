@php
    $aiCreditCost = (int) ($flowdeskAiTaskCredits['product_copy'] ?? 80);
@endphp
@if ($flowdeskPlanGates['ai_credits'] ?? false)
    <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-800 shadow-sm hover:bg-indigo-100 dark:border-indigo-500/40 dark:bg-indigo-950/50 dark:text-indigo-200 dark:hover:bg-indigo-900/40"
        x-on:click="openModal()"
    >
        <i class="fa-solid fa-wand-magic-sparkles text-[11px]" aria-hidden="true"></i>
        {{ __('catalog_ai_generate') }} ({{ $aiCreditCost }} {{ __('credits') }})
    </button>

    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-[210] flex items-center justify-center bg-slate-900/50 p-4"
        x-on:keydown.escape.window="closeModal()"
    >
        <div
            class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-600 dark:bg-slate-900"
            x-on:click.outside="if (!busy) closeModal()"
        >
            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('catalog_ai_heading') }}</h3>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('catalog_ai_intro') }}</p>

            <div class="mt-4">
                <x-input-label for="catalog_ai_prompt" :value="__('catalog_ai_prompt')" />
                <x-ai-voice-wrap target-id="catalog_ai_prompt" submit-button-id="catalog_ai_generate_btn" class="mt-1">
                    <textarea
                        id="catalog_ai_prompt"
                        rows="5"
                        x-model="prompt"
                        class="block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"
                        placeholder="{{ __('catalog_ai_prompt_placeholder') }}"
                    ></textarea>
                </x-ai-voice-wrap>
            </div>

            <p x-show="err" x-text="err" class="mt-3 text-sm text-rose-600 dark:text-rose-400"></p>
            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('AI-generated content — review before sending to clients.') }}</p>

            <div class="mt-6 flex justify-end gap-2">
                <button
                    type="button"
                    class="rounded-lg px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                    x-on:click="closeModal()"
                >
                    {{ __('Cancel') }}
                </button>
                <x-primary-button type="button" id="catalog_ai_generate_btn" x-bind:disabled="busy" x-on:click="generate()">
                    <span x-show="!busy">{{ __('Generate') }}</span>
                    <span x-show="busy" x-cloak>{{ __('Working…') }}</span>
                </x-primary-button>
            </div>
        </div>
    </div>
@endif
