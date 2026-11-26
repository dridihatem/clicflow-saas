@php
    $cfAiToken = (string) request()->query('ai_draft', '');
    $cfAiDraft = null;
    if ($cfAiToken !== '' && auth()->check()) {
        $cfAiDraft = app(\App\Services\AiSidebar\AiDraftService::class)->find($cfAiToken, auth()->user());
        if ($cfAiDraft && ! request()->routeIs($cfAiDraft['route'] ?? '__none__')) {
            $cfAiDraft = null;
        }
        // A task draft only fills the Tasks form of its own project.
        if ($cfAiDraft && ! empty($cfAiDraft['route_params']['project'])) {
            $cfRouteProject = request()->route('project');
            $cfRouteProjectId = is_object($cfRouteProject) ? (string) $cfRouteProject->getKey() : (string) $cfRouteProject;
            if ($cfRouteProjectId !== (string) $cfAiDraft['route_params']['project']) {
                $cfAiDraft = null;
            }
        }
    }
@endphp
@if ($cfAiToken !== '' && $cfAiDraft === null && auth()->check())
    <div class="max-w-12xl w-full px-4 pt-6 sm:px-6 lg:px-8">
        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ __('ai_draft_expired') }}</div>
    </div>
@elseif ($cfAiDraft)
    @php
        $cfAiPayload = [
            'entity' => $cfAiDraft['entity'],
            'fields' => $cfAiDraft['fields'] ?? [],
            'client' => $cfAiDraft['client'] ?? null,
            'lines' => $cfAiDraft['lines'] ?? [],
            'currency' => $cfAiDraft['currency'] ?? null,
            'assignees' => $cfAiDraft['assignees'] ?? [],
            'form_selector' => $cfAiDraft['form_selector'] ?? null,
            'labels' => ['filling' => __('ai_draft_filling'), 'done' => __('ai_draft_done')],
        ];
    @endphp
    <div class="max-w-12xl w-full px-4 pt-6 sm:px-6 lg:px-8" data-ai-draft-banner>
        <div class="rounded-2xl border border-orange-200 bg-gradient-to-r from-orange-50 via-amber-50 to-white px-4 py-3 shadow-sm dark:border-orange-500/30 dark:from-orange-950/40 dark:via-slate-900 dark:to-slate-900">
            <div class="flex flex-wrap items-start gap-3">
                <x-cf-icon name="ai" variant="tile" tone="orange" size="md" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-orange-900 dark:text-orange-100">{{ __('ai_draft_banner_title') }}</p>
                    <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                        <span data-ai-draft-status>{{ __('ai_draft_filling') }}</span>
                        · {{ __('ai_draft_banner_note') }}
                        @if (($cfAiDraft['source'] ?? '') === 'rules')
                            · {{ __('ai_draft_banner_rules') }}
                        @endif
                    </p>
                    <p class="mt-1 text-[11px] italic text-slate-500 dark:text-slate-400">« {{ \Illuminate\Support\Str::limit($cfAiDraft['prompt'] ?? '', 220) }} »</p>
                    @if (! empty($cfAiDraft['unresolved']))
                        <div class="mt-2">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ __('ai_draft_banner_todo') }}</p>
                            <ul class="mt-1 space-y-1 text-xs text-amber-900 dark:text-amber-100">
                                @foreach ($cfAiDraft['unresolved'] as $item)
                                    <li class="flex flex-wrap items-center gap-2">
                                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                        <span>{{ $item['label'] }}</span>
                                        @if (($item['type'] ?? '') === 'client' && in_array($cfAiDraft['entity'], ['quote', 'invoice'], true) && ($item['value'] ?? '') !== '')
                                            <button type="button" class="rounded-md border border-orange-300 bg-white px-2 py-0.5 text-[11px] font-semibold text-orange-700 hover:bg-orange-50 dark:border-orange-500/40 dark:bg-slate-900 dark:text-orange-200"
                                                onclick="document.dispatchEvent(new CustomEvent('flowdesk-open-quick-client', { detail: { name: @js($item['value']) } }))">
                                                {{ __('ai_draft_create_client', ['name' => $item['value']]) }}
                                            </button>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>window.cfAiDraft = @js($cfAiPayload);</script>
@endif
