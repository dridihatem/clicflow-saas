@if (! empty($flowdeskNovaVoiceNav['enabled']))
    @once('flowdesk-nova-voice-nav')
        <div
            x-data="novaVoiceNav(@js($flowdeskNovaVoiceNav))"
            x-cloak
            class="relative flex items-center"
            aria-live="polite"
        >
            <div
                x-show="error || (heard && active && !ttsEnabled)"
                x-cloak
                class="absolute end-0 top-full z-50 mt-2 w-max max-w-sm rounded-xl border border-sky-200 bg-white px-3 py-2.5 text-xs text-slate-800 shadow-lg dark:border-sky-500/30 dark:bg-slate-900 dark:text-slate-100"
            >
                <p x-show="error" x-text="error" class="text-rose-600 dark:text-rose-400"></p>
                <p x-show="!error && heard" x-text="heard" class="italic text-slate-600 dark:text-slate-300"></p>
            </div>

            <button
                type="button"
                class="flow-nova-topbar inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border px-0 text-xs font-semibold shadow-sm transition dark:bg-slate-900/80 dark:text-slate-100"
                :class="_listeningPaused
                    ? 'border-rose-300/60 bg-rose-50/95 text-rose-900 hover:bg-rose-100 dark:border-rose-500/35 dark:bg-rose-950/50 dark:text-rose-100 dark:hover:bg-rose-950/70'
                    : 'border-sky-300/50 bg-white/90 text-slate-800 hover:bg-sky-50 dark:border-sky-500/30 dark:hover:bg-slate-800'"
                x-on:click="toggleMic()"
                x-bind:title="supported ? (_listeningPaused ? @js(__('nova_voice_mic_resume_title')) : (status ? status + ' — ' + @js(__('nova_voice_mic_pause_title')) : @js(__('nova_voice_mic_pause_title')))) : @js(__('ai_voice_unsupported'))"
                aria-label="{{ __('Voice commands') }}"
            >
                <span
                    class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-white"
                    :class="[
                        _listeningPaused
                            ? 'bg-gradient-to-br from-rose-500 to-red-600'
                            : 'bg-gradient-to-br from-sky-400 to-indigo-600',
                        { 'animate-pulse': supported && recognitionActive && !error && !_listeningPaused },
                    ]"
                >
                    <i class="fa-solid text-xs" :class="_listeningPaused ? 'fa-microphone-slash' : 'fa-microphone'" aria-hidden="true"></i>
                </span>
            </button>
        </div>
    @endonce
@endif
