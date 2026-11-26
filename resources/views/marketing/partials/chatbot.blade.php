@php
    $nova = config('flowdesk.ai_assistant_brand_name', 'Nova');
    $brand = config('flowdesk.brand_name', config('app.name'));
    $demo = app(\App\Services\NovaPublicDemoService::class);
    $status = $demo->statusPayload();
@endphp

<div
    class="fixed bottom-5 end-5 z-50 flex flex-col items-end gap-3"
    x-data="novaPublicDemo(@js([
        'mode' => 'chat',
        'open' => false,
        'status' => $status,
        'locale' => app()->getLocale(),
        'script' => '',
        'urls' => [
            'chat' => route('demo.nova.chat'),
            'speak' => route('demo.nova.speak'),
            'status' => route('demo.nova.status'),
            'locale' => route('locale.update'),
        ],
        'labels' => [
            'welcome' => __('nova_chatbot_welcome', ['nova' => $nova, 'brand' => $brand]),
            'unavailable' => __('nova_demo_unavailable'),
            'requestFailed' => __('nova_demo_request_failed'),
            'placeholder' => __('nova_chatbot_placeholder', ['nova' => $nova]),
            'send' => __('Send'),
            'thinking' => __('nova_demo_thinking'),
        ],
    ]))"
    x-cloak
    x-on:mkt-open-chatbot.window="open = true"
>
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-95"
        class="mkt-glass flex h-[min(28rem,70vh)] w-[min(22rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-2xl"
        role="dialog"
        aria-label="{{ __('nova_chatbot_title', ['nova' => $nova]) }}"
    >
        <div class="flex items-center gap-3 border-b border-[rgba(120,80,50,0.12)] bg-gradient-to-r from-[rgba(234,88,12,0.06)] via-transparent to-[rgba(38,232,212,0.06)] px-4 py-3">
            <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-[#0b1020] ring-1 ring-[rgba(234,88,12,0.35)]">
                <img src="{{ asset('robot.png') }}" alt="" class="h-8 w-8 object-contain" width="32" height="32" decoding="async" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-slate-900">{{ __('nova_chatbot_title', ['nova' => $nova]) }}</p>
                <p class="truncate text-xs text-slate-500" x-show="chatOk">{{ __('nova_chatbot_online') }}</p>
                <p class="truncate text-xs text-amber-700" x-show="! chatOk" x-cloak>{{ __('nova_chatbot_offline') }}</p>
            </div>
            <button type="button" class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-200/60 hover:text-slate-700" x-on:click="open = false" aria-label="{{ __('Close') }}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto px-4 py-4" x-ref="messages">
            <template x-for="(msg, idx) in messages" :key="idx">
                <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="max-w-[85%] rounded-2xl px-3.5 py-2 text-sm leading-relaxed"
                        :class="msg.role === 'user' ? 'rounded-br-md bg-[#EA580C] text-white' : 'rounded-bl-md bg-slate-100 text-slate-800'"
                    >
                        <p x-text="msg.text"></p>
                    </div>
                </div>
            </template>
            <p class="flex gap-1 px-1" x-show="loading" x-cloak>
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400"></span>
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400 [animation-delay:150ms]"></span>
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-slate-400 [animation-delay:300ms]"></span>
            </p>
        </div>

        <p class="px-4 pb-1 text-xs text-rose-600" x-show="error" x-text="error" x-cloak></p>

        <form class="flex gap-2 border-t border-slate-100 p-3" x-on:submit.prevent="sendChat()">
            <input
                type="text"
                class="min-w-0 flex-1 rounded-xl border border-[rgba(120,80,50,0.16)] bg-white/90 px-3 py-2.5 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#EA580C] focus:outline-none focus:ring-2 focus:ring-[rgba(234,88,12,0.15)]"
                x-model="draft"
                :placeholder="labels.placeholder"
                :disabled="loading || ! chatOk"
                autocomplete="off"
            />
            <button
                type="submit"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EA580C] text-white transition hover:bg-[#c2410c] disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="loading || ! chatOk || ! draft.trim()"
                aria-label="{{ __('Send') }}"
            >
                <i class="fa-solid fa-paper-plane text-xs" aria-hidden="true"></i>
            </button>
        </form>
    </div>

    <button
        type="button"
        class="mkt-chat-fab inline-flex h-14 w-14 items-center justify-center rounded-full text-white transition hover:bg-[#c2410c] focus:outline-none focus:ring-2 focus:ring-[#EA580C] focus:ring-offset-2"
        x-on:click="open = ! open"
        :aria-expanded="(open ?? false).toString()"
        aria-label="{{ __('nova_chatbot_toggle', ['nova' => $nova]) }}"
    >
        <img
            src="{{ asset('robot.png') }}"
            alt=""
            class="h-10 w-10 object-contain"
            width="40"
            height="40"
            decoding="async"
            x-show="! open"
        />
        <i class="fa-solid fa-xmark text-lg" x-show="open" x-cloak aria-hidden="true"></i>
    </button>
</div>
