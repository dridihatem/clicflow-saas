@php
    $requiredKeys = collect($columns)->where('required', true)->pluck('key')->all();
    $rules = $mappingRules ?? ['groups' => [], 'notes' => []];
@endphp
<style>
    .cfm-card{transition:border-color .15s, box-shadow .15s}
    .cfm-card.is-mapped{border-color:rgb(253 186 116);box-shadow:0 0 0 1px rgb(254 215 170)}
    .cfm-card.is-dup{border-color:rgb(244 63 94);box-shadow:0 0 0 1px rgb(254 205 211)}
</style>

@if (! empty($mappingErrors))
    <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-200">
        <ul class="list-disc space-y-1 ps-5">
            @foreach ($mappingErrors as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('import.store', $type) }}" id="cf-mapping-form" class="space-y-6">
    @csrf
    <input type="hidden" name="raw" value="{{ $raw }}">
    <input type="hidden" name="filename" value="{{ $filename }}">

    <div class="flow-panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <x-cf-icon name="layer-group" variant="tile" tone="orange" class="cf-stat-tile" />
                <div>
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('data_import_mapping_title') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('data_import_mapping_intro', ['count' => count($headerKeys)]) }}</p>
                </div>
            </div>
            <div id="cfm-summary" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-300"></div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($headerKeys as $i => $header)
                @php $sel = $selectedMapping[$header] ?? ''; @endphp
                <div class="cfm-card rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900/60" data-cfm-card>
                    <div class="flex items-center justify-between gap-2">
                        <p class="truncate text-sm font-semibold text-slate-900 dark:text-white" title="{{ $header }}">
                            <span class="me-1 text-xs font-normal text-slate-400">{{ __('data_import_mapping_file_column') }}</span>{{ $header }}
                        </p>
                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">#{{ $i + 1 }}</span>
                    </div>
                    <div class="mt-2 min-h-[2.5rem] space-y-0.5">
                        @forelse ($samples[$header] ?? [] as $sample)
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $sample }}">{{ $sample }}</p>
                        @empty
                            <p class="text-xs italic text-slate-400">{{ __('data_import_mapping_no_sample') }}</p>
                        @endforelse
                    </div>
                    <label class="mt-3 block text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="cfm_{{ $i }}">{{ __('data_import_mapping_target') }}</label>
                    <select id="cfm_{{ $i }}" name="mapping[{{ $i }}]" class="flow-input mt-1 block w-full text-sm" data-cfm-select>
                        <option value="">{{ __('data_import_mapping_ignore') }}</option>
                        @foreach ($fieldLabels as $key => $label)
                            <option value="{{ $key }}" @selected($sel === $key)>{{ $label }}@if (in_array($key, $requiredKeys, true)) ({{ __('data_import_mapping_required') }})@endif</option>
                        @endforeach
                    </select>
                    <p class="mt-1 hidden text-xs font-medium text-rose-600" data-cfm-dup>{{ __('data_import_mapping_dup_hint') }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-5 space-y-2">
            @foreach ($rules['notes'] as $n => $note)
                <div class="{{ $note['missing'] ? 'hidden' : '' }} flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-100"
                     data-cfm-note data-missing="{{ $note['missing'] }}" data-any="{{ implode(',', $note['any']) }}">
                    <x-cf-icon name="info" size="sm" class="mt-0.5 shrink-0 text-sky-600" />
                    <span>{{ $note['text'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="flow-panel overflow-hidden p-0">
        <x-flow.table>
            <thead class="bg-slate-50/90 text-start text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/80 dark:text-slate-400">
                <tr>
                    @foreach ($headerKeys as $header)
                        <th class="px-4 py-3 text-start">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200/80 text-sm dark:divide-slate-700/80">
                @foreach (array_slice($preview, 0, 10) as $row)
                    <tr>
                        @foreach ($headerKeys as $header)
                            <td class="max-w-xs truncate px-4 py-3 text-slate-700 dark:text-slate-200">{{ $row[$header] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </x-flow.table>
    </div>
    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_import_preview_intro', ['count' => count($rows)]) }}</p>

    <div class="flex flex-wrap items-center gap-3">
        <x-primary-button type="submit" id="cfm-submit">{{ $confirmLabel ?? __('prospect_import_confirm') }}</x-primary-button>
        <a href="{{ route('import.preview.form', $type) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Cancel') }}</a>
        <p id="cfm-block" class="hidden text-sm font-medium text-rose-600"></p>
    </div>
</form>

<script>
(function () {
    const form = document.getElementById('cf-mapping-form');
    if (!form) return;
    const selects = Array.from(form.querySelectorAll('[data-cfm-select]'));
    const submit = document.getElementById('cfm-submit');
    const block = document.getElementById('cfm-block');
    const notes = Array.from(form.querySelectorAll('[data-cfm-note]'));
    const groups = @json($rules['groups']);
    const summary = document.getElementById('cfm-summary');
    const msgs = {
        dup: @json(__('data_import_mapping_duplicate_js')),
        summary: @json(__('data_import_mapping_summary')),
    };
    function refresh() {
        const counts = {};
        selects.forEach(s => { if (s.value) counts[s.value] = (counts[s.value] || 0) + 1; });
        let dup = false;
        selects.forEach(s => {
            const card = s.closest('[data-cfm-card]');
            const isDup = s.value && counts[s.value] > 1;
            dup = dup || isDup;
            card.classList.toggle('is-mapped', !!s.value && !isDup);
            card.classList.toggle('is-dup', !!isDup);
            card.querySelector('[data-cfm-dup]').classList.toggle('hidden', !isDup);
        });
        const missingGroup = groups.find(g => !g.keys.some(k => counts[k]));
        const ok = !dup && !missingGroup;
        notes.forEach(n => {
            const missing = n.dataset.missing;
            if (!missing) return;
            const any = (n.dataset.any || '').split(',').filter(Boolean);
            n.classList.toggle('hidden', !!counts[missing] || !any.some(k => counts[k]));
        });
        submit.disabled = !ok;
        submit.classList.toggle('opacity-50', !ok);
        submit.classList.toggle('cursor-not-allowed', !ok);
        block.textContent = dup ? msgs.dup : (missingGroup ? missingGroup.message : '');
        block.classList.toggle('hidden', ok);
        const mapped = selects.filter(s => s.value).length;
        summary.textContent = msgs.summary.replace(':mapped', mapped).replace(':ignored', selects.length - mapped);
    }
    selects.forEach(s => s.addEventListener('change', refresh));
    form.addEventListener('submit', e => { refresh(); if (submit.disabled) e.preventDefault(); });
    refresh();
})();
</script>
