@props(['variant' => 'topbar'])
@php
    $u = auth()->user();
    $cfAiLaunch = $u
        && $u->hasAnyRole(['company_admin', 'team_member'])
        && ($flowdeskPlanGates['ai_credits'] ?? false)
        && \Illuminate\Support\Facades\Route::has('ai.chat.send');
@endphp
@if ($cfAiLaunch)
    @if ($variant === 'sidebar')
        <div class="px-2 pt-3">
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('cf-ai-chat-toggle'))"
                class="flex w-full items-center gap-2 rounded-lg border border-orange-200/80 bg-gradient-to-r from-orange-50 to-amber-50/70 px-3 py-2.5 text-sm font-semibold text-orange-800 shadow-sm transition hover:border-orange-300 hover:from-orange-100 dark:border-orange-500/25 dark:from-orange-950/40 dark:to-slate-900 dark:text-orange-200"
                title="{{ __('ai_chat_title') }}"
                data-ai-chat-launcher
            >
                <x-cf-icon name="ai" size="md" tone="orange" class="shrink-0" />
                <span class="cf-nav-label min-w-0 truncate">{{ __('ai_chat_title') }}</span>
            </button>
        </div>
    @else
        <button
            type="button"
            onclick="window.dispatchEvent(new CustomEvent('cf-ai-chat-toggle'))"
            class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-orange-200 bg-gradient-to-r from-orange-50 to-amber-50 px-2.5 text-sm font-semibold text-orange-800 shadow-sm transition hover:border-orange-300 dark:border-orange-500/30 dark:from-orange-950/40 dark:to-slate-900 dark:text-orange-200"
            title="{{ __('ai_chat_title') }}"
            aria-label="{{ __('ai_chat_open') }}"
            data-ai-chat-launcher
        >
            <x-cf-icon name="ai" size="sm" tone="orange" />
            <span class="hidden xl:inline">{{ __('ai_chat_title') }}</span>
        </button>
    @endif
@endif
