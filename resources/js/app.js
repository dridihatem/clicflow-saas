import './bootstrap';
import './guest-forms';
import { registerClicflowIcons } from './clicflow-icons';
import { flowdeskNotify } from './flowdesk-notify';

window.flowdeskNotify = flowdeskNotify;

import Alpine from 'alpinejs';
import { registerAiVoiceField, registerFlowdeskAiFormVoiceBridge, flowdeskNotifyVoiceBusy, flowdeskNotifyVoiceIdle } from './ai-voice';
import { registerAiWritingModes } from './ai-writing-modes';
import { registerNovaVoiceNav } from './nova-voice-nav';
import { registerNovaAssistant } from './nova-assistant';
import { registerNovaPublicDemo } from './nova-public-demo';
import { registerMarketingBrand } from './marketing-brand';
import { registerMarketingTheme } from './marketing-theme';
import { registerSidebarFlyout } from './sidebar-flyout';
import { registerAiSidebarPrompt, bootAiDraftLiveFill } from './ai-draft-live';
import { registerAiChatPanel } from './ai-chat-panel';
import { registerFlowdeskCalendar } from './calendar';
import { flowdeskSpeakNovaText } from './flowdesk-nova-speech';
import Sortable from 'sortablejs';
import { createApp } from 'vue';
import FlowdeskPulse from './vue/FlowdeskPulse.vue';

window.Alpine = Alpine;

registerClicflowIcons();

/**
 * Match PHP number_format($num, $decimals, $decimal_separator, $thousands_separator).
 */
function flowdeskNumberFormat(num, decimals, decPoint, thousandsSep) {
    const d = Math.max(0, Math.floor(Number(decimals)) || 0);
    const dec = decPoint ?? '.';
    const thou = thousandsSep ?? ',';
    const x = Number(num);
    if (!Number.isFinite(x)) {
        if (d === 0) {
            return '0';
        }
        return `0${dec}${'0'.repeat(d)}`;
    }
    const neg = x < 0 || Object.is(x, -0);
    const abs = Math.abs(x);
    const body = d === 0 ? String(Math.round(abs)) : abs.toFixed(d);
    const parts = body.split('.');
    let intPart = parts[0];
    const fracPart = parts[1];
    intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, thou);
    let out = intPart;
    if (d > 0 && fracPart !== undefined) {
        out += dec + fracPart;
    }
    return neg ? `-${out}` : out;
}

