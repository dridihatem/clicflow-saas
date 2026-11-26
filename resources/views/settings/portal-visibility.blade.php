<x-app-layout>
    <style>
        .pv-switch{position:relative;display:inline-flex;flex-shrink:0;align-items:center}
        .pv-switch input{position:absolute;opacity:0;width:1px;height:1px;pointer-events:none}
        .pv-track{position:relative;display:block;width:44px;height:24px;border-radius:9999px;background:#cbd5e1;transition:background .2s}
        .pv-knob{position:absolute;top:2px;left:2px;width:20px;height:20px;border-radius:9999px;background:#fff;box-shadow:0 1px 3px rgba(15,23,42,.25);transition:transform .2s}
        .pv-switch input:checked + .pv-track{background:#f97316}
        .pv-switch input:checked + .pv-track .pv-knob{transform:translateX(20px)}
        .pv-switch input:focus-visible + .pv-track{outline:2px solid #fb923c;outline-offset:2px}
        .pv-on{color:#059669}.pv-off{color:#94a3b8}.pv-dim{opacity:.6}
        .dark .pv-track{background:#475569}.dark .pv-on{color:#34d399}
    </style>
    <div class="py-10">
        <div class="max-w-5xl w-full sm:px-6 lg:px-8">
            <x-flow.page-header
                icon="eye"
                icon-tone="orange"
                :title="__('portal_vis_title')"
                :description="__('portal_vis_intro')"
            />

            @if (session('status'))
                <div class="mt-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100" role="status">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('settings.portal-visibility.update') }}" class="mt-8 space-y-6"
                x-data="{ visible: @js(array_values(array_diff(array_keys($sections), $hidden))), all: @js(array_keys($sections)) }">
                @csrf
                @method('PUT')

                <div class="flow-panel flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <x-cf-icon name="eye" variant="tile" tone="emerald" size="md" />
                        <div>
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                <span x-text="visible.length">{{ count($sections) - count($hidden) }}</span> / {{ count($sections) }} {{ __('portal_vis_visible_count') }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('portal_vis_counter_help') }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" x-on:click="visible = [...all]">{{ __('portal_vis_show_all') }}</button>
                        <button type="button" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" x-on:click="visible = []">{{ __('portal_vis_hide_all') }}</button>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($sections as $key => $section)
                        <label class="flow-panel group flex cursor-pointer items-start gap-3 p-4 transition hover:border-orange-300 dark:hover:border-orange-700 {{ in_array($key, $hidden, true) ? 'pv-dim' : '' }}"
                            x-bind:class="visible.includes('{{ $key }}') ? '' : 'pv-dim'">
                            <x-cf-icon :name="$section['icon']" variant="tile" :tone="$section['tone']" size="md" />
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $section['label'] }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $section['description'] }}</span>
                                <span class="pv-state mt-1 inline-block text-[11px] font-medium {{ in_array($key, $hidden, true) ? 'pv-off' : 'pv-on' }}"
                                    x-bind:class="visible.includes('{{ $key }}') ? 'pv-on' : 'pv-off'"
                                    x-text="visible.includes('{{ $key }}') ? @js(__('portal_vis_state_visible')) : @js(__('portal_vis_state_hidden'))">{{ in_array($key, $hidden, true) ? __('portal_vis_state_hidden') : __('portal_vis_state_visible') }}</span>
                            </span>
                            <span class="pv-switch mt-1">
                                <input type="checkbox" name="visible[]" value="{{ $key }}" x-model="visible" data-section="{{ $key }}" @checked(! in_array($key, $hidden, true))>
                                <span class="pv-track" aria-hidden="true"><span class="pv-knob"></span></span>
                            </span>
                        </label>
                    @endforeach
                </div>

                <div class="flow-panel px-5 py-4 text-xs text-slate-600 dark:text-slate-400">
                    <p class="flex items-start gap-2"><x-cf-icon name="info" size="sm" tone="sky" /> <span>{{ __('portal_vis_note_rules') }}</span></p>
                    <p class="mt-2 flex items-start gap-2"><x-cf-icon name="clients" size="sm" tone="violet" /> <span>{{ __('portal_vis_note_overrides', ['count' => $overrideCount]) }}</span></p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-400">
                        <x-cf-icon name="floppy-disk" size="sm" />
                        {{ __('Save') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
