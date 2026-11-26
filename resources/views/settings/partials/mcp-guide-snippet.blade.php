<div class="relative mt-2">
    <pre class="overflow-auto rounded-xl border border-slate-200 bg-slate-900 p-3 pr-20 text-xs text-slate-100">{{ $code }}</pre>
    <button type="button" class="absolute right-2 top-2 inline-flex items-center gap-1 rounded-lg bg-white/10 px-2 py-1 text-[11px] font-semibold text-white hover:bg-white/20"
        @click="copy(@js($code), '{{ $key }}')">
        <x-cf-icon name="copy" size="sm" class="text-white" />
        <span x-show="copied !== '{{ $key }}'">Copier</span>
        <span x-show="copied === '{{ $key }}'" x-cloak>Copié</span>
    </button>
</div>
