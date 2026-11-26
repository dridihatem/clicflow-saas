@props(['floating' => false])
@php
    $u = auth()->user();
    $cfAiChatShow = $u
        && $u->hasAnyRole(['company_admin', 'team_member'])
        && ($flowdeskPlanGates['ai_credits'] ?? false)
        && \Illuminate\Support\Facades\Route::has('ai.chat.send');
    // Voice in the chat = the user's own dictation / read-aloud. Independent of the company top-bar Nova switch;
    // only the platform admin switch (admin_nova_voice_enabled) turns it off.
    $cfAiVoice = $cfAiChatShow && \App\Services\NovaVoiceNavigationService::adminVoiceEnabled($u->company);
    $cfAiLocale = (string) app()->getLocale();
    $cfAiVoiceCfg = $cfAiVoice ? [
        'enabled' => true,
        'lang' => match ($cfAiLocale) { 'fr' => 'fr-FR', 'en' => 'en-US', 'ar' => 'ar-TN', 'es' => 'es-ES', 'hi' => 'hi-IN', 'id' => 'id-ID', default => 'fr-FR' },
        'speakUrl' => ((bool) config('flowdesk.nova_voice_tts', false) && \Illuminate\Support\Facades\Route::has('assistant.chat-speak')) ? route('assistant.chat-speak') : null,
        'statusUrl' => \Illuminate\Support\Facades\Route::has('assistant.voice-status') ? route('assistant.voice-status') : null,
    ] : ['enabled' => false];
@endphp
@if ($cfAiChatShow)
<div
    x-data="aiChatPanel(@js([
        'userId' => (string) $u->id,
        'historyUrl' => route('ai.chat.show'),
        'newUrl' => route('ai.chat.new'),
        'sendUrl' => route('ai.chat.send'),
        'route' => (string) request()->route()?->getName(),
        'voice' => $cfAiVoiceCfg,
        'labels' => [
            'error' => __('ai_sidebar_error'),
            'throttled' => __('ai_chat_throttled'),
            'voiceUnsupported' => __('ai_chat_voice_unsupported'),
            'voiceDenied' => __('ai_chat_voice_denied'),
            'voiceNoSpeech' => __('ai_chat_voice_no_speech'),
            'voiceNoMic' => __('ai_chat_voice_no_mic'),
            'voiceError' => __('ai_chat_voice_error'),
        ],
    ]))"
    data-ai-chat-panel
