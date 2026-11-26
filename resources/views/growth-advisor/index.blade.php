<x-app-layout>
    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8 space-y-8">
            <x-flow.page-header
                :title="__('AI growth advisor')"
                :description="__('AI growth advisor lead')"
            />

            @if ($aiGrowthAvailable && ! empty($aiGrowthModules))
                <div
                    class="flow-panel p-8"
                    x-data="growthAdvisor({ suggestUrl: @js(route('assistant.suggest')), modules: @js($aiGrowthModules) })"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('Get AI suggestions') }}</h3>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-800 dark:border-indigo-500/40 dark:bg-indigo-950/50 dark:text-indigo-200">
                            <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                            {{ __('Included in plan') }}
                        </span>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <template x-for="mod in modules" :key="mod.mode">
                            <div class="rounded-xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-900/50">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                    <i class="fa-solid" :class="mod.icon" aria-hidden="true"></i>
                                </div>
                                <h4 class="mt-4 text-sm font-semibold text-slate-900 dark:text-white" x-text="mod.title"></h4>
                                <p class="mt-2 text-xs leading-relaxed text-slate-600 dark:text-slate-400" x-text="mod.description"></p>
                                <button
                                    type="button"
                                    class="mt-4 inline-flex w-full items-center justify-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-800 transition hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-500/40 dark:bg-indigo-950/50 dark:text-indigo-200 dark:hover:bg-indigo-900/40"
                                    x-on:click="run(mod)"
                                    x-bind:disabled="busy"
                                >
                                    <i class="fa-solid fa-lightbulb text-[11px]" aria-hidden="true"></i>
                                    <span x-text="busy && activeMode === mod.mode ? @js(__('Working…')) : (@js(__('Get AI suggestions')) + ' (' + mod.credit_cost + ' ' + @js(__('credits')) + ')')"></span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div x-show="error" x-cloak class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-200" x-text="error"></div>

                    <div x-show="result" x-cloak class="mt-6 rounded-xl border border-slate-200 bg-slate-50/80 p-5 dark:border-slate-700 dark:bg-slate-800/40">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white" x-text="resultTitle"></p>
                            <button type="button" class="inline-flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400" x-on:click="copy()">
                                <i class="fa-regular fa-copy" aria-hidden="true"></i>
                                {{ __('Copy') }}
                            </button>
                        </div>
                        <pre class="mt-3 max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-white p-4 text-sm text-slate-800 ring-1 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700" x-text="result"></pre>
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('AI-generated content — review before sharing with clients.') }}</p>
                    </div>
                </div>
            @else
                <div class="flow-panel p-8">
                    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('Not enough AI credits for the growth advisor right now. Check your plan or try again later.') }}</p>
                    <a href="{{ route('billing.index') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                        {{ __('Plan subscription') }}
                        <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('growthAdvisor', (config) => ({
                    modules: config.modules || [],
                    suggestUrl: config.suggestUrl,
                    busy: false,
                    activeMode: null,
                    error: '',
                    result: '',
                    resultTitle: '',
                    async run(mod) {
                        this.error = '';
                        this.result = '';
                        this.busy = true;
                        this.activeMode = mod.mode;
                        this.resultTitle = mod.title;
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        try {
                            const res = await fetch(this.suggestUrl, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': token,
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                },
                                body: JSON.stringify({ mode: mod.mode, context: mod.context }),
                            });
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                this.error = data.message || @json(__('Something went wrong.'));
                                return;
                            }
                            this.result = data.suggestion || '';
                        } catch (e) {
                            this.error = @json(__('Network error.'));
                        } finally {
                            this.busy = false;
                            this.activeMode = null;
                        }
                    },
                    copy() {
                        navigator.clipboard?.writeText(this.result || '');
                    },
                }));
            });
        </script>
    @endpush
</x-app-layout>
