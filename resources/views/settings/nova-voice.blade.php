<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Nova voice character') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.nova-voice.toggle') }}" class="mb-8 rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/50 sm:p-8" data-testid="nova-voice-toggle">
                @csrf
                @method('PUT')
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <x-cf-icon name="ai" variant="tile" :tone="$novaVoiceEnabled ? 'sky' : 'slate'" size="md" />
                        <div>
                            <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('nova_voice_toggle_title') }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('nova_voice_toggle_help') }}</p>
                            @if ($novaAdminDisabled ?? false)
                                <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-500/30 dark:bg-amber-950/40 dark:text-amber-100">{{ __('nova_voice_admin_disabled_notice') }}</p>
                            @endif
                            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $novaVoiceEnabled ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">
                                {{ $novaVoiceEnabled ? __('nova_voice_state_on') : __('nova_voice_state_off') }}
                            </p>
                        </div>
                    </div>
                    <input type="hidden" name="nova_voice_enabled" value="{{ $novaVoiceEnabled ? 0 : 1 }}">
                    @if ($novaVoiceEnabled)
                        <button type="submit" class="shrink-0 rounded-lg border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-600 shadow-sm hover:bg-rose-50 dark:border-rose-500/40 dark:bg-slate-900 dark:text-rose-300">{{ __('nova_voice_disable_button') }}</button>
                    @else
                        <button type="submit" class="shrink-0 rounded-lg bg-orange-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-700">{{ __('nova_voice_enable_button') }}</button>
                    @endif
                </div>
            </form>

            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('workspace_cartesia_voice_help') }}</p>

            @if (! $cartesiaAvailable)
                <div class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50/90 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ __('Cartesia is not configured on the platform yet. Ask a platform admin to add the Cartesia API key.') }}
                </div>
            @endif

            <div class="mt-8 rounded-2xl border border-slate-200/80 bg-white/80 p-6 shadow-sm dark:border-slate-700/80 dark:bg-slate-900/50 sm:p-8">
                <form method="POST" action="{{ route('settings.nova-voice.update') }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="cartesia_tts_voice" :value="__('Speaking character')" />
                        <x-cartesia-voice-select
                            name="cartesia_tts_voice"
                            id="cartesia_tts_voice"
                            :selected="old('cartesia_tts_voice', $form['cartesia_tts_voice'] ?? '')"
                            :include-empty="true"
                        />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Voices are grouped by language (EN, FR, ES, AR, HI, ID).') }}</p>
                        <x-input-error :messages="$errors->get('cartesia_tts_voice')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="cartesia_tts_voice_custom" :value="__('Or paste a Cartesia voice UUID')" />
                        <x-text-input
                            id="cartesia_tts_voice_custom"
                            name="cartesia_tts_voice_custom"
                            type="text"
                            class="mt-1 block w-full font-mono text-sm"
                            :value="old('cartesia_tts_voice_custom', '')"
                            placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"
                        />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Find voices at play.cartesia.ai/voices') }}</p>
                        <x-input-error :messages="$errors->get('cartesia_tts_voice_custom')" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ __('Save') }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
