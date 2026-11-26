<x-app-layout>
    <x-slot name="header">
        <div class="cf-page-head">
            <div>
                <p class="cf-eyebrow">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="cf-page-title">{{ __('Calendar') }}</h2>
                <p class="cf-page-sub">{{ __('portal_calendar_intro') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="cf-page-wrap" x-data="portalCalendar(@js([
            'events' => $events,
            'anchor' => $anchor,
            'view' => $view,
            'prospects' => $prospects->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values(),
            'storeUrl' => route('portal.calendar.rdv'),
            'csrf' => csrf_token(),
            'labels' => [
                'today' => __('Today'),
                'month' => __('portal_calendar_month'),
                'week' => __('portal_calendar_week'),
                'day' => __('portal_calendar_day'),
                'create' => __('portal_calendar_create_rdv'),
                'save' => __('Save'),
                'cancel' => __('Cancel'),
                'prospect' => __('client_prospect_name'),
                'when' => __('client_prospect_appointment'),
                'empty' => __('portal_calendar_empty_day'),
            ],
        ]))">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="cf-btn-secondary" @click="prev()" aria-label="{{ __('Previous') }}"><i class="fa-solid fa-chevron-left text-xs rtl:rotate-180"></i></button>
                    <h3 class="min-w-[12rem] text-center text-base font-semibold text-slate-900 dark:text-white" x-text="heading"></h3>
                    <button type="button" class="cf-btn-secondary" @click="next()" aria-label="{{ __('Next') }}"><i class="fa-solid fa-chevron-right text-xs rtl:rotate-180"></i></button>
                    <button type="button" class="cf-btn-secondary" @click="goToday()" x-text="labels.today"></button>
                </div>
                <div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    <template x-for="v in ['month','week','day']" :key="v">
                        <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold" :class="view === v ? 'bg-white text-orange-700 shadow-sm dark:bg-slate-900 dark:text-orange-300' : 'text-slate-600'" @click="setView(v)" x-text="labels[v]"></button>
                    </template>
                </div>
            </div>

            <div class="flow-panel cf-card overflow-hidden">
                <template x-if="view === 'month'">
                    <div>
                        <div class="grid grid-cols-7 border-b border-slate-100 bg-slate-50 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:border-slate-800 dark:bg-slate-800/80">
                            <template x-for="d in weekdayLabels" :key="d"><div class="px-1 py-2" x-text="d"></div></template>
                        </div>
                        <div class="grid grid-cols-7">
                            <template x-for="cell in monthCells" :key="cell.key">
                                <button type="button" class="min-h-[6.5rem] border-e border-b border-slate-100 p-1.5 text-start hover:bg-orange-50/60 dark:border-slate-800 dark:hover:bg-orange-950/20"
                                    :class="cell.inMonth ? '' : 'bg-slate-50/80 text-slate-400 dark:bg-slate-900/40'"
                                    @click="onSlot(cell.date)">
                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs tabular-nums" :class="cell.isToday ? 'bg-orange-600 font-bold text-white' : 'font-medium'" x-text="cell.day"></span>
                                    <ul class="mt-1 space-y-0.5">
                                        <template x-for="ev in cell.events.slice(0,3)" :key="ev.id">
                                            <li>
                                                <a :href="ev.url" @click.stop class="block truncate rounded px-1 py-0.5 text-[10px] font-medium text-white" :style="{ background: ev.color }" x-text="ev.title"></a>
                                            </li>
                                        </template>
                                        <li x-show="cell.events.length > 3" class="px-1 text-[10px] text-slate-400" x-text="'+' + (cell.events.length - 3)"></li>
                                    </ul>
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
                <template x-if="view !== 'month'">
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        <template x-for="cell in periodCells" :key="cell.key">
                            <div class="grid grid-cols-[4.5rem_minmax(0,1fr)]">
                                <div class="border-e border-slate-100 px-2 py-3 text-xs font-semibold text-slate-500 dark:border-slate-800">
                                    <div x-text="cell.label"></div>
                                    <div class="tabular-nums" x-text="cell.dateLabel"></div>
                                </div>
                                <div class="min-h-[4.5rem] p-2 hover:bg-orange-50/40" @click="onSlot(cell.date)">
                                    <template x-if="cell.events.length === 0">
                                        <p class="text-xs text-slate-400" x-text="labels.empty"></p>
                                    </template>
                                    <ul class="space-y-1">
                                        <template x-for="ev in cell.events" :key="ev.id">
                                            <li>
                                                <a :href="ev.url" @click.stop class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-white" :style="{ background: ev.color }">
                                                    <span class="tabular-nums text-xs opacity-90" x-text="ev.time"></span>
                                                    <span class="font-medium" x-text="ev.title"></span>
                                                </a>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div x-show="creating" x-cloak class="fixed inset-0 z-50 flex items-center justify-center px-4">
                <div class="fixed inset-0 bg-slate-900/50" @click="creating = false"></div>
                <form method="POST" :action="storeUrl" class="relative z-10 w-full max-w-md space-y-4 rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900" @click.stop>
                    <input type="hidden" name="_token" :value="csrf">
                    <h3 class="text-base font-semibold text-slate-900 dark:text-white" x-text="labels.create"></h3>
                    <div>
                        <label class="text-xs font-semibold text-slate-500" x-text="labels.prospect"></label>
                        <select name="prospect_id" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800">
                            <template x-for="p in prospects" :key="p.id">
                                <option :value="p.id" x-text="p.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold text-slate-500" x-text="labels.when"></label>
                        <input type="datetime-local" name="appointment_at" x-model="slotAt" required class="mt-1 block w-full rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-800" />
                    </div>
                    <input type="hidden" name="appointment_status" value="confirme" />
                    <div class="flex justify-end gap-2">
                        <button type="button" class="cf-btn-secondary" @click="creating = false" x-text="labels.cancel"></button>
                        <button type="submit" class="cf-btn-primary" x-text="labels.save"></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('portalCalendar', (cfg) => ({
                events: cfg.events || [],
                anchor: new Date(cfg.anchor + 'T12:00:00'),
                view: cfg.view || 'month',
                prospects: cfg.prospects || [],
                storeUrl: cfg.storeUrl,
                csrf: cfg.csrf,
                labels: cfg.labels,
                creating: false,
                slotAt: '',
                weekdayLabels: (() => {
                    const fmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
                    return [1,2,3,4,5,6,0].map((d) => {
                        const dt = new Date(2026, 10, d === 0 ? 15 : 8 + d); // a Mon-Sun week
                        return fmt.format(dt);
                    });
                })(),
                get heading() {
                    const opts = this.view === 'day'
                        ? { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }
                        : { month: 'long', year: 'numeric' };
                    return new Intl.DateTimeFormat('fr-FR', opts).format(this.anchor);
                },
                eventsOn(dateStr) {
                    return this.events.filter((e) => (e.start || '').slice(0, 10) === dateStr).map((e) => ({
                        ...e,
                        time: e.start ? e.start.slice(11, 16) : '',
                    }));
                },
                ymd(d) {
                    const y = d.getFullYear();
                    const m = String(d.getMonth() + 1).padStart(2, '0');
                    const day = String(d.getDate()).padStart(2, '0');
                    return `${y}-${m}-${day}`;
                },
                get monthCells() {
                    const first = new Date(this.anchor.getFullYear(), this.anchor.getMonth(), 1);
                    const start = new Date(first);
                    const wd = (first.getDay() + 6) % 7; // Monday=0
                    start.setDate(first.getDate() - wd);
                    const today = this.ymd(new Date());
                    const cells = [];
                    for (let i = 0; i < 42; i++) {
                        const d = new Date(start);
                        d.setDate(start.getDate() + i);
                        const key = this.ymd(d);
                        cells.push({
                            key,
                            date: key,
                            day: d.getDate(),
                            inMonth: d.getMonth() === this.anchor.getMonth(),
                            isToday: key === today,
                            events: this.eventsOn(key),
                        });
                    }
                    return cells;
                },
                get periodCells() {
                    const days = this.view === 'day' ? 1 : 7;
                    const start = new Date(this.anchor);
                    if (this.view === 'week') {
                        const wd = (start.getDay() + 6) % 7;
                        start.setDate(start.getDate() - wd);
                    }
                    const fmt = new Intl.DateTimeFormat('fr-FR', { weekday: 'short' });
                    const cells = [];
                    for (let i = 0; i < days; i++) {
                        const d = new Date(start);
                        d.setDate(start.getDate() + i);
                        const key = this.ymd(d);
                        cells.push({
                            key,
                            date: key,
                            label: fmt.format(d),
                            dateLabel: d.getDate() + '/' + (d.getMonth() + 1),
                            events: this.eventsOn(key),
                        });
                    }
                    return cells;
                },
                setView(v) { this.view = v; },
                prev() {
                    const d = new Date(this.anchor);
                    if (this.view === 'month') d.setMonth(d.getMonth() - 1);
                    else d.setDate(d.getDate() - (this.view === 'week' ? 7 : 1));
                    this.anchor = d;
                },
                next() {
                    const d = new Date(this.anchor);
                    if (this.view === 'month') d.setMonth(d.getMonth() + 1);
                    else d.setDate(d.getDate() + (this.view === 'week' ? 7 : 1));
                    this.anchor = d;
                },
                goToday() { this.anchor = new Date(); },
                onSlot(dateStr) {
                    this.slotAt = dateStr + 'T09:00';
                    this.creating = true;
                },
            }));
        });
    </script>
</x-app-layout>