document.addEventListener('alpine:init', () => {
    registerFlowdeskAiFormVoiceBridge();
    registerAiVoiceField(Alpine);
    registerAiWritingModes(Alpine);
    registerNovaVoiceNav(Alpine);
    registerNovaAssistant(Alpine);
    registerNovaPublicDemo(Alpine);
    registerSidebarFlyout(Alpine);
    registerAiSidebarPrompt(Alpine);
    registerAiChatPanel(Alpine);
    registerFlowdeskCalendar(Alpine);
    registerMarketingBrand();
    registerMarketingTheme();

    // Auth password mascot: idle animation only (no cover-on-focus)
    Alpine.store('authPeek', {
        covering: false,
        showPassword: false,
        focused: false,
        hasValue: false,
        sync() {
            this.covering = false;
        },
        cover() {
            this.focused = true;
            this.sync();
        },
        uncover() {
            this.focused = false;
            this.sync();
        },
        setValue(value) {
            this.hasValue = String(value ?? '').length > 0;
            this.sync();
        },
        toggleShow() {
            this.showPassword = !this.showPassword;
            this.sync();
        },
    });

    Alpine.data('productTour', (cfg = {}) => ({
        show: false,
        loading: false,
        step: 0,
        dismissUrl: cfg.dismissUrl || '',
        autoOpen: Boolean(cfg.autoOpen),
        dismissed: Boolean(cfg.dismissed),
        userId: String(cfg.userId || 'anon'),
        total: Number(cfg.total || 1),
        labels: cfg.labels || { next: 'Next', terminate: 'Terminate' },
        get isLast() {
            return this.step >= this.total - 1;
        },
        storageKey() {
            return 'clicflow.tour.dismissed.' + this.userId;
        },
        sessionKey() {
            return 'clicflow.tour.session.' + this.userId;
        },
        isPermanentlyDismissed() {
            if (this.dismissed) {
                return true;
            }
            try {
                return window.localStorage.getItem(this.storageKey()) === '1';
            } catch (e) {
                return false;
            }
        },
        isSessionHidden() {
            try {
                return window.sessionStorage.getItem(this.sessionKey()) === '1';
            } catch (e) {
                return false;
            }
        },
        init() {
            if (this.isPermanentlyDismissed() || this.isSessionHidden()) {
                return;
            }
            if (this.autoOpen) {
                this.$nextTick(() => this.open(true));
            }
        },
        open(withLoading = false) {
            this.step = 0;
            this.show = true;
            document.body.classList.add('overflow-y-hidden');
            if (withLoading) {
                this.loading = true;
                window.setTimeout(() => {
                    this.loading = false;
                }, 450);
            } else {
                this.loading = false;
            }
        },
        close() {
            // "Fermer": hide for this session only.
            try {
                window.sessionStorage.setItem(this.sessionKey(), '1');
            } catch (e) {
                // ignore
            }
            this.show = false;
            this.loading = false;
            document.body.classList.remove('overflow-y-hidden');
        },
        next() {
            if (this.isLast) {
                this.terminate();
            } else {
                this.step += 1;
            }
        },
        prev() {
            if (this.step > 0) {
                this.step -= 1;
            }
        },
        csrfToken() {
            const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            let cookie = '';
            try {
                const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
                if (match) {
                    cookie = decodeURIComponent(match[1]);
                }
            } catch (e) {
                cookie = '';
            }
            return { meta, cookie };
        },
        async postDismiss() {
            if (! this.dismissUrl) {
                return false;
            }
            const { meta, cookie } = this.csrfToken();
            const headers = {
                'X-CSRF-TOKEN': meta,
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json',
                'Content-Type': 'application/json',
            };
            if (cookie) {
                headers['X-XSRF-TOKEN'] = cookie;
            }
            const res = await fetch(this.dismissUrl, {
                method: 'POST',
                headers,
                credentials: 'same-origin',
                body: JSON.stringify({}),
            });
            if (! res.ok) {
                return false;
            }
            try {
                const data = await res.json();
                return Boolean(data && data.ok);
            } catch (e) {
                return false;
            }
        },
        async dismissForever() {
            try {
                window.localStorage.setItem(this.storageKey(), '1');
            } catch (e) {
                // ignore
            }
            this.dismissed = true;
            try {
                await this.postDismiss();
            } catch (e) {
                // localStorage already set; server retry happens next visit if needed
            }
            this.show = false;
            this.loading = false;
            document.body.classList.remove('overflow-y-hidden');
        },
        async terminate() {
            // End of tour: persist like "Ne plus afficher" so it does not return next visit.
            await this.dismissForever();
        },
    }));

    Alpine.data('clicflowShell', () => ({
        collapsed: false,
        mobileOpen: false,
        init() {
            try {
                this.collapsed = window.localStorage.getItem('clicflow.sidebar.collapsed') === '1';
            } catch (e) {
                this.collapsed = false;
            }
        },
        toggleCollapsed() {
            this.collapsed = ! this.collapsed;
            try {
                window.localStorage.setItem('clicflow.sidebar.collapsed', this.collapsed ? '1' : '0');
            } catch (e) {}
        },
    }));

    Alpine.directive('count-up', (el, { expression }, { evaluateLater, effect, cleanup }) => {
        const get = evaluateLater(expression);
        let frame;
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const run = (target) => {
            const n = Number(target);
            if (! Number.isFinite(n) || reduce) {
                el.textContent = target;
                return;
            }
            const start = performance.now();
            const from = 0;
            const dur = 700;
            const tick = (now) => {
                const t = Math.min(1, (now - start) / dur);
                const eased = 1 - Math.pow(1 - t, 3);
                const val = from + (n - from) * eased;
                el.textContent = Number.isInteger(n) ? Math.round(val).toLocaleString('fr-FR') : val.toFixed(1);
                if (t < 1) frame = requestAnimationFrame(tick);
            };
            frame = requestAnimationFrame(tick);
        };
        effect(() => get((v) => run(v)));
        cleanup(() => frame && cancelAnimationFrame(frame));
    });

    Alpine.data('dashboardLayoutEditor', (initial) => ({
        rows: initial.rows,
        saveUrl: initial.saveUrl,
        csrf: initial.csrf,
        redirectUrl: initial.redirectUrl ?? '/settings/dashboard',
        init() {
            this.$nextTick(() => {
                const el = this.$refs.sortList;
                if (! el) {
                    return;
                }
                Sortable.create(el, {
                    animation: 150,
                    handle: '[data-drag-handle]',
                    onEnd: () => {
                        const keys = [...el.children].map((n) => n.dataset.widgetKey);
                        const next = [];
                        keys.forEach((k) => {
                            const row = this.rows.find((r) => r.key === k);
                            if (row) {
                                next.push(row);
                            }
                        });
                        this.rows = next;
                        this.rows.forEach((r, i) => {
                            r.order = i;
                        });
                    },
                });
            });
        },
        async saveLayout() {
            const body = {
                widgets: this.rows.map((r, i) => ({
                    key: r.key,
                    enabled: !!r.enabled,
                    order: i,
                })),
            };
            const res = await fetch(this.saveUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            if (res.ok) {
                window.location.assign(this.redirectUrl);
                return;
            }
            let message = `HTTP ${res.status}`;
            try {
                const data = await res.json();
                if (data.message) {
                    message = data.message;
                }
                if (data.errors) {
                    message = Object.values(data.errors).flat().join(' ') || message;
                }
            } catch {
                // ignore
            }
            window.alert(message);
        },
    }));

    const buildInvoiceForm = (items, taxPreview, clientsStoreUrl, currencyMoneyMeta = {}, localeAmountSep = {}) => ({
        items,
        taxPreview,
        clientsStoreUrl,
        currencyMoneyMeta,
        amountDec: localeAmountSep.decimal ?? '.',
        amountThou: localeAmountSep.thousands ?? ',',
        minorScale: 100,
        fractionDigits: 2,
        currencyCode: 'EUR',
        clientMode: 'pick',
        extraClients: [],
        selectedQuickId: null,
        quickOpen: false,
        quickName: '',
        quickEmail: '',
        quickPhone: '',
        quickSource: '',
        quickAddressLine1: '',
        quickAddressCity: '',
        quickAddressCountry: '',
        quickAddressPostal: '',
        quickError: '',
        quickLoading: false,
        quickFailMsg: 'Could not save client.',
        _quickClientVoiceHandler: null,
        resetQuickClientForm() {
            this.quickName = '';
            this.quickEmail = '';
            this.quickPhone = '';
            this.quickSource = '';
            this.quickAddressLine1 = '';
            this.quickAddressCity = '';
            this.quickAddressCountry = '';
            this.quickAddressPostal = '';
            this.quickError = '';
        },
        openQuickClientModal(prefill = {}) {
            this.quickError = '';
            this.clientMode = 'pick';
            if (prefill?.name) {
                this.quickName = String(prefill.name).trim();
            } else {
                this.resetQuickClientForm();
            }
            this.quickOpen = true;
        },
        init() {
            const mode = this.$root.dataset.clientMode;
            if (mode === 'new' || mode === 'pick') {
                this.clientMode = mode;
            }
            if (this.$root.dataset.quickError) {
                this.quickFailMsg = this.$root.dataset.quickError;
            }
            const sel = document.getElementById('currency');
            const fromSelect = sel?.value ? String(sel.value).toUpperCase() : '';
            const fromPreview = this.taxPreview?.invoice_currency
                ? String(this.taxPreview.invoice_currency).toUpperCase()
                : '';
            const initial = fromSelect || fromPreview || 'USD';
            this.syncCurrencyMoney(initial, false);

            if (this.clientsStoreUrl) {
                this._quickClientVoiceHandler = (event) => {
                    const name = String(event?.detail?.name || '').trim();
                    this.openQuickClientModal(name ? { name } : {});
                };
                document.addEventListener('flowdesk-open-quick-client', this._quickClientVoiceHandler);
            }
        },
        syncCurrencyMoney(code, convertLines = true) {
            const c = String(code || 'USD').toUpperCase();
            const newMeta = this.currencyMoneyMeta[c] || { scale: 100, fractionDigits: 2 };
            const oldScale = this.minorScale;
            const newScale = Number(newMeta.scale) > 0 ? Number(newMeta.scale) : 100;
            if (convertLines && oldScale > 0 && newScale > 0 && oldScale !== newScale) {
                this.items.forEach((line) => {
                    const oldMinor = Math.round((Number(line.unit_major) || 0) * oldScale);
                    line.unit_major = oldMinor / newScale;
                });
            }
            this.currencyCode = c;
            this.minorScale = newScale;
            this.fractionDigits = Number(newMeta.fractionDigits) >= 0 ? Number(newMeta.fractionDigits) : 2;
        },
        fmt(n) {
            return String(Math.trunc(Number(n) || 0));
        },
        async submitQuickClient() {
            this.quickError = '';
            this.quickLoading = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const body = new URLSearchParams();
                body.set('name', this.quickName.trim());
                if (this.quickEmail.trim()) {
                    body.set('email', this.quickEmail.trim());
                }
                if (this.quickPhone.trim()) {
                    body.set('phone', this.quickPhone.trim());
                }
                if (this.quickSource) {
                    body.set('source', this.quickSource);
                }
                if (this.quickAddressLine1.trim()) {
                    body.set('address_line1', this.quickAddressLine1.trim());
                }
                if (this.quickAddressCity.trim()) {
                    body.set('address_city', this.quickAddressCity.trim());
                }
                if (this.quickAddressPostal.trim()) {
                    body.set('address_postal_code', this.quickAddressPostal.trim());
                }
                if (this.quickAddressCountry.trim()) {
                    body.set('address_country', this.quickAddressCountry.trim().toUpperCase());
                }
                const r = await fetch(this.clientsStoreUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body,
                    credentials: 'same-origin',
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                    this.quickError = first || data.message || this.quickFailMsg;
                    return;
                }
                if (data.client) {
                    this.extraClients.push({ id: data.client.id, name: data.client.name, email: data.client.email || '' });
                    this.selectedQuickId = data.client.id;
                    this.clientMode = 'pick';
                    this.quickOpen = false;
                    this.resetQuickClientForm();
                    this.$nextTick(() => {
                        const sel = document.getElementById('client_id');
                        if (sel) {
                            sel.value = data.client.id;
                            sel.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                }
            } catch (e) {
                this.quickError = this.quickFailMsg;
            } finally {
                this.quickLoading = false;
            }
        },
    });

    /** Overrides for buildInvoiceForm — must follow `{ ...baseObj, ...mixin }` so getters stay reactive. */
    const documentLineFormMixin = (baseObj) => ({
        linesRevision: 0,
        /** Document discount before VAT: '' | 'percent' | 'amount' (value in major units or %). */
        discountType: '',
        discountValue: '',
        discountNumber() {
            const raw = String(this.discountValue ?? '').replace(/[\s\u00a0%€]/g, '');
            if (raw === '') {
                return 0;
            }
            let s = raw;
            if (s.includes(',') && s.includes('.')) {
                s = s.lastIndexOf(',') > s.lastIndexOf('.') ? s.replace(/\./g, '').replace(',', '.') : s.replace(/,/g, '');
            } else {
                s = s.replace(',', '.');
            }
            const n = Number(s);

            return Number.isFinite(n) && n > 0 ? n : 0;
        },
        discountMinor() {
            void this.linesRevision;
            const lines = this.lineSubtotal;
            const n = this.discountNumber();
            if (!this.discountType || n <= 0 || lines <= 0) {
                return 0;
            }
            const scale = this.minorScale > 0 ? this.minorScale : 100;
            const minor = this.discountType === 'percent' ? Math.round((lines * Math.min(100, n)) / 100) : Math.round(n * scale);

            return Math.max(0, Math.min(lines, minor));
        },
        discountLabel() {
            const n = this.discountNumber();
            if (this.discountType === 'percent' && n > 0) {
                return String(Math.min(100, n)).replace('.', ',') + ' %';
            }

            return '';
        },
        init() {
            baseObj.init.call(this);
            if (!Array.isArray(this.items)) {
                this.items = [];
            }
            this.items.forEach((line) => {
                this.hydrateLineAmounts(line);
            });
            this.touchLines();
            window.addEventListener('cf-ai-chat-opened', () => {
                this.qpOpen = false;
                this.quickOpen = false;
            });
        },
        hydrateLineAmounts(line) {
            const scale = this.minorScale > 0 ? this.minorScale : 100;
            const display = String(line.unit_display ?? '').trim();
            let minor = Math.round(Number(line.unit_amount_minor));

            if (display !== '') {
                minor = Math.round(this.parseMajorInput(display) * scale);
            } else if (!Number.isFinite(minor) || minor <= 0) {
                minor = Math.round((Number(line.unit_major) || 0) * scale);
            }

            line.unit_amount_minor = minor;
            line.unit_display = this.formatMajorFromMinor(minor);
            line.unit_major = scale > 0 ? minor / scale : 0;
        },
        syncLineMinorFromDisplay(line) {
            const scale = this.minorScale > 0 ? this.minorScale : 100;
            const minor = Math.round(this.parseMajorInput(line.unit_display) * scale);
            line.unit_amount_minor = minor;
            line.unit_major = scale > 0 ? minor / scale : 0;

            return minor;
        },
        touchLines() {
            (Array.isArray(this.items) ? this.items : []).forEach((line) => {
                if (line && !line._k) {
                    this.lineSeq += 1;
                    line._k = `k${this.lineSeq}`;
                }
                if (line && (line.vat_rate === undefined || line.vat_rate === null)) {
                    line.vat_rate = this.defaultVatInput();
                }
            });
            this.linesRevision += 1;
        },
        get lineSubtotal() {
            void this.linesRevision;

            return (Array.isArray(this.items) ? this.items : []).reduce((s, l) => {
                void l.quantity;
                void l.unit_display;

                return s + (Number(l.quantity) || 0) * this.unitMinor(l);
            }, 0);
        },
        get totals() {
            void this.linesRevision;
            void this.discountType;
            void this.discountValue;
            const lines = this.lineSubtotal;
            const discount = this.discountMinor();
            const sub = lines - discount;
            const breakdown = this.vatBreakdownFor(lines, discount);
            const vat = breakdown.reduce((s, r) => s + r.vat, 0);
            const stamp =
                this.taxPreview.fiscal_stamp_enabled && Number(this.taxPreview.fiscal_stamp_minor) > 0
                    ? Number(this.taxPreview.fiscal_stamp_minor)
                    : 0;

            return { lines, discount, subtotal: sub, vat, stamp, total: sub + vat + stamp, breakdown };
        },
        /** Line VAT rate (%): the line's own rate, or the workspace default when empty. */
        lineRate(line) {
            const raw = String(line?.vat_rate ?? '').replace('%', '').replace(',', '.').trim();
            const n = raw === '' ? NaN : Number(raw);
            if (Number.isFinite(n) && n >= 0) {
                return Math.min(100, Math.round(n * 1000) / 1000);
            }

            return Number(this.taxPreview?.vat_percent) || 0;
        },
        rateInput(n) {
            const s = String(Math.round((Number(n) || 0) * 1000) / 1000);

            return this.amountDec === ',' ? s.replace('.', ',') : s;
        },
        defaultVatInput() {
            return this.rateInput(Number(this.taxPreview?.vat_percent) || 0);
        },
        rateLabel(rate) {
            return String(Math.round((Number(rate) || 0) * 1000) / 1000).replace('.', ',') + ' %';
        },
        /** Same algorithm as InvoiceTotalsService::splitVat (discount spread over rate groups before VAT). */
        vatBreakdownFor(lines, discount) {
            const groups = new Map();
            (Array.isArray(this.items) ? this.items : []).forEach((l) => {
                void l.vat_rate;
                const ht = Math.round((Number(l.quantity) || 0) * this.unitMinor(l));
                const key = this.lineRate(l).toFixed(3);
                groups.set(key, (groups.get(key) || 0) + ht);
            });
            if (groups.size === 0) {
                groups.set((Number(this.taxPreview?.vat_percent) || 0).toFixed(3), 0);
            }
            const alloc = new Map();
            let given = 0;
            let largest = null;
            groups.forEach((ht, key) => {
                const d = lines > 0 ? Math.floor((discount * ht) / lines) : 0;
                alloc.set(key, d);
                given += d;
                if (largest === null || ht > groups.get(largest)) {
                    largest = key;
                }
            });
            if (largest !== null) {
                alloc.set(largest, alloc.get(largest) + discount - given);
            }
            const out = [];
            groups.forEach((ht, key) => {
                const rate = Number(key);
                const net = ht - alloc.get(key);
                out.push({ rate, base: net, vat: rate > 0 ? Math.round((net * rate) / 100) : 0, label: this.rateLabel(rate) });
            });
            out.sort((a, b) => b.rate - a.rate);

            return out;
        },
        lineSeq: 0,
        dragFrom: null,
        dragOver: null,
        newLine(extra = {}) {
            this.lineSeq += 1;

            return {
                _k: `n${this.lineSeq}`,
                title: '',
                description: '',
                quantity: 1,
                unit_major: 0,
                unit_amount_minor: 0,
                unit_display: this.pricePlaceholder(),
                product_id: null,
                product_variant_id: null,
                unit: '',
                vat_rate: this.defaultVatInput(),
                catalog_detail: '',
                ...extra,
            };
        },
        focusLine(index) {
            this.$nextTick(() => {
                const inputs = this.$root.querySelectorAll('[data-line-title]');
                const el = inputs[Math.max(0, Math.min(index, inputs.length - 1))];
                el?.focus();
            });
        },
        addLine(focus = true) {
            this.items = [...this.items, this.newLine()];
            this.touchLines();
            if (focus) {
                this.focusLine(this.items.length - 1);
            }
        },
        removeLine(i) {
            if (this.items.length <= 1) {
                this.items = [this.newLine()];
            } else {
                this.items = this.items.filter((_, k) => k !== i);
            }
            this.touchLines();
        },
        moveLine(from, to) {
            if (from === to || from < 0 || to < 0 || from >= this.items.length || to >= this.items.length) {
                return;
            }
            const next = [...this.items];
            const [row] = next.splice(from, 1);
            next.splice(to, 0, row);
            this.items = next;
            this.touchLines();
        },
        onLineEnter(i) {
            if (i >= this.items.length - 1) {
                this.addLine();
            } else {
                this.focusLine(i + 1);
            }
        },
        onDragStart(i, event) {
            this.dragFrom = i;
            try {
                event.dataTransfer.effectAllowed = 'move';
                event.dataTransfer.setData('text/plain', String(i));
                const row = event.target.closest('[data-line-row]');
                if (row) {
                    event.dataTransfer.setDragImage(row, 16, 16);
                }
            } catch {
                // ignore
            }
        },
        onDrop(i) {
            if (this.dragFrom !== null) {
                this.moveLine(this.dragFrom, i);
            }
            this.dragFrom = null;
            this.dragOver = null;
        },
        /* ---- Quick add product / service (right slide-over) ---- */
        qpOpen: false,
        qpIndex: null,
        qpLoading: false,
        qpError: '',
        qp: { kind: 'service', name: '', description: '', price: '', tax_rate: '', unit: '', category: '' },
        qpUnits() {
            const cfg = this.editorCfg || {};

            return (this.qp.kind === 'product' ? cfg.productUnits : cfg.serviceUnits) || [];
        },
        openQuickProduct(index = null) {
            const line = Number.isInteger(index) ? this.items[index] : null;
            this.qpIndex = Number.isInteger(index) ? index : null;
            this.qp = {
                kind: 'service',
                name: line && !line.product_id ? String(line.description || '').slice(0, 255) : '',
                description: '',
                price: line && !line.product_id && this.unitMinor(line) > 0 ? this.formatMajorFromMinor(this.unitMinor(line)) : '',
                tax_rate: line ? this.rateInput(this.lineRate(line)) : this.defaultVatInput(),
                unit: '',
                category: '',
            };
            this.qpError = '';
            this.quickOpen = false;
            this.qpOpen = true;
            window.dispatchEvent(new CustomEvent('cf-ai-chat-close'));
            this.$nextTick(() => document.getElementById('cf_qp_name')?.focus());
        },
        openQuickClientAside(prefill = {}) {
            this.qpOpen = false;
            window.dispatchEvent(new CustomEvent('cf-ai-chat-close'));
            this.openQuickClientModal(prefill);
        },
        async submitQuickProduct() {
            const url = this.editorCfg?.productStoreUrl;
            if (!url || this.qpLoading) {
                return;
            }
            this.qpError = '';
            if (!String(this.qp.name || '').trim()) {
                this.qpError = this.editorCfg?.errName || 'Name is required.';

                return;
            }
            this.qpLoading = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const body = new FormData();
                body.append('name', String(this.qp.name).trim());
                body.append('kind', this.qp.kind === 'product' ? 'product' : 'service');
                body.append('description', String(this.qp.description || ''));
                body.append('base_price', String(this.parseMajorInput(this.qp.price)));
                body.append('currency', this.currencyCode);
                body.append('unit', String(this.qp.unit || ''));
                const rate = String(this.qp.tax_rate ?? '').replace('%', '').replace(',', '.').trim();
                body.append('tax_rate', rate);
                body.append('category', String(this.qp.category || ''));
                body.append('track_stock', '0');
                const r = await fetch(url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                    credentials: 'same-origin',
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.product) {
                    const first = data.errors ? Object.values(data.errors).flat()[0] : null;
                    this.qpError = first || data.message || this.editorCfg?.errSave || 'Could not save.';

                    return;
                }
                const p = data.product;
                this.catalog = [...(this.catalog || []), p];
                let index = this.qpIndex;
                if (!Number.isInteger(index) || !this.items[index]) {
                    const last = this.items[this.items.length - 1];
                    if (last && !last.product_id && !String(last.description || '').trim() && !String(last.title || '').trim() && this.unitMinor(last) <= 0) {
                        index = this.items.length - 1;
                    } else {
                        this.items = [...this.items, this.newLine()];
                        index = this.items.length - 1;
                    }
                }
                const line = this.items[index];
                line.product_id = String(p.id);
                this.applyCatalog(line);
                await this.$nextTick();
                // Options of the new product render after x-model: re-apply so the select shows it.
                line.product_id = '';
                await this.$nextTick();
                line.product_id = String(p.id);
                this.qpOpen = false;
                this.touchLines();
                this.focusLine(index);
            } catch {
                this.qpError = this.editorCfg?.errSave || 'Could not save.';
            } finally {
                this.qpLoading = false;
            }
        },
        lineHt(line) {
            void this.linesRevision;
            void line.quantity;
            void line.unit_display;

            return Math.round((Number(line.quantity) || 0) * this.unitMinor(line));
        },
        lineVat(line) {
            void this.linesRevision;
            void line.vat_rate;
            const ht = this.lineHt(line);
            const lines = this.lineSubtotal;
            if (ht <= 0 || lines <= 0) {
                return 0;
            }
            const ratio = (lines - this.discountMinor()) / lines;

            return Math.round((ht * ratio * this.lineRate(line)) / 100);
        },
        lineTtc(line) {
            void this.linesRevision;

            return this.lineHt(line) + this.lineVat(line);
        },
        onLineQtyInput() {
            this.touchLines();
        },
        onLinePriceInput(line) {
            this.syncLineMinorFromDisplay(line);
            this.touchLines();
        },
        onLinePriceBlur(line) {
            this.syncLineMinorFromDisplay(line);
            line.unit_display = this.formatMajorFromMinor(line.unit_amount_minor);
            this.touchLines();
        },
        inputFractionDigits() {
            if (this.currencyCode === 'TND') {
                return 3;
            }

            return this.fractionDigits > 0 ? this.fractionDigits : 2;
        },
        pricePlaceholder() {
            const fd = this.inputFractionDigits();

            return '0' + this.amountDec + (fd > 0 ? '0'.repeat(fd) : '');
        },
        formatMajorInput(n) {
            const fd = this.inputFractionDigits();
            const num = Number(n) || 0;
            if (fd <= 0) {
                return String(Math.round(num));
            }
            let fixed = num.toFixed(fd);
            fixed = fixed.replace(/(\.\d*?)0+$/, '$1').replace(/\.$/, '');

            return fixed.replace('.', this.amountDec);
        },
        formatMajorFromMinor(minor) {
            const scale = this.minorScale > 0 ? this.minorScale : 100;
            const fd = this.inputFractionDigits();
            let m = Math.trunc(Number(minor) || 0);
            const neg = m < 0;
            const abs = Math.abs(m);
            const whole = Math.floor(abs / scale);
            const rem = abs % scale;
            if (fd <= 0) {
                return String(neg ? -whole : whole);
            }
            let fracStr = String(rem).padStart(fd, '0');
            if (fracStr.length > fd) {
                fracStr = fracStr.slice(0, fd);
            }
            fracStr = fracStr.replace(/0+$/, '');
            const major = fracStr ? parseFloat(`${whole}.${fracStr}`) : whole;

            return this.formatMajorInput(neg ? -major : major);
        },
        parseMajorInput(str) {
            const raw = String(str ?? '')
                .trim()
                .replace(new RegExp('\\' + this.amountThou, 'g'), '')
                .replace(this.amountDec, '.');
            const n = parseFloat(raw);

            return Number.isFinite(n) ? n : 0;
        },
        unitMinor(line) {
            const display = String(line.unit_display ?? '').trim();
            if (display !== '') {
                return Math.round(this.parseMajorInput(display) * this.minorScale);
            }
            const cached = Math.round(Number(line.unit_amount_minor));
            if (Number.isFinite(cached) && cached > 0) {
                return cached;
            }

            return Math.round((Number(line.unit_major) || 0) * this.minorScale);
        },
        fmtMinor(n) {
            const scale = this.minorScale > 0 ? this.minorScale : 100;
            const fd = this.inputFractionDigits();
            let minor = Math.trunc(Number(n) || 0);
            const neg = minor < 0;
            const a = Math.abs(minor);
            const whole = Math.floor(a / scale);
            const rem = a % scale;
            if (fd <= 0) {
                const v = neg ? -whole : whole;

                return flowdeskNumberFormat(v, 0, this.amountDec, this.amountThou);
            }
            let fracStr = String(rem).padStart(fd, '0');
            if (fracStr.length > fd) {
                fracStr = fracStr.slice(0, fd);
            }
            const left = flowdeskNumberFormat(neg ? -whole : whole, 0, this.amountDec, this.amountThou);

            return left + this.amountDec + fracStr;
        },
        syncCurrencyMoney(code, convertLines = true) {
            baseObj.syncCurrencyMoney.call(this, code, convertLines);
            this.items.forEach((line) => {
                this.hydrateLineAmounts(line);
            });
            this.touchLines();
        },
        mapAiSuggestedLines(rows) {
            return rows.map((row) => {
                const minor = Math.round((Number(row.unit_major) || 0) * this.minorScale);

                return {
                    title: row.title ?? '',
                    description: row.description ?? '',
                    quantity: Number(row.quantity) || 1,
                    unit_major: Number(row.unit_major) || 0,
                    unit_amount_minor: minor,
                    unit_display: this.formatMajorFromMinor(minor),
                    vat_rate: row.vat_rate !== undefined && row.vat_rate !== null && row.vat_rate !== '' ? this.rateInput(row.vat_rate) : this.defaultVatInput(),
                };
            });
        },
    });

    /** Merge base form data with line-calc mixin without invoking getters via spread/assign. */
    const composeDocumentLineForm = (baseObj, extras = {}) => {
        const form = {};

        for (const key of Object.keys(baseObj)) {
            const desc = Object.getOwnPropertyDescriptor(baseObj, key);
            if (desc && !desc.get && !desc.set) {
                Object.defineProperty(form, key, desc);
            }
        }

        const mixin = documentLineFormMixin(baseObj);
        for (const key of Object.keys(mixin)) {
            const desc = Object.getOwnPropertyDescriptor(mixin, key);
            if (desc) {
                Object.defineProperty(form, key, desc);
            }
        }

        for (const key of Object.keys(extras)) {
            const desc = Object.getOwnPropertyDescriptor(extras, key);
            if (desc) {
                Object.defineProperty(form, key, desc);
            }
        }

        return form;
    };

    const buildDocumentLineAiSuggest = (briefRequiredMsg) =>
        async function suggestAiLines() {
            const url = this.aiConfig?.url;
            if (!url) {
                return;
            }
            const brief = String(this.aiBrief ?? '').trim();
            if (brief.length < 10) {
                this.aiError = this.aiConfig?.errBrief || briefRequiredMsg;

                return;
            }
            this.aiLoading = true;
            this.aiError = '';
            this.cueNovaWorkingSpeech();
            flowdeskNotifyVoiceBusy('generate');
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const payload = { brief, replace: this.aiReplace };
                if (this.aiConfig.draft) {
                    payload.currency = this.currencyCode;
                    const nameEl = document.getElementById('name');
                    if (nameEl?.value) {
                        payload.name = nameEl.value;
                    }
                    const clientSel = document.getElementById('client_id');
                    if (clientSel?.value) {
                        payload.client_id = clientSel.value;
                    }
                    const projectSel = document.getElementById('project_id');
                    if (projectSel?.value) {
                        payload.project_id = projectSel.value;
                    }
                    const validEl = document.getElementById('valid_until');
                    if (validEl?.value) {
                        payload.valid_until = validEl.value;
                    }
                }
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                    credentials: 'same-origin',
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.aiError = data.message || this.aiConfig?.errNetwork || 'AI request failed.';

                    return;
                }
                const rows = Array.isArray(data.items) ? data.items : [];
                const mapped = this.mapAiSuggestedLines(rows);
                if (mapped.length === 0) {
                    this.aiError = this.aiConfig?.errEmpty || 'No lines returned.';

                    return;
                }
                await this.applyAiSuggestedLines(mapped, this.aiReplace);
                this.aiBrief = '';
            } catch {
                this.aiError = this.aiConfig?.errNetwork || 'Network error.';
            } finally {
                this.aiLoading = false;
                flowdeskNotifyVoiceIdle('generate');
            }
        };

    const buildDocumentLineScan = () =>
        async function scanDocumentLines() {
            const url = this.aiConfig?.scanUrl;
            if (!url || !this.scanFile) {
                this.scanError = this.aiConfig?.errScanFile || 'Choose a document first.';

                return;
            }
            this.scanLoading = true;
            this.scanError = '';
            this.cueNovaWorkingSpeech();
            flowdeskNotifyVoiceBusy('generate');
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const form = new FormData();
                form.append('document', this.scanFile);
                form.append('replace', this.scanReplace ? '1' : '0');
                if (this.aiConfig.draft) {
                    form.append('currency', this.currencyCode);
                }
                const r = await fetch(url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: form,
                    credentials: 'same-origin',
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok) {
                    this.scanError = data.message || this.aiConfig?.errScanNetwork || 'Scan failed.';

                    return;
                }
                const rows = Array.isArray(data.items) ? data.items : [];
                const mapped = this.mapAiSuggestedLines(rows);
                if (mapped.length === 0) {
                    this.scanError = this.aiConfig?.errScanEmpty || 'No lines returned.';

                    return;
                }
                await this.applyAiSuggestedLines(mapped, this.scanReplace);
                this.scanFile = null;
                if (this.$refs.scanFileInput) {
                    this.$refs.scanFileInput.value = '';
                }
            } catch {
                this.scanError = this.aiConfig?.errScanNetwork || 'Network error.';
            } finally {
                this.scanLoading = false;
                flowdeskNotifyVoiceIdle('generate');
            }
        };

    const sleepMs = (ms) => new Promise((resolve) => window.setTimeout(resolve, ms));

    const documentLineAiExtras = (aiConfig, briefRequiredMsg) => {
        if (!aiConfig?.url) {
            return {};
        }

        const extras = {
            aiConfig,
            aiBrief: aiConfig.initialBrief || '',
            aiLoading: false,
            aiError: '',
            aiReplace: true,
            suggestAiLines: buildDocumentLineAiSuggest(briefRequiredMsg),
            cueNovaWorkingSpeech() {
                const text = String(this.aiConfig?.workingSpeech || '').trim();
                const speakUrl = this.aiConfig?.speakUrl || null;
                if (!text || !speakUrl) {
                    return;
                }
                void flowdeskSpeakNovaText({
                    text,
                    speakUrl,
                    purpose: 'ui',
                    speechLocale: null,
                    synthesis: typeof window !== 'undefined' ? window.speechSynthesis : null,
                    browserFallbackHint: '',
                    onNotice: () => {},
                    onStart: () => {},
                    onEnd: () => {},
                });
            },
            async applyAiSuggestedLines(mapped, replace) {
                if (replace) {
                    this.items = [];
                    this.touchLines();
                }
                for (let i = 0; i < mapped.length; i += 1) {
                    this.items = [...this.items, mapped[i]];
                    this.touchLines();
                    if (i < mapped.length - 1) {
                        await sleepMs(70);
                    }
                }
            },
        };

        if (aiConfig.scanUrl) {
            extras.scanFile = null;
            extras.scanLoading = false;
            extras.scanError = '';
            extras.scanReplace = true;
            extras.scanDocumentLines = buildDocumentLineScan();
        }

        return extras;
    };

    Alpine.data('invoiceForm', (items, taxPreview, clientsStoreUrl, currencyMoneyMeta, localeAmountSep, aiConfig = null, catalog = [], editorCfg = {}) => {
        const baseObj = buildInvoiceForm(items, taxPreview, clientsStoreUrl, currencyMoneyMeta, localeAmountSep);

        return composeDocumentLineForm(
            {
                ...baseObj,
                editorCfg: editorCfg || {},
                catalog: Array.isArray(catalog) ? catalog : [],
                applyCatalog(line) {
                    const id = line.product_id || '';
                    const product = (this.catalog || []).find((p) => String(p.id) === String(id));
                    if (!product) {
                        line.product_variant_id = null;
                        line.catalog_detail = '';
                        return;
                    }
                    // Title = product name, description = product description (both editable).
                    line.title = String(product.name || '').trim();
                    line.description = String(product.description || '').trim();
                    line.unit = product.unit || '';
                    line.product_variant_id = product.variant_id || null;
                    line.unit_amount_minor = Number(product.price_minor) || 0;
                    const scale = this.minorScale > 0 ? this.minorScale : 100;
                    line.unit_major = line.unit_amount_minor / scale;
                    line.unit_display = this.formatMajorFromMinor(line.unit_amount_minor);
                    // The product's own VAT rate fills the line (workspace default when the product has none).
                    line.vat_rate = product.tax_rate !== null && product.tax_rate !== undefined
                        ? this.rateInput(product.tax_rate)
                        : this.defaultVatInput();
                    line.catalog_detail = [
                        product.kind_label || product.kind || '',
                        product.unit ? String(product.unit) : '',
                        product.sku ? `SKU ${product.sku}` : '',
                        product.tax_label ? `TVA ${product.tax_label}` : '',
                    ]
                        .filter(Boolean)
                        .join(' · ');
                    this.touchLines();
                },
            },
            documentLineAiExtras(aiConfig, 'Describe what to invoice (min. 10 characters).'),
        );
    });

    /** Searchable client picker over the native <select name="client_id"> (kept for forms, AI fill and tests). */
    Alpine.data('cfClientPicker', () => ({
        q: '',
        open: false,
        active: 0,
        options: [],
        label: '',
        init() {
            const sel = this.sel();
            if (!sel) {
                return;
            }
            this.$nextTick(() => this.refresh());
            sel.addEventListener('change', () => this.refresh());
            new MutationObserver(() => this.refresh()).observe(sel, { childList: true, subtree: true });
        },
        sel() {
            return this.$root.querySelector('select[name="client_id"]');
        },
        refresh() {
            const sel = this.sel();
            if (!sel) {
                return;
            }
            this.options = [...sel.options]
                .filter((o) => o.value)
                .map((o) => ({ id: o.value, name: o.textContent.trim(), email: o.dataset.email || '' }));
            const cur = sel.options[sel.selectedIndex];
            this.label = cur && cur.value ? cur.textContent.trim() : '';
        },
        get filtered() {
            const fold = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
            const want = fold(this.q).trim();
            const list = want === '' ? this.options : this.options.filter((o) => fold(`${o.name} ${o.email}`).includes(want));

            return list.slice(0, 60);
        },
        pick(o) {
            const sel = this.sel();
            if (!sel) {
                return;
            }
            sel.value = o ? String(o.id) : '';
            sel.dispatchEvent(new Event('change', { bubbles: true }));
            this.q = '';
            this.open = false;
        },
    }));

    Alpine.data('quoteForm', (items, taxPreview, currencyMoneyMeta, localeAmountSep, aiConfig = {}, clientsStoreUrl = '', catalog = [], editorCfg = {}) => {
        const baseObj = buildInvoiceForm(items, taxPreview, clientsStoreUrl, currencyMoneyMeta, localeAmountSep);

        return composeDocumentLineForm(
            {
                ...baseObj,
                editorCfg: editorCfg || {},
                catalog: Array.isArray(catalog) ? catalog : [],
                applyCatalog(line) {
                    const id = line.product_id || '';
                    const product = (this.catalog || []).find((p) => String(p.id) === String(id));
                    if (!product) {
                        line.product_variant_id = null;
                        line.catalog_detail = '';
                        return;
                    }
                    // Title = product name, description = product description (both editable).
                    line.title = String(product.name || '').trim();
                    line.description = String(product.description || '').trim();
                    line.unit = product.unit || '';
                    line.product_variant_id = product.variant_id || null;
                    line.unit_amount_minor = Number(product.price_minor) || 0;
                    const scale = this.minorScale > 0 ? this.minorScale : 100;
                    line.unit_major = line.unit_amount_minor / scale;
                    line.unit_display = this.formatMajorFromMinor(line.unit_amount_minor);
                    // The product's own VAT rate fills the line (workspace default when the product has none).
                    line.vat_rate = product.tax_rate !== null && product.tax_rate !== undefined
                        ? this.rateInput(product.tax_rate)
                        : this.defaultVatInput();
                    line.catalog_detail = [
                        product.kind_label || product.kind || '',
                        product.unit ? String(product.unit) : '',
                        product.sku ? `SKU ${product.sku}` : '',
                        product.tax_label ? `TVA ${product.tax_label}` : '',
                    ]
                        .filter(Boolean)
                        .join(' · ');
                    this.touchLines();
                },
            },
            documentLineAiExtras(aiConfig, 'Describe what to quote (min. 10 characters).'),
        );
    });

    Alpine.data('projectsAiModal', (cfg = {}) => ({
        aiModalOpen: Boolean(cfg.open),
        aiSubmitting: false,
        openModal() {
            this.aiModalOpen = true;
        },
        closeModal() {
            if (this.aiSubmitting) {
                return;
            }
            this.aiModalOpen = false;
        },
        submitProjectsAi() {
            const form = this.$refs.projectsAiForm;
            if (!form) {
                return false;
            }
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                form.reportValidity();
                return false;
            }
            this.aiSubmitting = true;
            this.aiModalOpen = true;
            try {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            } catch {
                this.aiSubmitting = false;
                return false;
            }
            return true;
        },
    }));

    Alpine.data('projectDescriptionAi', (cfg) => ({
        open: false,
        busy: false,
        err: null,
        prompt: '',
        applyMode: 'replace',
        suggestUrl: cfg.suggestUrl,
        textareaId: cfg.textareaId || 'description',
        errEmpty: cfg.errEmpty || 'Empty response',
        errNetwork: cfg.errNetwork || 'Network error',
        errPromptRequired: cfg.errPromptRequired || 'Enter instructions first.',
        openModal() {
            this.open = true;
            this.err = null;
        },
        closeModal() {
            if (this.busy) {
                return;
            }
            this.open = false;
        },
        applyDescriptionHtml(html, replace) {
            const target = document.getElementById(this.textareaId);
            if (!target) {
                return false;
            }
            const $ = window.jQuery;
            const $el = $ ? $(target) : null;
            const summernoteReady = Boolean($el && $el.data('summernote'));

            if (summernoteReady) {
                try {
                    const cur = $el.summernote('code');
                    const merged = replace
                        ? html
                        : `${typeof cur === 'string' && cur.trim() !== '' ? cur : ''}<br><br>${html}`;
                    $el.summernote('code', merged);
                    target.value = merged;
                    target.dispatchEvent(new Event('input', { bubbles: true }));
                    target.dispatchEvent(new Event('change', { bubbles: true }));
                    return true;
                } catch {
                    /* fall through to plain textarea */
                }
            }

            const merged = replace
                ? html
                : `${target.value || ''}${target.value ? '<br><br>' : ''}${html}`;
            target.value = merged;
            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));
            return true;
        },
        async generate() {
            const promptEl = document.getElementById('ai_desc_prompt');
            const promptText = (promptEl?.value ?? this.prompt ?? '').trim();
            if (!promptText) {
                this.err = this.errPromptRequired;
                return;
            }
            this.prompt = promptText;
            this.busy = true;
            this.err = null;
            const replace = this.applyMode === 'replace';
            flowdeskNotifyVoiceBusy('generate');
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const r = await fetch(this.suggestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        mode: 'project_description',
                        context: promptText,
                    }),
                    credentials: 'same-origin',
                });
                const text = await r.text();
                let data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch {
                    data = {};
                }
                if (!r.ok) {
                    this.err = data.message || `HTTP ${r.status}`;
                    return;
                }
                const html = typeof data.suggestion === 'string' ? data.suggestion.trim() : '';
                if (html === '') {
                    this.err = this.errEmpty;
                    return;
                }
                if (!this.applyDescriptionHtml(html, replace)) {
                    this.err = this.errNetwork;
                    return;
                }
                if (promptEl) {
                    promptEl.value = '';
                }
                this.prompt = '';
                this.open = false;
            } catch (e) {
                this.err = this.errNetwork;
            } finally {
                this.busy = false;
                flowdeskNotifyVoiceIdle('generate');
            }
        },
    }));

    Alpine.data('productCatalogForm', (cfg = {}) => ({
        kind: cfg.kind || 'product',
        unit: cfg.unit || '',
        customUnit: '',
        name: cfg.name || '',
        description: cfg.description || '',
        productUnits: Array.isArray(cfg.productUnits) ? cfg.productUnits : [],
        serviceUnits: Array.isArray(cfg.serviceUnits) ? cfg.serviceUnits : [],
        open: false,
        busy: false,
        err: null,
        prompt: '',
        suggestUrl: cfg.suggestUrl || '',
        errEmpty: cfg.errEmpty || 'Empty response',
        errNetwork: cfg.errNetwork || 'Network error',
        errPromptRequired: cfg.errPromptRequired || 'Enter instructions first.',
        get units() {
            return this.kind === 'service' ? this.serviceUnits : this.productUnits;
        },
        get resolvedUnit() {
            if (this.unit === '__custom__') {
                return String(this.customUnit || '').trim();
            }
            return this.unit || '';
        },
        init() {
            const known = this.units.map((u) => u.value);
            if (this.unit && !known.includes(this.unit)) {
                this.customUnit = this.unit;
                this.unit = '__custom__';
            } else if (!this.unit) {
                this.unit = this.kind === 'service' ? 'hour' : 'pcs';
            }
        },
        onKindChange() {
            const known = this.units.map((u) => u.value);
            if (this.unit !== '__custom__' && !known.includes(this.unit)) {
                this.unit = this.kind === 'service' ? 'hour' : 'pcs';
            }
        },
        openModal() {
            this.open = true;
            this.err = null;
        },
        closeModal() {
            if (this.busy) {
                return;
            }
            this.open = false;
        },
        applyCopy(name, description, unit) {
            if (name) {
                this.name = name;
            }
            if (description) {
                this.description = description;
            }
            const known = this.units.map((u) => u.value);
            if (unit && known.includes(unit)) {
                this.unit = unit;
                this.customUnit = '';
            } else if (unit) {
                this.unit = '__custom__';
                this.customUnit = unit;
            }
            const nameEl = document.getElementById('name');
            if (nameEl) {
                nameEl.value = this.name;
                nameEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
            const descEl = document.getElementById('description');
            if (descEl) {
                descEl.value = this.description;
                descEl.dispatchEvent(new Event('input', { bubbles: true }));
            }
        },
        async generate() {
            const promptEl = document.getElementById('catalog_ai_prompt');
            const promptText = (promptEl?.value ?? this.prompt ?? '').trim();
            if (!promptText) {
                this.err = this.errPromptRequired;
                return;
            }
            this.prompt = promptText;
            this.busy = true;
            this.err = null;
            flowdeskNotifyVoiceBusy('generate');
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const r = await fetch(this.suggestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        brief: promptText,
                        kind: this.kind || 'product',
                        name: this.name || '',
                        description: this.description || '',
                    }),
                    credentials: 'same-origin',
                });
                const text = await r.text();
                let data = {};
                try {
                    data = text ? JSON.parse(text) : {};
                } catch {
                    data = {};
                }
                if (!r.ok) {
                    this.err = data.message || `HTTP ${r.status}`;
                    return;
                }
                const name = typeof data.name === 'string' ? data.name.trim() : '';
                const description = typeof data.description === 'string' ? data.description.trim() : '';
                if (name === '' && description === '') {
                    this.err = this.errEmpty;
                    return;
                }
                this.applyCopy(name, description, typeof data.unit === 'string' ? data.unit.trim() : '');
                if (promptEl) {
                    promptEl.value = '';
                }
                this.prompt = '';
                this.open = false;
            } catch {
                this.err = this.errNetwork;
            } finally {
                this.busy = false;
                flowdeskNotifyVoiceIdle('generate');
            }
        },
    }));

    Alpine.data('invoicePaymentQuickFill', (cfg) => ({
        totalMinor: Number(cfg.totalMinor) || 0,
        paidMinor: Number(cfg.paidMinor) || 0,
        balanceMinor: Number(cfg.balanceMinor) || 0,
        scale: Number(cfg.scale) > 0 ? Number(cfg.scale) : 100,
        fractionDigits: Number(cfg.fractionDigits) >= 0 ? Number(cfg.fractionDigits) : 2,
        dec: cfg.dec ?? '.',
        thou: cfg.thou ?? ',',
        currency: cfg.currency ?? 'EUR',
        picks: Array.isArray(cfg.picks) ? cfg.picks : [],
        draftRemainingMinor: Number(cfg.balanceMinor) || 0,
        parseMajorToMinor(str) {
            const raw = String(str ?? '')
                .trim()
                .replace(/\s/g, '')
                .replace(',', '.');
            if (raw === '') {
                return 0;
            }
            const n = Number.parseFloat(raw);
            if (!Number.isFinite(n)) {
                return 0;
            }
            return Math.round(n * this.scale);
        },
        fmtDisplay(minor) {
            const m = Math.trunc(Number(minor) || 0);
            const scale = this.scale;
            const fd = this.fractionDigits;
            const neg = m < 0;
            const a = Math.abs(m);
            const whole = Math.floor(a / scale);
            const rem = a % scale;
            if (fd <= 0) {
                const v = neg ? -whole : whole;
                return flowdeskNumberFormat(v, 0, this.dec, this.thou);
            }
            let fracStr = String(rem).padStart(fd, '0');
            if (fracStr.length > fd) {
                fracStr = fracStr.slice(0, fd);
            }
            const left = flowdeskNumberFormat(neg ? -whole : whole, 0, this.dec, this.thou);
            return left + this.dec + fracStr;
        },
        onAmountInput(e) {
            const minor = this.parseMajorToMinor(e.target.value);
            this.draftRemainingMinor = Math.max(0, this.balanceMinor - minor);
        },
        applyQuickPick(amountMajor, amountMinor, percent) {
            const el = document.getElementById('amount');
            if (el) {
                el.value = amountMajor;
            }
            const m = Math.min(Number(amountMinor) || 0, this.balanceMinor);
            this.draftRemainingMinor = Math.max(0, this.balanceMinor - m);
            const kind = document.getElementById('payment_kind');
            if (kind) {
                if (Number(percent) < 100) {
                    const dep = kind.querySelector('option[value="deposit"]');
                    if (dep) {
                        kind.value = 'deposit';
                    }
                } else {
                    const std = kind.querySelector('option[value="standard"]');
                    if (std) {
                        kind.value = 'standard';
                    }
                }
            }
        },
        init() {
            const el = document.getElementById('amount');
            if (el && el.value) {
                this.onAmountInput({ target: el });
            }
        },
    }));

    Alpine.data('portalInvoicePay', (cfg) => ({
        methods: Array.isArray(cfg.methods) ? cfg.methods : [],
        selectedMethod: Array.isArray(cfg.methods) && cfg.methods[0] ? cfg.methods[0].id : null,
        stripeUrl: cfg.stripeUrl || null,
        paypalUrl: cfg.paypalUrl || null,
        flouciUrl: cfg.flouciUrl || null,
        stripePk: cfg.stripePublishableKey || null,
        loading: false,
        err: '',
        stripeClientSecret: null,
        stripeInstance: null,
        stripeElements: null,
        showStripeForm: false,
        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.content || '';
        },
        async postJson(url) {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                throw new Error(data.message || res.statusText || 'Request failed');
            }

            return data;
        },
        async payPayPal() {
            if (!this.paypalUrl) {
                return;
            }
            this.loading = true;
            this.err = '';
            try {
                const data = await this.postJson(this.paypalUrl);
                if (data.approval_url) {
                    window.location.href = data.approval_url;

                    return;
                }
                throw new Error('PayPal approval URL missing');
            } catch (e) {
                this.err = e.message || 'PayPal failed';
                this.loading = false;
            }
        },
        async payFlouci() {
            if (!this.flouciUrl) {
                return;
            }
            this.loading = true;
            this.err = '';
            try {
                const data = await this.postJson(this.flouciUrl);
                if (data.payment_url) {
                    window.location.href = data.payment_url;

                    return;
                }
                throw new Error('Flouci payment URL missing');
            } catch (e) {
                this.err = e.message || 'Flouci failed';
                this.loading = false;
            }
        },
        async initStripeForm() {
            if (!this.stripeUrl || !this.stripePk || typeof window.Stripe === 'undefined') {
                this.err = 'Stripe is not available.';

                return false;
            }
            if (this.stripeElements) {
                return true;
            }
            const data = await this.postJson(this.stripeUrl);
            const secret = data.client_secret;
            if (!secret) {
                throw new Error('Missing payment session');
            }
            this.stripeClientSecret = secret;
            this.stripeInstance = window.Stripe(this.stripePk);
            this.stripeElements = this.stripeInstance.elements({ clientSecret: secret });
            const paymentElement = this.stripeElements.create('payment');
            await this.$nextTick();
            const mount = this.$refs.paymentElement;
            if (!mount) {
                throw new Error('Payment form unavailable');
            }
            paymentElement.mount(mount);

            return true;
        },
        async payStripe() {
            this.loading = true;
            this.err = '';
            try {
                if (!this.showStripeForm) {
                    this.showStripeForm = true;
                    await this.$nextTick();
                    await this.initStripeForm();
                    this.loading = false;

                    return;
                }
                if (!this.stripeInstance || !this.stripeElements || !this.stripeClientSecret) {
                    await this.initStripeForm();
                }
                const returnBase = window.location.href.split('?')[0];
                const { error } = await this.stripeInstance.confirmPayment({
                    elements: this.stripeElements,
                    clientSecret: this.stripeClientSecret,
                    confirmParams: {
                        return_url: `${returnBase}?payment=success&provider=stripe`,
                    },
                });
                if (error) {
                    this.err = error.message || 'Payment failed';
                }
            } catch (e) {
                this.err = e.message || 'Stripe failed';
            } finally {
                this.loading = false;
            }
        },
    }));

    Alpine.data('flowdeskMessenger', (cfg) => ({
        cfg,
        open: false,
        booted: false,
        loading: false,
        sending: false,
        view: 'list',
        threads: [],
        selfId: '',
        fullPageUrl: '',
        threadTitle: '',
        activeThreadId: null,
        messages: [],
        lastId: 0,
        body: '',
        pollTimer: null,
        pollIntervalMs: 2500,
        pollFailStreak: 0,
        _pollVisHandler: null,

        typeLabel(type) {
            return this.cfg.labels?.[type] || type || '';
        },

        toggle() {
            this.open = !this.open;
            if (this.open && !this.booted) {
                this.bootstrap();
            }
            if (!this.open) {
                this.stopPoll();
            }
        },

        async bootstrap() {
            this.loading = true;
            try {
                const r = await fetch(this.cfg.bootstrapUrl, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await r.json().catch(() => ({}));
                this.threads = Array.isArray(d.threads) ? d.threads : [];
                this.selfId = String(d.self_id || '');
                this.fullPageUrl = d.full_page_url || '';
                this.booted = true;
                if (this.threads.length === 1) {
                    const t = this.threads[0];
                    await this.openThread(t.id, t.label);
                }
            } finally {
                this.loading = false;
            }
        },

        backToList() {
            this.stopPoll();
            this.view = 'list';
            this.activeThreadId = null;
            this.threadTitle = '';
            this.messages = [];
            this.lastId = 0;
            this.body = '';
        },

        messagesFullUrl(id) {
            return `${this.cfg.chatBase}/${id}/messages/full`;
        },

        pollUrl(id) {
            return `${this.cfg.chatBase}/${id}/messages/poll`;
        },

        storeUrl(id) {
            return `${this.cfg.chatBase}/${id}/messages`;
        },

        async openThread(id, label) {
            this.activeThreadId = id;
            this.threadTitle = label || '';
            this.view = 'thread';
            this.loading = true;
            this.messages = [];
            this.lastId = 0;
            try {
                const r = await fetch(this.messagesFullUrl(id), {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await r.json().catch(() => ({}));
                const list = Array.isArray(d.messages) ? d.messages : [];
                list.forEach((m) => this.appendMessage(m));
                list.forEach((m) => {
                    if (m.id > this.lastId) {
                        this.lastId = m.id;
                    }
                });
            } finally {
                this.loading = false;
            }
            this.$nextTick(() => this.scrollStream());
            this.startPoll();
        },

        appendMessage(m) {
            if (this.messages.some((x) => x.id === m.id)) {
                return;
            }
            this.messages.push(m);
        },

        startPoll() {
            this.stopPoll();
            if (!this.activeThreadId) {
                return;
            }
            const id = this.activeThreadId;
            this.pollIntervalMs = 2500;
            this.pollFailStreak = 0;

            this._pollVisHandler = () => {
                if (!document.hidden && this.activeThreadId === id) {
                    void this.runPollTick(id);
                }
            };
            document.addEventListener('visibilitychange', this._pollVisHandler);

            const scheduleNext = () => {
                this.pollTimer = setTimeout(async () => {
                    if (this.activeThreadId !== id) {
                        return;
                    }
                    if (!document.hidden) {
                        await this.runPollTick(id);
                    }
                    scheduleNext();
                }, this.pollIntervalMs);
            };
            scheduleNext();
        },

        async runPollTick(id) {
            try {
                const r = await fetch(`${this.pollUrl(id)}?after=${this.lastId}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (!r.ok) {
                    this.pollFailStreak++;
                    this.pollIntervalMs = Math.min(30000, 2500 * 2 ** Math.min(this.pollFailStreak, 4));
                    return;
                }
                this.pollFailStreak = 0;
                this.pollIntervalMs = 2500;
                const d = await r.json().catch(() => ({}));
                const list = Array.isArray(d.messages) ? d.messages : [];
                for (const m of list) {
                    if (m.id > this.lastId) {
                        this.lastId = m.id;
                        this.appendMessage(m);
                    }
                }
                this.$nextTick(() => this.scrollStream());
            } catch (e) {
                this.pollFailStreak++;
                this.pollIntervalMs = Math.min(30000, 2500 * 2 ** Math.min(this.pollFailStreak, 4));
            }
        },

        stopPoll() {
            if (this._pollVisHandler) {
                document.removeEventListener('visibilitychange', this._pollVisHandler);
                this._pollVisHandler = null;
            }
            if (this.pollTimer) {
                clearTimeout(this.pollTimer);
            }
            this.pollTimer = null;
        },

        scrollStream() {
            const el = this.$refs.stream;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },

        formatTime(iso) {
            if (!iso) {
                return '';
            }
            const dt = new Date(iso);
            if (Number.isNaN(dt.getTime())) {
                return '';
            }
            const pad = (n) => String(n).padStart(2, '0');
            return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
        },

        async send() {
            const text = (this.body || '').trim();
            if (!text || !this.activeThreadId || this.sending) {
                return;
            }
            const id = this.activeThreadId;
            this.sending = true;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const r = await fetch(this.storeUrl(id), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ body: text }),
                    credentials: 'same-origin',
                });
                const d = await r.json().catch(() => ({}));
                if (r.ok) {
                    if (d.message) {
                        this.appendMessage(d.message);
                        if (d.message.id > this.lastId) {
                            this.lastId = d.message.id;
                        }
                    }
                    this.body = '';
                    this.$nextTick(() => this.scrollStream());
                }
            } finally {
                this.sending = false;
            }
        },
    }));

window.flowdeskSetEmailBodyHtml = (bodyId, html, options = {}) => {
    window.dispatchEvent(
        new CustomEvent('flowdesk-email-body-html', {
            detail: {
                bodyId: bodyId || 'body_html',
                html: html != null ? String(html) : '',
                showPreview: Boolean(options.showPreview),
            },
        }),
    );
};

    /** Email marketing / template HTML: insert merge tags, preview body in modal */
    Alpine.data('emailHtmlEditorTools', (cfg) => {
        const bodyId = (cfg && cfg.bodyId) || 'body_html';
        const subjectId = (cfg && cfg.subjectId) || 'subject';
        const bodyDisabled = Boolean(cfg && cfg.bodyDisabled);
        const previewEmailUrl = (cfg && cfg.previewEmailUrl) || null;
        const previewCampaignId = (cfg && cfg.previewCampaignId) || null;

        return {
            bodyId,
            subjectId,
            bodyDisabled,
            previewEmailUrl,
            previewCampaignId,
            previewEmailTo: (cfg && cfg.previewEmailTo) || '',
            companyLogoUrl: (cfg && cfg.companyLogoUrl) || '',
            companyName: (cfg && cfg.companyName) || '',
            toolTextColor: (cfg && cfg.brandPrimary) || '#4f46e5',
            toolBgColor: '#ffffff',
            linkPromptLabel: (cfg && cfg.linkPromptLabel) || 'URL',
            previewEmailBusy: false,
            previewEmailErr: null,
            previewEmailOk: null,
            previewOpen: false,
            viewMode: 'code',
            srcdoc: '',
            /** Set when textarea value is pushed from the preview iframe; skips updating srcdoc so the iframe is not reloaded. */
            ignoreTextareaInput: false,
            previewEditDebounce: null,
            /** Last caret in the code textarea (buttons steal focus before click). */
            savedTextareaCaret: null,
            /** Last caret inside the preview iframe. */
            iframeSavedRange: null,
            expandMergeTagsForPreview(html) {
                let s = String(html || '');
                if (this.companyLogoUrl) {
                    s = s.split('{{company_logo}}').join(this.companyLogoUrl);
                }
                return s;
            },
            collapseMergeTagsFromPreview(html) {
                let s = String(html || '');
                if (this.companyLogoUrl) {
                    s = s.split(this.companyLogoUrl).join('{{company_logo}}');
                }
                return s;
            },
            logoBlockHtml(align) {
                const a = align === 'left' ? 'left' : align === 'right' ? 'right' : 'center';
                return `<div style="text-align:${a};padding:12px 0;margin:8px 0"><img src="{{company_logo}}" alt="{{company_name}}" style="max-height:72px;max-width:220px;height:auto;display:inline-block;border:0" /></div>`;
            },
            applyHtml(html, showPreview = false) {
                const ta = document.getElementById(this.bodyId);
                if (!ta) {
                    return;
                }
                const value = html != null ? String(html) : '';
                ta.value = value;
                try {
                    ta.dispatchEvent(new Event('input', { bubbles: true }));
                } catch (e) { /* ignore */ }
                this.previewNonce = (this.previewNonce || 0) + 1;
                this.srcdoc = this.expandMergeTagsForPreview(value);
                if (showPreview && value !== '') {
                    this.setViewMode('preview');
                }
                this.$nextTick(() => this.writePreviewSrcdoc());
            },
            writePreviewSrcdoc() {
                const iframe = this.$refs?.previewFrame;
                if (iframe && typeof this.srcdoc === 'string') {
                    iframe.srcdoc = this.srcdoc;
                }
            },
            setViewMode(mode) {
                if (this.viewMode === 'preview' && mode === 'code') {
                    this.syncPreviewHtmlFromIframe();
                }
                this.viewMode = mode;
                if (mode === 'preview') {
                    this.iframeSavedRange = null;
                    const ta = document.getElementById(this.bodyId);
                    const raw = ta && ta.value != null ? String(ta.value) : '';
                    this.srcdoc = this.expandMergeTagsForPreview(raw);
                }
            },
            saveTextareaCaret() {
                const ta = document.getElementById(this.bodyId);
                if (!ta || typeof ta.selectionStart !== 'number') {
                    return;
                }
                this.savedTextareaCaret = {
                    start: ta.selectionStart,
                    end: ta.selectionEnd,
                };
            },
            saveIframeSelection(iframe) {
                if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
                    return;
                }
                const sel = iframe.contentWindow.getSelection();
                if (!sel || sel.rangeCount < 1) {
                    return;
                }
                try {
                    this.iframeSavedRange = sel.getRangeAt(0).cloneRange();
                } catch (e) {
                    this.iframeSavedRange = null;
                }
            },
            serializeIframeDocument(doc) {
                if (!doc || !doc.documentElement) {
                    return '';
                }
                const d = doc.doctype;
                const prolog = d ? `<!DOCTYPE ${d.name}>\n` : '<!DOCTYPE html>\n';
                return prolog + doc.documentElement.outerHTML;
            },
            syncPreviewHtmlFromIframe() {
                if (this.viewMode !== 'preview' || this.bodyDisabled) {
                    return;
                }
                const iframe = this.$refs?.previewFrame;
                const win = iframe?.contentWindow;
                if (!win?.document?.documentElement) {
                    return;
                }
                const html = this.collapseMergeTagsFromPreview(
                    this.serializeIframeDocument(win.document),
                );
                if (!html) {
                    return;
                }
                const ta = document.getElementById(this.bodyId);
                if (!ta) {
                    return;
                }
                this.ignoreTextareaInput = true;
                ta.value = html;
            },
            debouncedSyncFromIframe() {
                if (this.previewEditDebounce) {
                    clearTimeout(this.previewEditDebounce);
                }
                this.previewEditDebounce = setTimeout(() => {
                    this.previewEditDebounce = null;
                    this.syncPreviewHtmlFromIframe();
                }, 200);
            },
            execPreviewCommand(command, value) {
                const iframe = this.$refs?.previewFrame;
                const win = iframe?.contentWindow;
                const doc = win?.document;
                if (!win || !doc?.body) {
                    return;
                }
                win.focus();
                try {
                    doc.execCommand(command, false, value ?? null);
                } catch (e) {
                    return;
                }
                this.debouncedSyncFromIframe();
            },
            insertPreviewLink() {
                const url = window.prompt(this.linkPromptLabel, 'https://');
                if (!url || !String(url).trim()) {
                    return;
                }
                this.execPreviewCommand('createLink', String(url).trim());
            },
            insertLogoBlock(align) {
                this.insertHtmlAtCursor(this.logoBlockHtml(align));
            },
            onPreviewFrameLoad(iframe) {
                if (this.viewMode !== 'preview' || this.bodyDisabled) {
                    return;
                }
                if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
                    return;
                }
                const doc = iframe.contentWindow.document;
                if (!doc?.body) {
                    return;
                }
                try {
                    doc.designMode = 'on';
                } catch (e) {
                    return;
                }
                const track = () => this.saveIframeSelection(iframe);
                doc.addEventListener('mouseup', track);
                doc.addEventListener('keyup', track);
                doc.addEventListener('focus', track, true);
                const onEdit = () => {
                    if (this.viewMode !== 'preview' || this.bodyDisabled) {
                        return;
                    }
                    this.debouncedSyncFromIframe();
                };
                doc.addEventListener('input', onEdit);
                doc.addEventListener('paste', onEdit);
            },
            syncPreviewFromTextarea() {
                if (this.viewMode !== 'preview') {
                    return;
                }
                if (this.ignoreTextareaInput) {
                    this.ignoreTextareaInput = false;
                    return;
                }
                const ta = document.getElementById(this.bodyId);
                const raw = ta && ta.value != null ? String(ta.value) : '';
                this.srcdoc = this.expandMergeTagsForPreview(raw);
            },
            openPreview() {
                const ta = document.getElementById(this.bodyId);
                const raw = ta && ta.value != null ? String(ta.value) : '';
                this.srcdoc = this.expandMergeTagsForPreview(raw);
                this.previewOpen = true;
            },
            closePreview() {
                this.previewOpen = false;
            },
            insertHtmlMergeTagInBody(ta, ins) {
                const v = String(ta.value || '');
                const low = v.toLowerCase();
                const bodyEnd = low.lastIndexOf('</body>');
                if (bodyEnd !== -1) {
                    return v.slice(0, bodyEnd) + ins + v.slice(bodyEnd);
                }
                const htmlEnd = low.lastIndexOf('</html>');
                if (htmlEnd !== -1) {
                    return v.slice(0, htmlEnd) + ins + v.slice(htmlEnd);
                }
                return v + ins;
            },
            insertHtmlAtIframeCursor(html) {
                const iframe = this.$refs?.previewFrame;
                const win = iframe?.contentWindow;
                const doc = win?.document;
                if (!doc?.body) {
                    return false;
                }
                const ins = String(html || '');
                const sel = win.getSelection();
                let range = null;
                if (this.iframeSavedRange) {
                    try {
                        range = this.iframeSavedRange.cloneRange();
                    } catch (e) {
                        range = null;
                    }
                }
                if (!range && sel && sel.rangeCount > 0) {
                    range = sel.getRangeAt(0);
                }
                if (!range) {
                    return false;
                }
                try {
                    if (sel) {
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                    const fragment = range.createContextualFragment(
                        this.expandMergeTagsForPreview(ins),
                    );
                    range.deleteContents();
                    range.insertNode(fragment);
                    range.collapse(false);
                    if (sel) {
                        sel.removeAllRanges();
                        sel.addRange(range);
                    }
                    this.iframeSavedRange = range.cloneRange();
                } catch (e) {
                    return false;
                }
                this.syncPreviewHtmlFromIframe();
                return true;
            },
            insertHtmlAtCursor(html) {
                const ta = document.getElementById(this.bodyId);
                if (!ta) {
                    return;
                }
                const ins = String(html || '');
                if (this.viewMode === 'preview') {
                    if (!this.insertHtmlAtIframeCursor(ins)) {
                        const v = this.insertHtmlMergeTagInBody(ta, ins);
                        this.ignoreTextareaInput = true;
                        ta.value = v;
                        this.srcdoc = this.expandMergeTagsForPreview(v);
                    }
                    return;
                }
                const v = String(ta.value || '');
                let start = v.length;
                let end = v.length;
                if (
                    this.savedTextareaCaret
                    && Number.isInteger(this.savedTextareaCaret.start)
                    && Number.isInteger(this.savedTextareaCaret.end)
                ) {
                    start = this.savedTextareaCaret.start;
                    end = this.savedTextareaCaret.end;
                } else if (Number.isInteger(ta.selectionStart)) {
                    start = ta.selectionStart;
                    end = Number.isInteger(ta.selectionEnd) ? ta.selectionEnd : start;
                }
                ta.value = v.slice(0, start) + ins + v.slice(end);
                const pos = start + ins.length;
                this.savedTextareaCaret = { start: pos, end: pos };
                ta.focus();
                if (typeof ta.setSelectionRange === 'function') {
                    ta.setSelectionRange(pos, pos);
                }
            },
            insertAtCursor(tag) {
                const id = this.bodyId;
                const ta = document.getElementById(id);
                if (!ta) {
                    return;
                }
                const ins = String(tag || '');
                if (this.viewMode === 'preview') {
                    if (!this.insertHtmlAtIframeCursor(ins)) {
                        const v = this.insertHtmlMergeTagInBody(ta, ins);
                        this.ignoreTextareaInput = true;
                        ta.value = v;
                        this.srcdoc = this.expandMergeTagsForPreview(v);
                    }
                    return;
                }
                const v = String(ta.value || '');
                let start = v.length;
                let end = v.length;
                if (
                    this.savedTextareaCaret
                    && Number.isInteger(this.savedTextareaCaret.start)
                    && Number.isInteger(this.savedTextareaCaret.end)
                ) {
                    start = this.savedTextareaCaret.start;
                    end = this.savedTextareaCaret.end;
                } else if (Number.isInteger(ta.selectionStart)) {
                    start = ta.selectionStart;
                    end = Number.isInteger(ta.selectionEnd) ? ta.selectionEnd : start;
                }
                ta.value = v.slice(0, start) + ins + v.slice(end);
                const pos = start + ins.length;
                this.savedTextareaCaret = { start: pos, end: pos };
                ta.focus();
                if (typeof ta.setSelectionRange === 'function') {
                    ta.setSelectionRange(pos, pos);
                }
            },
            savedSubjectCaret: null,
            saveSubjectCaret() {
                const ta = document.getElementById(this.subjectId);
                if (!ta || typeof ta.selectionStart !== 'number') {
                    return;
                }
                this.savedSubjectCaret = {
                    start: ta.selectionStart,
                    end: ta.selectionEnd,
                };
            },
            insertInSubject(tag) {
                const ta = document.getElementById(this.subjectId);
                if (!ta) {
                    return;
                }
                const v = String(ta.value || '');
                const ins = String(tag || '');
                let start = v.length;
                let end = v.length;
                if (
                    this.savedSubjectCaret
                    && Number.isInteger(this.savedSubjectCaret.start)
                    && Number.isInteger(this.savedSubjectCaret.end)
                ) {
                    start = this.savedSubjectCaret.start;
                    end = this.savedSubjectCaret.end;
                } else if (Number.isInteger(ta.selectionStart)) {
                    start = ta.selectionStart;
                    end = Number.isInteger(ta.selectionEnd) ? ta.selectionEnd : start;
                }
                ta.value = v.slice(0, start) + ins + v.slice(end);
                const pos = start + ins.length;
                this.savedSubjectCaret = { start: pos, end: pos };
                ta.focus();
                if (typeof ta.setSelectionRange === 'function') {
                    ta.setSelectionRange(pos, pos);
                }
            },
            collectEditorHtml() {
                if (this.viewMode === 'preview') {
                    this.syncPreviewHtmlFromIframe();
                }
                const ta = document.getElementById(this.bodyId);
                return ta && ta.value != null ? String(ta.value) : '';
            },
            async sendPreviewEmail() {
                if (!this.previewEmailUrl || this.bodyDisabled) {
                    return;
                }
                this.previewEmailErr = null;
                this.previewEmailOk = null;
                const to = String(this.previewEmailTo || '').trim();
                if (!to) {
                    this.previewEmailErr = 'Enter a valid email address.';
                    return;
                }
                const bodyHtml = this.collectEditorHtml();
                if (!bodyHtml.trim()) {
                    this.previewEmailErr = 'Message body is empty.';
                    return;
                }
                const subjectEl = document.getElementById(this.subjectId);
                const subject = subjectEl ? String(subjectEl.value || '') : '';
                const token = (document.querySelector('meta[name="csrf-token"]') || {}).content;
                if (!token) {
                    this.previewEmailErr = 'Request failed.';
                    return;
                }
                this.previewEmailBusy = true;
                try {
                    const res = await fetch(this.previewEmailUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            sample_to: to,
                            subject,
                            body_html: bodyHtml,
                            campaign_id: this.previewCampaignId || null,
                        }),
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        this.previewEmailErr = data.message || 'Could not send preview.';
                        return;
                    }
                    this.previewEmailOk = data.message || 'Preview sent.';
                } catch (e) {
                    this.previewEmailErr = e && e.message ? e.message : 'Could not send preview.';
                } finally {
                    this.previewEmailBusy = false;
                }
            },
            init() {
                const el = this.$el;
                if (!(el instanceof HTMLElement)) {
                    return;
                }
                const ta = document.getElementById(this.bodyId);
                if (ta) {
                    const trackBody = () => this.saveTextareaCaret();
                    ta.addEventListener('select', trackBody);
                    ta.addEventListener('keyup', trackBody);
                    ta.addEventListener('mouseup', trackBody);
                    ta.addEventListener('focus', trackBody);
                }
                const subjectTa = document.getElementById(this.subjectId);
                if (subjectTa) {
                    const trackSubject = () => this.saveSubjectCaret();
                    subjectTa.addEventListener('select', trackSubject);
                    subjectTa.addEventListener('keyup', trackSubject);
                    subjectTa.addEventListener('mouseup', trackSubject);
                    subjectTa.addEventListener('focus', trackSubject);
                }
                if (ta && String(ta.value || '').trim() !== '') {
                    this.setViewMode('preview');
                }
                const onBodySet = (event) => {
                    const detail = event && event.detail ? event.detail : {};
                    if (detail.bodyId && detail.bodyId !== this.bodyId) {
                        return;
                    }
                    this.applyHtml(detail.html ?? '', Boolean(detail.showPreview));
                };
                window.addEventListener('flowdesk-email-body-html', onBodySet);
                const form = el.closest('form');
                if (!form) {
                    return;
                }
                form.addEventListener(
                    'submit',
                    () => {
                        this.setViewMode('code');
                    },
                    true,
                );
            },
        };
    });

    /**
     * Audience contacts textarea: "sync clients" button merges the workspace's
     * client emails into the list without duplicating existing lines.
     */
    Alpine.data('flowdeskAudienceContacts', (cfg = {}) => ({
        clientEmails: Array.isArray(cfg.emails) ? cfg.emails : [],
        prospectEmails: Array.isArray(cfg.prospectEmails) ? cfg.prospectEmails : [],
        added: null,
        lastSource: 'clients',

        mergeEmails(list) {
            const el = document.getElementById('contacts_input');
            if (!el) {
                return 0;
            }

            const lines = el.value
                .split(/\r\n|\r|\n/)
                .map((line) => line.trim())
                .filter(Boolean);
            const existing = new Set(lines.map((line) => line.toLowerCase()));

            let count = 0;
            for (const email of list) {
                const normalized = String(email).trim().toLowerCase();
                if (normalized && !existing.has(normalized)) {
                    existing.add(normalized);
                    lines.push(normalized);
                    count++;
                }
            }

            el.value = lines.join('\n');
            el.dispatchEvent(new Event('input', { bubbles: true }));

            return count;
        },

        syncClients() {
            this.lastSource = 'clients';
            this.added = this.mergeEmails(this.clientEmails);
        },

        syncProspects() {
            this.lastSource = 'prospects';
            this.added = this.mergeEmails(this.prospectEmails);
        },

        addedMessage() {
            if (this.added === null) {
                return '';
            }

            const fromProspects = this.lastSource === 'prospects';
            const tpl = this.added > 0
                ? (fromProspects
                    ? (cfg.prospectAddedMessage || ':count contact(s) added from prospects.')
                    : (cfg.addedMessage || ':count contact(s) added from clients.'))
                : (fromProspects
                    ? (cfg.prospectNoneAddedMessage || 'All prospect emails are already in the list.')
                    : (cfg.noneAddedMessage || 'All client emails are already in the list.'));

            return tpl.replace(':count', String(this.added));
        },
    }));

    /**
     * Email template starter: theme list (sortable) + color / border / radius, live HTML preview.
     */
    Alpine.data('emailMarketingStarterModal', (cfg) => {
        const presets = (cfg && cfg.presets) || [];
        const firstId = presets[0]?.id ?? 'indigo';

        return {
            open: false,
            fieldId: (cfg && cfg.fieldId) || 'body_html',
            baseTemplate: (cfg && cfg.baseTemplate) || '',
            presets,
            selectedId: firstId,
            borderW: '1px',
            cardR: '16px',
            custom: {},
            sortable: null,
            get selectedPreset() {
                return this.presets.find((p) => p.id === this.selectedId);
            },
            get mergedTokens() {
                const s = this.selectedPreset;
                if (!s) {
                    return {};
                }
                return {
                    ...s.tokens,
                    '%%CARD_BORDER_W%%': this.borderW,
                    '%%CARD_R%%': this.cardR,
                    ...this.custom,
                };
            },
            get html() {
                let out = this.baseTemplate;
                const m = this.mergedTokens;
                const keys = Object.keys(m).sort((a, b) => b.length - a.length);
                keys.forEach((k) => {
                    const v = m[k];
                    if (v !== null && v !== undefined) {
                        out = out.split(k).join(String(v));
                    }
                });
                return out;
            },
            normalizeHex(v) {
                const s = String(v || '').trim();
                if (/^#[0-9A-Fa-f]{6}$/.test(s)) {
                    return s;
                }
                if (/^[0-9A-Fa-f]{6}$/.test(s)) {
                    return `#${s}`;
                }
                return '';
            },
            colorForRole(role) {
                const m = this.mergedTokens;
                if (role === 'accent') {
                    return m['%%CTA%%'] || '#4f46e5';
                }
                if (role === 'outer') {
                    return m['%%OUTER_BG%%'] || '#f1f5f9';
                }
                if (role === 'card') {
                    return m['%%CARD_BG%%'] || '#ffffff';
                }
                if (role === 'border') {
                    return m['%%CARD_BORDER%%'] || '#e2e8f0';
                }
                if (role === 'heading') {
                    return m['%%H1%%'] || '#0f172a';
                }
                return '#000000';
            },
            setAccent(ev) {
                const h = this.normalizeHex(ev?.target?.value);
                if (!h) {
                    return;
                }
                const noHash = h.replace('#', '');
                this.custom = {
                    ...this.custom,
                    '%%CTA%%': h,
                    '%%CTA_BORDER%%': h,
                    '%%BAR%%': h,
                    '%%LINK%%': h,
                    '%%LOGO1%%': noHash,
                    '%%BAR2%%': h,
                };
            },
            setOuterBg(ev) {
                const h = this.normalizeHex(ev?.target?.value);
                if (h) {
                    this.custom = { ...this.custom, '%%OUTER_BG%%': h };
                }
            },
            setCardBg(ev) {
                const h = this.normalizeHex(ev?.target?.value);
                if (h) {
                    this.custom = { ...this.custom, '%%CARD_BG%%': h };
                }
            },
            setBorderColor(ev) {
                const h = this.normalizeHex(ev?.target?.value);
                if (h) {
                    this.custom = { ...this.custom, '%%CARD_BORDER%%': h, '%%RULE%%': h };
                }
            },
            setHeadingColor(ev) {
                const h = this.normalizeHex(ev?.target?.value);
                if (h) {
                    this.custom = { ...this.custom, '%%H1%%': h };
                }
            },
            selectPreset(id) {
                this.selectedId = id;
                this.custom = {};
                const p = this.presets.find((x) => x.id === id);
                if (p?.tokens) {
                    this.borderW = p.tokens['%%CARD_BORDER_W%%'] || '1px';
                    this.cardR = p.tokens['%%CARD_R%%'] || '16px';
                }
            },
            resetThemeTweaks() {
                this.custom = {};
                this.selectPreset(this.selectedId);
            },
            openModal() {
                this.open = true;
            },
            apply() {
                const html = this.html;
                const el = document.getElementById(this.fieldId);
                const current = (el && el.value ? String(el.value) : '').trim();
                const confirmMsg = this.confirmOverwrite
                    || (el && el.getAttribute('data-confirm-overwrite'))
                    || '';
                if (current && confirmMsg && typeof window.confirm === 'function' && !window.confirm(confirmMsg)) {
                    return;
                }
                if (typeof window.flowdeskSetEmailBodyHtml === 'function') {
                    window.flowdeskSetEmailBodyHtml(this.fieldId || 'body_html', html, { showPreview: true });
                } else if (el) {
                    el.value = html;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                }
                this.open = false;
            },
            init() {
                this.selectPreset(this.selectedId);
                this.$nextTick(() => {
                    const root = this.$refs.presetList;
                    if (!root || !Sortable) {
                        return;
                    }
                    this.sortable = Sortable.create(root, {
                        animation: 180,
                        handle: '[data-preset-handle]',
                        draggable: '[data-preset-item]',
                        ghostClass: 'flowdesk-starter-ghost',
                    });
                });
            },
        };
    });

    /**
     * Project form: “New client” modal + quick create (avoids x-data in HTML: regex with `>` breaks attribute parsing).
     */
    Alpine.data('projectClientQuickAdd', (cfg) => {
        const t = (cfg && cfg.i18n) || {};
        return {
            modalOpen: false,
            busy: false,
            err: null,
            quickUrl: (cfg && cfg.quickUrl) || '',
            selectId: (cfg && cfg.selectId) || 'client_id',
            csrf: (cfg && cfg.csrf) || '',
            quickName: '',
            quickEmail: '',
            quickPhone: '',
            quickSource: '',
            quickAddressLine1: '',
            quickAddressCity: '',
            quickAddressCountry: '',
            resetForm() {
                this.quickName = '';
                this.quickEmail = '';
                this.quickPhone = '';
                this.quickSource = '';
                this.quickAddressLine1 = '';
                this.quickAddressCity = '';
                this.quickAddressCountry = '';
                this.err = null;
            },
            openModal() {
                this.resetForm();
                // Defer open so the triggering click does not immediately dismiss the dialog.
                queueMicrotask(() => {
                    this.modalOpen = true;
                });
            },
            async quickSubmit() {
                const name = (this.quickName || '').trim();
                if (!name) {
                    this.err = t.required || '';
                    return;
                }
                this.busy = true;
                this.err = null;
                const fd = new FormData();
                fd.append('name', name);
                if ((this.quickEmail || '').trim()) {
                    fd.append('email', this.quickEmail.trim());
                }
                if ((this.quickPhone || '').trim()) {
                    fd.append('phone', this.quickPhone.trim());
                }
                if (this.quickSource) {
                    fd.append('source', this.quickSource);
                }
                if ((this.quickAddressLine1 || '').trim()) {
                    fd.append('address_line1', this.quickAddressLine1.trim());
                }
                if ((this.quickAddressCity || '').trim()) {
                    fd.append('address_city', this.quickAddressCity.trim());
                }
                if ((this.quickAddressCountry || '').trim()) {
                    fd.append('address_country', this.quickAddressCountry.trim().toUpperCase());
                }
                fd.append('_token', this.csrf);
                try {
                    const r = await fetch(this.quickUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this.csrf,
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: fd,
                        credentials: 'same-origin',
                    });
                    const text = await r.text();
                    let body = {};
                    try {
                        body = text ? JSON.parse(text) : {};
                    } catch (parseErr) {
                        const re = new RegExp('<[^>]+>', 'g');
                        body = { message: text ? text.replace(re, ' ').trim().slice(0, 200) : '' };
                    }
                    this.busy = false;
                    if (!r.ok) {
                        if (r.status === 419) {
                            this.err = t.pageExpired || '';
                        } else if (r.status === 403) {
                            this.err = body.message || t.notAllowed || '';
                        } else {
                            this.err =
                                body.message ||
                                (body.errors && Object.values(body.errors).flat().join(' ')) ||
                                (t.couldNot || '') + (r.status ? ' (' + r.status + ')' : '');
                        }
                        return;
                    }
                    const sel = document.getElementById(this.selectId);
                    if (sel && body.client) {
                        const opt = document.createElement('option');
                        opt.value = body.client.id;
                        opt.textContent = body.client.name + (body.client.code ? ' (' + body.client.code + ')' : '');
                        opt.selected = true;
                        sel.appendChild(opt);
                        sel.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                    this.modalOpen = false;
                    this.resetForm();
                } catch (e) {
                    this.busy = false;
                    this.err = t.network || '';
                }
            },
        };
    });
});

bootAiDraftLiveFill();
Alpine.start();

const vueRoot = document.getElementById('flowdesk-vue-root');
if (vueRoot) {
    const appName = vueRoot.dataset.appName ?? 'Flowdesk';
    const message = vueRoot.dataset.message ?? '';
    createApp(FlowdeskPulse, { appName, message }).mount(vueRoot);
}

// Color picker ↔ HEX input sync
(() => {
    function normalizeHex(value) {
        if (!value) return '';
        const v = String(value).trim();
        return /^#[0-9A-Fa-f]{6}$/.test(v) ? v : '';
    }

    function setupColorHexSync(root = document) {
        const boundKey = '__flowdeskColorHexBound';
        const bound = root[boundKey] instanceof WeakSet ? root[boundKey] : new WeakSet();
        root[boundKey] = bound;

        root.querySelectorAll('input[type="color"][data-sync-hex]').forEach((colorEl) => {
            if (bound.has(colorEl)) return;
            bound.add(colorEl);

            const selector = colorEl.getAttribute('data-sync-hex');
            const hexEl = selector ? document.querySelector(selector) : null;
            if (!hexEl) return;

            // init picker from hex (if valid)
            colorEl.value = normalizeHex(hexEl.value) || colorEl.value || '#000000';

            colorEl.addEventListener('input', () => {
                hexEl.value = colorEl.value;
                hexEl.dispatchEvent(new Event('input', { bubbles: true }));
                hexEl.dispatchEvent(new Event('change', { bubbles: true }));
            });

            hexEl.addEventListener('input', () => {
                const n = normalizeHex(hexEl.value);
                if (n) colorEl.value = n;
            });
        });
    }

    function boot() {
        setupColorHexSync(document);

        // Re-bind when Alpine renders template content.
        document.addEventListener('alpine:init', () => {
            queueMicrotask(() => setupColorHexSync(document));
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();

/** When a theme template is selected, copy preset hex colors into inputs if “Apply preset colors” is checked. */
function setupAppearancePresetColorSync() {
    const form = document.querySelector('form[action*="/settings/appearance"]');
    if (!form) {
        return;
    }

    const applyPreset = form.querySelector('#apply_preset_colors');
    const primaryHex = form.querySelector('#primary_color');
    const secondaryHex = form.querySelector('#secondary_color');
    const modeSelect = form.querySelector('[data-appearance-mode]');
    if (!primaryHex || !secondaryHex) {
        return;
    }

    const applyModePreview = (mode) => {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
            return;
        }
        if (mode === 'light') {
            document.documentElement.classList.remove('dark');
            return;
        }
        document.documentElement.classList.toggle(
            'dark',
            window.matchMedia('(prefers-color-scheme: dark)').matches,
        );
    };

    function syncModeFromSelectedTemplate() {
        if (!modeSelect) {
            return;
        }
        const radio = form.querySelector('input[name="theme_name"]:checked');
        const label = radio?.closest('label');
        const mode = label?.dataset?.flowdeskPresetMode;
        if (mode !== 'light' && mode !== 'dark') {
            return;
        }
        if (modeSelect.value === mode) {
            applyModePreview(mode);
            return;
        }
        modeSelect.value = mode;
        applyModePreview(mode);
    }

    function syncFromSelectedTemplate() {
        if (applyPreset && !applyPreset.checked) {
            syncModeFromSelectedTemplate();
            return;
        }
        const radio = form.querySelector('input[name="theme_name"]:checked');
        const label = radio?.closest('label');
        const pri = label?.dataset?.flowdeskPresetPrimary;
        const sec = label?.dataset?.flowdeskPresetSecondary;
        if (pri && /^#[0-9A-Fa-f]{6}$/.test(pri)) {
            primaryHex.value = pri;
            primaryHex.dispatchEvent(new Event('input', { bubbles: true }));
            primaryHex.dispatchEvent(new Event('change', { bubbles: true }));
        }
        if (sec && /^#[0-9A-Fa-f]{6}$/.test(sec)) {
            secondaryHex.value = sec;
            secondaryHex.dispatchEvent(new Event('input', { bubbles: true }));
            secondaryHex.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncModeFromSelectedTemplate();
    }

    form.querySelectorAll('input[name="theme_name"]').forEach((el) => {
        el.addEventListener('change', syncFromSelectedTemplate);
    });
    applyPreset?.addEventListener('change', () => {
        if (applyPreset.checked) {
            syncFromSelectedTemplate();
        }
    });
}

/** Auto-select Light/Dark from the current page appearance; live-preview on change. */
function setupAppearanceModeAutoSelect() {
    const select = document.querySelector('[data-appearance-mode]');
    if (!select) {
        return;
    }

    const effectiveMode = () => (document.documentElement.classList.contains('dark') ? 'dark' : 'light');

    const applyPreview = (mode) => {
        if (mode === 'dark') {
            document.documentElement.classList.add('dark');
            return;
        }
        if (mode === 'light') {
            document.documentElement.classList.remove('dark');
            return;
        }
        document.documentElement.classList.toggle('dark', window.matchMedia('(prefers-color-scheme: dark)').matches);
    };

    // Prefer the currently rendered mode when nothing was re-posted from validation.
    if (select.dataset.hasOld !== '1') {
        const current = effectiveMode();
        if (select.value === 'system' || select.value === '') {
            select.value = current;
        } else if (select.value !== current && (select.value === 'light' || select.value === 'dark')) {
            // Keep saved preference, but if the page already differs (e.g. system→dark),
            // still show the effective mode so the control matches what you see.
            select.value = current;
        }
    }

    select.addEventListener('change', () => {
        applyPreview(select.value);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener(
        'DOMContentLoaded',
        () => {
            setupAppearancePresetColorSync();
            setupAppearanceModeAutoSelect();
        },
        { once: true },
    );
} else {
    setupAppearancePresetColorSync();
    setupAppearanceModeAutoSelect();
}

/**
 * Project / money fields: only digits and one decimal separator (`.` or `,`). Strips all letters and symbols.
 * Backend accepts comma or dot (see flowdesk_decimal_to_minor).
 */
function flowdeskSanitizeAmountInputValue(raw) {
    const s = String(raw).replace(/[^\d.,]/g, '');
    const i = s.search(/[.,]/);
    if (i === -1) {
        return s;
    }
    const intPart = s.slice(0, i).replace(/[^\d]/g, '');
    const sep = s[i];
    const onlyDigits = s
        .slice(i + 1)
        .replace(/[.,]/g, '')
        .replace(/[^\d]/g, '');
    if (onlyDigits.length === 0 && /[.,]$/.test(s)) {
        return intPart + sep;
    }
    return intPart + sep + onlyDigits;
}

function setupFlowdeskAmountInputs() {
    document.addEventListener(
        'input',
        (e) => {
            const el = e.target;
            if (!el || el.nodeName !== 'INPUT' || !el.classList || !el.classList.contains('flowdesk-amount')) {
                return;
            }
            const next = flowdeskSanitizeAmountInputValue(el.value);
            if (next !== el.value) {
                el.value = next;
            }
        },
        true,
    );
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupFlowdeskAmountInputs, { once: true });
} else {
    setupFlowdeskAmountInputs();
}


/** Apply a marketing email template into the HTML editor / preview. */
window.flowdeskApplyCampaignTemplate = function flowdeskApplyCampaignTemplate(html, options) {
    options = options || {};
    const bodyId = options.bodyId || 'body_html';
    const ta = document.getElementById(bodyId);
    const current = (ta && ta.value ? String(ta.value) : '').trim();
    const confirmMsg = options.confirmMsg || '';
    if (current && confirmMsg && typeof window.confirm === 'function' && !window.confirm(confirmMsg)) {
        return false;
    }
    const value = html != null ? String(html) : '';
    if (typeof window.flowdeskSetEmailBodyHtml === 'function') {
        window.flowdeskSetEmailBodyHtml(bodyId, value, { showPreview: true });
        return true;
    }
    if (ta) {
        ta.value = value;
        ta.dispatchEvent(new Event('input', { bubbles: true }));
        ta.dispatchEvent(new Event('change', { bubbles: true }));
    }
    return true;
};

function flowdeskBindCampaignTemplatePicker() {
    const dataEl = document.getElementById('cf-campaign-template-data');
    const select = document.getElementById('campaign_template_picker');
    if (!select) {
        return;
    }
    let picker = { models: [], workspace: [] };
    if (dataEl) {
        try {
            picker = JSON.parse(dataEl.textContent || '{}') || picker;
        } catch (e) {
            picker = { models: [], workspace: [] };
        }
    }
    const confirmMsg = select.getAttribute('data-confirm-overwrite') || '';
    const resolveHtml = (key) => {
        if (!key) return '';
        const model = (picker.models || []).find((t) => t.key === key);
        if (model) return model.body_html || '';
        const workspace = (picker.workspace || []).find((t) => t.key === key);
        if (workspace) return workspace.body_html || '';
        return '';
    };
    const applyKey = (key) => {
        if (!key) return;
        const html = resolveHtml(key);
        const hidden = document.getElementById('workspace_template_id');
        if (hidden) {
            hidden.value = key.indexOf('workspace:') === 0 ? key.slice('workspace:'.length) : '';
        }
        if (html) {
            window.flowdeskApplyCampaignTemplate(html, { confirmMsg: confirmMsg });
        }
    };
    select.addEventListener('change', (event) => {
        applyKey(event.target.value);
    });
    document.querySelectorAll('[data-cf-template-key]').forEach((btn) => {
        btn.addEventListener('click', (event) => {
            event.preventDefault();
            const key = btn.getAttribute('data-cf-template-key');
            if (select) select.value = key || '';
            applyKey(key);
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', flowdeskBindCampaignTemplatePicker, { once: true });
} else {
    flowdeskBindCampaignTemplatePicker();
}
