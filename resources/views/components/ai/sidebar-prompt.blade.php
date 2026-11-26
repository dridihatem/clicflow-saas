@php
    $u = auth()->user();
    $show = $u
        && $u->hasAnyRole(['company_admin', 'team_member'])
        && ($flowdeskPlanGates['ai_credits'] ?? false)
        && \Illuminate\Support\Facades\Route::has('ai.sidebar-draft');
@endphp
@if ($show)
    <div
        class="cf-nav-label mx-2 mt-3 rounded-2xl border border-orange-200/70 bg-gradient-to-br from-orange-50 to-amber-50/60 p-2.5 shadow-sm dark:border-orange-500/25 dark:from-orange-950/30 dark:to-slate-900"
        x-data="aiSidebarPrompt(@js(['url' => route('ai.sidebar-draft'), 'loading' => __('ai_sidebar_loading'), 'error' => __('ai_sidebar_error')]))"
        data-ai-sidebar-prompt
    >
        <form x-on:submit.prevent="submit()" class="space-y-2">
            <label for="cf-ai-sidebar-prompt" class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-orange-800 dark:text-orange-200">
                <x-cf-icon name="ai" tone="orange" size="sm" />
                {{ __('ai_sidebar_title') }}
            </label>
            <textarea
                id="cf-ai-sidebar-prompt"
                x-model="prompt"
                x-ref="input"
                rows="3"
                maxlength="2000"
                x-on:keydown.enter="if (!$event.shiftKey) { $event.preventDefault(); submit(); }"
                class="block w-full resize-none rounded-xl border-orange-200 bg-white/90 px-2.5 py-2 text-xs leading-snug text-slate-800 placeholder:text-slate-400 focus:border-orange-400 focus:ring-orange-300 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                placeholder="{{ __('ai_sidebar_placeholder') }}"
                :disabled="loading"
            ></textarea>
            <div class="flex items-center justify-between gap-2">
                <p class="min-w-0 text-[10px] leading-tight text-slate-500 dark:text-slate-400" x-show="!loading && !error">{{ __('ai_sidebar_hint') }}</p>
                <p class="min-w-0 text-[10px] font-medium leading-tight text-orange-700 dark:text-orange-300" x-show="loading" x-cloak x-text="loadingLabel"></p>
                <p class="min-w-0 text-[10px] font-medium leading-tight text-rose-600" x-show="error" x-cloak x-text="error"></p>
                <button type="submit" class="inline-flex shrink-0 items-center gap-1 rounded-lg bg-orange-600 px-2.5 py-1.5 text-[11px] font-semibold text-white shadow-sm transition hover:bg-orange-700 disabled:opacity-60" :disabled="loading || prompt.trim().length < 6">
                    <svg x-show="loading" x-cloak class="h-3 w-3 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3"/></svg>
                    {{ __('ai_sidebar_submit') }}
                </button>
            </div>
        </form>
    </div>
@endif
