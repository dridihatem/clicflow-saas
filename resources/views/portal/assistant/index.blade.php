<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-600 dark:text-orange-400">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="mt-0.5 text-xl font-semibold text-slate-900 dark:text-white">{{ __('portal_nav_assistant') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('portal_ai_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full max-w-12xl px-4 sm:px-6 lg:px-8"
             x-data="portalAssistant({
                chatUrl: @js(route('portal.assistant.chat')),
                csrf: @js(csrf_token()),
                available: @js($available),
                conversationId: @js($conversation?->id),
                prospectId: @js($prospect?->id),
                prefill: @js($prefill),
                history: @js(($conversation?->messages ?? collect())->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])->values()),
             })">
            @if (! $available)
                <div class="flow-panel border-amber-200 bg-amber-50 p-6 dark:border-amber-900/50 dark:bg-amber-950/40">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-amber-700 dark:text-amber-300">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-amber-950 dark:text-amber-100">{{ __('portal_ai_unavailable_title') }}</h3>
                            <p class="mt-1 text-sm text-amber-900 dark:text-amber-200">{{ $unavailableMessage }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="grid gap-5 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <aside class="flow-panel h-fit p-0 lg:sticky lg:top-24">
                        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('portal_ai_history') }}</p>
                            <a href="{{ route('portal.assistant.index') }}" class="text-[11px] font-medium text-orange-600 hover:underline">{{ __('portal_ai_new') }}</a>
                        </div>
                        <ul class="max-h-[28rem] overflow-y-auto py-2">
                            @forelse ($conversations as $item)
                                <li>
                                    <a href="{{ route('portal.assistant.index', ['conversation' => $item->id]) }}"
                                       class="block truncate px-4 py-2 text-sm hover:bg-orange-50 dark:hover:bg-orange-950/30 {{ $conversation && $conversation->id === $item->id ? 'bg-orange-50 font-medium text-orange-800 dark:bg-orange-950/40 dark:text-orange-200' : 'text-slate-700 dark:text-slate-200' }}">
                                        {{ $item->title ?: __('portal_ai_untitled') }}
                                    </a>
                                </li>
                            @empty
                                <li class="px-4 py-6 text-xs text-slate-500">{{ __('portal_ai_no_history') }}</li>
                            @endforelse
                        </ul>
                    </aside>

                    <div class="flow-panel flex min-h-[32rem] flex-col p-0">
                        @if ($prospect)
                            <div class="border-b border-orange-100 bg-orange-50/80 px-5 py-3 text-sm text-orange-950 dark:border-orange-900/40 dark:bg-orange-950/30 dark:text-orange-100">
                                <span class="font-semibold">{{ __('portal_ai_about_prospect') }}</span>
                                {{ $prospect->name }}
                                @if ($prospect->status)
                                    <span class="ms-2 inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $prospect->status->badgeClass() }}">{{ $prospect->status->label() }}</span>
                                @endif
                            </div>
                        @endif

                        <div class="flex-1 space-y-4 overflow-y-auto px-5 py-5" x-ref="thread">
                            <template x-if="messages.length === 0 && !pending">
                                <div>
                                    <p class="text-sm text-slate-600 dark:text-slate-300">{{ __('portal_ai_empty') }}</p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        @foreach ([
                                            __('portal_ai_hint_rappeler'),
                                            __('portal_ai_hint_sms'),
                                            __('portal_ai_hint_script'),
                                            __('portal_ai_hint_objection'),
                                        ] as $hint)
                                            <button type="button" class="rounded-full border border-orange-200 bg-white px-3 py-1.5 text-xs font-medium text-orange-800 hover:bg-orange-50 dark:border-orange-800 dark:bg-slate-900 dark:text-orange-200" x-on:click="draft = @js($hint); $nextTick(() => $refs.input?.focus())">{{ $hint }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            </template>
                            <template x-for="(msg, i) in messages" :key="i">
                                <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                                    <div class="max-w-[85%] whitespace-pre-wrap rounded-2xl px-4 py-2.5 text-sm leading-relaxed"
                                         :class="msg.role === 'user' ? 'bg-orange-600 text-white' : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'"
                                         x-text="msg.content"></div>
                                </div>
                            </template>
                            <div x-show="pending" class="flex justify-start">
                                <div class="rounded-2xl bg-slate-100 px-4 py-2.5 text-sm text-slate-500 dark:bg-slate-800">
                                    <i class="fa-solid fa-spinner fa-spin me-1" aria-hidden="true"></i>{{ __('portal_ai_thinking') }}
                                </div>
                            </div>
                            <p x-show="error" x-cloak class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-200" x-text="error"></p>
                        </div>

                        <form class="border-t border-slate-100 p-4 dark:border-slate-800" x-on:submit.prevent="send()">
                            <label for="portal-ai-input" class="sr-only">{{ __('portal_ai_placeholder') }}</label>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <textarea id="portal-ai-input" x-ref="input" x-model="draft" rows="3" maxlength="4000" required
                                          class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                          placeholder="{{ __('portal_ai_placeholder') }}"
                                          x-on:keydown.meta.enter.prevent="send()"
                                          x-on:keydown.ctrl.enter.prevent="send()"
                                          :disabled="pending"></textarea>
                                <x-primary-button type="submit" class="!normal-case shrink-0" x-bind:disabled="pending || !draft.trim()">
                                    <i class="fa-solid fa-paper-plane me-1.5 text-xs" aria-hidden="true"></i>{{ __('portal_ai_send') }}
                                </x-primary-button>
                            </div>
                            <p class="mt-2 text-[11px] text-slate-400">{{ __('portal_ai_disclaimer') }}</p>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($available)
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('portalAssistant', (cfg) => ({
                    chatUrl: cfg.chatUrl,
                    csrf: cfg.csrf,
                    available: cfg.available,
                    conversationId: cfg.conversationId || null,
                    prospectId: cfg.prospectId || null,
                    draft: cfg.prefill || '',
                    messages: cfg.history || [],
                    pending: false,
                    error: '',
                    async send() {
                        const text = (this.draft || '').trim();
                        if (!text || this.pending || !this.available) return;
                        this.error = '';
                        this.messages.push({ role: 'user', content: text });
                        this.draft = '';
                        this.pending = true;
                        this.$nextTick(() => this.scroll());
                        try {
                            const res = await fetch(this.chatUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': this.csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: JSON.stringify({
                                    message: text,
                                    conversation_id: this.conversationId,
                                    prospect_id: this.prospectId,
                                }),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                this.error = data.message || @js(__('portal_ai_error'));
                                return;
                            }
                            this.conversationId = data.conversation_id || this.conversationId;
                            this.messages.push({ role: 'assistant', content: data.reply || '' });
                            const url = new URL(window.location.href);
                            if (this.conversationId) {
                                url.searchParams.set('conversation', this.conversationId);
                                url.searchParams.delete('prompt');
                                window.history.replaceState(null, '', url);
                            }
                        } catch (e) {
                            this.error = @js(__('portal_ai_error'));
                        } finally {
                            this.pending = false;
                            this.$nextTick(() => this.scroll());
                        }
                    },
                    scroll() {
                        const el = this.$refs.thread;
                        if (el) el.scrollTop = el.scrollHeight;
                    },
                    init() { this.$nextTick(() => this.scroll()); },
                }));
            });
        </script>
    @endif
</x-app-layout>