>
    @if ($floating)
        <button
            type="button"
            x-show="!open"
            x-cloak
            @click="toggle()"
            class="fixed bottom-24 end-4 z-[285] inline-flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-orange-500 to-amber-500 text-white shadow-lg shadow-orange-500/30 transition hover:scale-105 sm:end-6"
            aria-label="{{ __('ai_chat_open') }}"
            title="{{ __('ai_chat_title') }}"
        >
            <x-cf-icon name="ai" size="md" />
        </button>
    @endif

    <aside
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-x-6"
        x-transition:enter-end="opacity-100 translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0 translate-x-6"
        class="cf-ai-chat fixed inset-0 z-[300] flex flex-col bg-white shadow-2xl dark:bg-slate-950 lg:start-auto lg:border-s lg:border-slate-200 dark:lg:border-slate-800"
        role="complementary"
        aria-label="{{ __('ai_chat_title') }}"
        @keydown.escape.window="if (open && !isDesktop()) setOpen(false)"
    >
        <div class="cf-ai-chat-resize absolute inset-y-0 start-0 hidden w-1.5 cursor-col-resize lg:block" @pointerdown="startResize($event)" title="{{ __('ai_chat_resize') }}"></div>

        {{-- Header --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-200 bg-gradient-to-r from-orange-50 via-amber-50/70 to-white px-4 dark:border-slate-800 dark:from-orange-950/40 dark:via-slate-950 dark:to-slate-950">
            <x-cf-icon name="ai" variant="tile" tone="orange" size="md" />
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ __('ai_chat_title') }}</p>
                <p class="truncate text-[11px] text-slate-500 dark:text-slate-400">{{ __('ai_chat_subtitle') }}</p>
            </div>
            @if ($cfAiVoice)
                <button type="button" x-show="voice.enabled" @click="toggleSpeak()" data-ai-chat-speaker
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border shadow-sm transition"
                    :class="speakOn ? 'border-orange-300 bg-orange-600 text-white hover:bg-orange-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    :aria-pressed="speakOn ? 'true' : 'false'"
                    :title="speakOn ? @js(__('ai_chat_voice_speak_on')) : @js(__('ai_chat_voice_speak_off'))">
                    <svg x-show="speakOn" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>
                    <svg x-show="!speakOn" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m23 9-6 6"/><path d="m17 9 6 6"/></svg>
                </button>
                <button type="button" x-show="voice.enabled" @click="toggleConversation()" data-ai-chat-conversation
                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg border shadow-sm transition"
                    :class="convMode ? 'border-emerald-300 bg-emerald-600 text-white hover:bg-emerald-700' : 'border-slate-200 bg-white text-slate-500 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                    :aria-pressed="convMode ? 'true' : 'false'"
                    title="{{ __('ai_chat_voice_conversation') }}">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/><path d="M8 10h.01M12 10h.01M16 10h.01"/></svg>
                </button>
            @endif
            <button type="button" @click="newConversation()" class="inline-flex h-8 items-center gap-1 rounded-lg border border-orange-200 bg-white px-2 text-[11px] font-semibold text-orange-700 shadow-sm hover:bg-orange-50 dark:border-orange-500/30 dark:bg-slate-900 dark:text-orange-300" title="{{ __('ai_chat_new') }}">
                <x-cf-icon name="plus" size="sm" />
                <span class="hidden sm:inline">{{ __('ai_chat_new_short') }}</span>
            </button>
            <button type="button" @click="setOpen(false)" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="{{ __('ai_chat_close') }}">
                <x-cf-icon name="x" size="sm" />
            </button>
        </div>

        {{-- Messages --}}
        <div x-ref="list" class="flex-1 space-y-3 overflow-y-auto bg-slate-50/60 px-4 py-4 dark:bg-slate-900/40">
            <div x-show="loading && !loaded" class="py-10 text-center text-xs text-slate-400">{{ __('ai_chat_loading') }}</div>

            <div x-show="loaded && messages.length === 0" x-cloak class="mx-auto max-w-sm py-6 text-center">
                <div class="mx-auto mb-3 inline-flex"><x-cf-icon name="ai" variant="tile" tone="orange" size="lg" /></div>
                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('ai_chat_welcome_title') }}</p>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ __('ai_chat_welcome_body') }}</p>
                <div class="mt-4 space-y-2 text-start">
                    @foreach (['ai_chat_suggestion_quote', 'ai_chat_suggestion_invoice', 'ai_chat_suggestion_task', 'ai_chat_suggestion_client'] as $sug)
                        <button type="button" @click="useSuggestion(@js(__($sug)))" class="block w-full rounded-xl border border-orange-200/80 bg-white px-3 py-2 text-xs text-slate-700 shadow-sm transition hover:border-orange-300 hover:bg-orange-50 dark:border-orange-500/20 dark:bg-slate-900 dark:text-slate-200">
                            {{ __($sug) }}
                        </button>
                    @endforeach
                </div>
            </div>

            <template x-for="m in messages" :key="m.id">
                <div class="flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[85%]">
                        <div
                            class="whitespace-pre-line break-words rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm"
                            :class="m.role === 'user'
                                ? 'rounded-br-md bg-gradient-to-br from-orange-500 to-orange-600 text-white'
                                : (m.meta && m.meta.type === 'error'
                                    ? 'rounded-bl-md border border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200'
                                    : 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100')"
                            x-text="m.content"
                        ></div>
                        <template x-if="m.meta && m.meta.type === 'draft' && m.meta.url">
                            <div class="mt-1.5">
                                <a
                                    :href="m.meta.url"
                                    x-show="!draftExpired(m)"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-orange-700"
                                >
                                    <x-cf-icon name="arrow-right" size="sm" />
                                    {{ __('ai_chat_open_draft') }}
                                </a>
                                <span x-show="draftExpired(m)" class="text-[11px] italic text-slate-400">{{ __('ai_chat_draft_expired') }}</span>
                            </div>
                        </template>
                        <p class="mt-1 px-1 text-[10px] text-slate-400" :class="m.role === 'user' ? 'text-end' : 'text-start'" x-text="fmt(m.at)"></p>
                    </div>
                </div>
            </template>

            <div x-show="sending" x-cloak class="flex justify-start">
                <div class="inline-flex items-center gap-1 rounded-2xl rounded-bl-md border border-slate-200 bg-white px-3.5 py-3 shadow-sm dark:border-slate-700 dark:bg-slate-900" aria-label="{{ __('ai_chat_typing') }}">
                    <span class="cf-ai-dot h-1.5 w-1.5 rounded-full bg-orange-500"></span>
                    <span class="cf-ai-dot h-1.5 w-1.5 rounded-full bg-orange-500" style="animation-delay:.15s"></span>
                    <span class="cf-ai-dot h-1.5 w-1.5 rounded-full bg-orange-500" style="animation-delay:.3s"></span>
                </div>
            </div>
        </div>

        {{-- Composer --}}
        <div class="shrink-0 border-t border-slate-200 bg-white px-3 pb-3 pt-2 dark:border-slate-800 dark:bg-slate-950">
            <p x-show="onForm" x-cloak class="mb-1.5 flex items-center gap-1 px-1 text-[11px] text-orange-700 dark:text-orange-300">
                <x-cf-icon name="check" size="sm" />
                <span x-show="formKind === 'quote' || formKind === 'invoice'">{{ __('ai_chat_form_context') }}</span>
                <span x-show="formKind && formKind !== 'quote' && formKind !== 'invoice'">{{ __('ai_chat_form_context_fields') }}</span>
            </p>
            <p x-show="error" x-cloak class="mb-1.5 px-1 text-[11px] text-rose-600" x-text="error"></p>
            @if ($cfAiVoice)
                <p x-show="voiceError" x-cloak class="mb-1.5 flex items-start gap-1 px-1 text-[11px] text-rose-600" x-text="voiceError" data-ai-chat-voice-error></p>
                <div x-show="convMode || listening || speaking" x-cloak data-ai-chat-voice-bar
                    class="mb-1.5 flex items-center gap-2 rounded-xl border px-2.5 py-1.5 text-[11px] font-medium"
                    :class="listening ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-950/40 dark:text-rose-200' : (speaking ? 'border-orange-200 bg-orange-50 text-orange-800 dark:border-orange-500/30 dark:bg-orange-950/40 dark:text-orange-200' : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-200')">
                    <span class="relative flex h-2.5 w-2.5 shrink-0">
                        <span class="absolute inline-flex h-full w-full rounded-full opacity-75" :class="(listening || speaking) ? 'animate-ping ' + (listening ? 'bg-rose-400' : 'bg-orange-400') : ''"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full" :class="listening ? 'bg-rose-500' : (speaking ? 'bg-orange-500' : 'bg-emerald-500')"></span>
                    </span>
                    <span class="min-w-0 flex-1 truncate" x-text="listening ? @js(__('ai_chat_voice_listening')) : (speaking ? @js(__('ai_chat_voice_speaking')) : (sending ? @js(__('ai_chat_voice_thinking')) : @js(__('ai_chat_voice_conversation_on'))))"></span>
                    <button type="button" @click="stopVoice()" class="inline-flex items-center gap-1 rounded-lg bg-white/80 px-2 py-0.5 font-semibold text-slate-700 shadow-sm hover:bg-white dark:bg-slate-900 dark:text-slate-200" data-ai-chat-voice-stop>
                        <svg class="h-3 w-3" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                        {{ __('ai_chat_voice_stop') }}
                    </button>
                </div>
            @endif
            <form @submit.prevent="send()" class="flex items-end gap-2 rounded-2xl border border-slate-300 bg-white p-1.5 shadow-sm focus-within:border-orange-400 focus-within:ring-2 focus-within:ring-orange-200 dark:border-slate-700 dark:bg-slate-900 dark:focus-within:ring-orange-500/30">
                <label for="cf-ai-chat-input" class="sr-only">{{ __('ai_chat_input_label') }}</label>
                <textarea
                    id="cf-ai-chat-input"
                    x-ref="input"
                    x-model="input"
                    rows="1"
                    maxlength="2000"
                    @keydown="onKeydown($event)"
                    @input="autosize()"
                    class="block max-h-44 min-h-[2.5rem] w-full resize-none border-0 bg-transparent px-2 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:ring-0 dark:text-slate-100"
                    placeholder="{{ __('ai_chat_placeholder') }}"
                ></textarea>
                @if ($cfAiVoice)
                    <button type="button" x-show="voice.enabled" data-ai-chat-mic
                        @pointerdown="micDown($event)" @pointerup="micUp()" @pointerleave="micLeave()" @keydown.enter.prevent="micClick()" @keydown.space.prevent="micClick()"
                        class="relative inline-flex h-9 w-9 shrink-0 touch-none select-none items-center justify-center rounded-xl border transition"
                        :class="listening ? 'border-rose-300 bg-rose-600 text-white shadow-md shadow-rose-500/30' : 'border-slate-200 bg-white text-slate-600 hover:border-orange-300 hover:text-orange-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
                        :aria-pressed="listening ? 'true' : 'false'"
                        :title="listening ? @js(__('ai_chat_voice_mic_stop')) : @js(__('ai_chat_voice_mic'))"
                        aria-label="{{ __('ai_chat_voice_mic') }}">
                        <span x-show="listening" x-cloak class="absolute inset-0 animate-ping rounded-xl bg-rose-400/40"></span>
                        <svg class="relative h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><path d="M12 17v5"/></svg>
                    </button>
                @endif
                <button
                    type="submit"
                    class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-orange-600 text-white shadow-sm transition hover:bg-orange-700 disabled:opacity-50"
                    :disabled="sending || input.trim().length < 2"
                    aria-label="{{ __('ai_chat_send') }}"
                >
                    <x-cf-icon name="send" size="sm" />
                </button>
            </form>
            <div class="mt-1.5 flex items-center justify-between gap-2 px-1 text-[10px] text-slate-400">
                <p class="min-w-0 truncate">{{ __('ai_chat_hint') }}</p>
                @if ($cfAiVoice)
                    <label x-show="voice.enabled" class="inline-flex shrink-0 cursor-pointer items-center gap-1" title="{{ __('ai_chat_voice_autosend_help') }}">
                        <input type="checkbox" x-model="autoSend" @change="persistVoice()" class="h-3 w-3 rounded border-slate-300 text-orange-600 focus:ring-orange-500" data-ai-chat-autosend>
                        {{ __('ai_chat_voice_autosend') }}
                    </label>
                @endif
            </div>
        </div>
    </aside>
</div>
@endif
