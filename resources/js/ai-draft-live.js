/**
 * Sidebar "Demander à l'IA": prompt box + live fill of the create form from a server-side draft
 * (window.cfAiDraft). Nothing is submitted: the user reviews and clicks the normal Create button.
 */
const sleep = (ms) => new Promise((r) => window.setTimeout(r, ms));

export function registerAiSidebarPrompt(Alpine) {
    Alpine.data('aiSidebarPrompt', (cfg = {}) => ({
        prompt: '',
        loading: false,
        error: '',
        loadingLabel: cfg.loading || '…',
        async submit() {
            const text = String(this.prompt || '').trim();
            if (text.length < 6 || this.loading) {
                return;
            }
            this.loading = true;
            this.error = '';
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const r = await fetch(cfg.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ prompt: text }),
                });
                const data = await r.json().catch(() => ({}));
                if (!r.ok || !data.redirect) {
                    this.error = data.message || cfg.error || 'Error';
                    this.loading = false;

                    return;
                }
                window.location.assign(data.redirect);
            } catch {
                this.error = cfg.error || 'Error';
                this.loading = false;
            }
        },
    }));
}

const highlight = (el, on) => {
    if (!el) {
        return;
    }
    el.classList.toggle('cf-ai-filling', on);
};

async function typeInto(el, value, speed = 14) {
    if (!el) {
        return;
    }
    const text = String(value ?? '');
    highlight(el, true);
    el.focus?.({ preventScroll: true });
    el.value = '';
    el.dispatchEvent(new Event('input', { bubbles: true }));
    const step = text.length > 120 ? 4 : text.length > 50 ? 2 : 1;
    for (let i = 0; i < text.length; i += step) {
        el.value = text.slice(0, i + step);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        await sleep(speed);
    }
    el.value = text;
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
    highlight(el, false);
}

async function setField(form, name, value) {
    const el = form.querySelector(`[name="${name}"]`);
    if (!el || value === null || value === undefined || value === '') {
        return;
    }
    el.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    if (el.tagName === 'SELECT' && ![...el.options].some((o) => o.value === String(value))) {
        // Chat values are words (« heure », « Formation »): pick the option whose label matches.
        const fold = (s) => String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
        const want = fold(value);
        const opt = [...el.options].find((o) => fold(o.textContent) === want || fold(o.value) === want)
            || [...el.options].find((o) => want.length >= 3 && (fold(o.textContent).includes(want) || want.includes(fold(o.textContent)) && fold(o.textContent).length >= 3));
        if (!opt) {
            return;
        }
        value = opt.value;
    }
    if (el.tagName === 'SELECT' || el.type === 'date' || el.type === 'number' || el.type === 'hidden') {
        highlight(el, true);
        el.value = String(value);
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
        await sleep(220);
        highlight(el, false);

        return;
    }
    await typeInto(el, value);
}

function targetForm(draft) {
    if (draft.form_selector) {
        const f = document.querySelector(draft.form_selector);
        if (f) {
            return f;
        }
    }
    const doc = document.querySelector('[x-data^="quoteForm"], [x-data^="invoiceForm"]');
    if ((draft.entity === 'quote' || draft.entity === 'invoice') && doc) {
        return doc.tagName === 'FORM' ? doc : doc.closest('form') || doc;
    }
    const names = Object.keys(draft.fields || {});
    let best = null;
    let bestScore = 0;
    document.querySelectorAll('main form').forEach((f) => {
        const score = names.filter((n) => f.querySelector(`[name="${n}"]`)).length;
        if (score > bestScore) {
            best = f;
            bestScore = score;
        }
    });

    return best;
}

function docRoot() {
    return document.querySelector('[x-data^="quoteForm"], [x-data^="invoiceForm"]');
}

async function addLineLive(root, d, line) {
    const scale = d.minorScale > 0 ? d.minorScale : 100;
    const minor = Number(line.unit_minor) || 0;
    // Title = product / service name (bold), description = detail below (small).
    const titleText = String(line.description || '');
    const detailText = String(line.detail || '');
    d.items = [
        ...d.items,
        {
            title: '',
            description: '',
            quantity: Number(line.quantity) || 1,
            unit_amount_minor: minor,
            unit_major: minor / scale,
            unit_display: d.formatMajorFromMinor ? d.formatMajorFromMinor(minor) : String(minor / scale),
            product_id: line.product_id || '',
            product_variant_id: line.product_variant_id || null,
            unit: line.unit || '',
            // Product VAT rate when the catalog product has one, else the workspace default.
            vat_rate: line.vat_rate !== null && line.vat_rate !== undefined && line.vat_rate !== ''
                ? (d.rateInput ? d.rateInput(line.vat_rate) : String(line.vat_rate))
                : (d.defaultVatInput ? d.defaultVatInput() : undefined),
            catalog_detail: [line.matched ? '' : '✎', line.unit || '', line.tax_label ? `TVA ${line.tax_label}` : ''].filter(Boolean).join(' · '),
        },
    ];
    d.touchLines?.();
    const ref = d.items[d.items.length - 1];
    await sleep(60);
    if (line.product_id) {
        // Options of a freshly added row render after x-model: re-apply so the select shows the product.
        ref.product_id = '';
        await sleep(0);
        ref.product_id = line.product_id;
        await sleep(30);
        const selects = root.querySelectorAll('select[x-model="line.product_id"]');
        const sel = selects[selects.length - 1];
        if (sel && sel.value !== String(line.product_id)) {
            sel.value = String(line.product_id);
        }
    }
    const rows = root.querySelectorAll('[name^="items["][name$="[title]"]');
    const input = rows[rows.length - 1];
    input?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    highlight(input, true);
    for (const [field, text] of [['title', titleText], ['description', detailText]]) {
        const step = text.length > 120 ? 5 : text.length > 50 ? 3 : 1;
        for (let i = 0; i < text.length; i += step) {
            ref[field] = text.slice(0, i + step);
            await sleep(12);
        }
        ref[field] = text;
    }
    highlight(input, false);
    d.touchLines?.();
    await sleep(160);
}

async function fillDocumentLines(root, draft) {
    if (!window.Alpine || !root) {
        return;
    }
    const d = window.Alpine.$data(root);
    if (!d || !Array.isArray(d.items)) {
        return;
    }
    d.items = [];
    d.touchLines?.();
    for (const line of draft.lines || []) {
        await addLineLive(root, d, line);
    }
}

/** Chat-editable fields of the other forms (by route name). */
const GENERIC_FORMS = {
    client: { routes: ['clients.create', 'clients.edit'], fields: ['name', 'email', 'phone', 'company_name', 'contact_name', 'category', 'status', 'address_line1', 'address_postal_code', 'address_city', 'address_country', 'source', 'notes'] },
    product: { routes: ['commerce.products.create', 'commerce.products.show', 'commerce.products.edit'], fields: ['name', 'kind', 'base_price', 'currency', 'tax_rate', 'tax_rate_2', 'unit', 'category', 'description', 'sku'] },
    project: { routes: ['projects.create', 'projects.edit'], fields: ['title', 'description', 'client_id', 'final_deadline', 'negotiated_price', 'final_price', 'status', 'source'] },
    task: { routes: ['projects.show'], fields: ['title', 'description', 'status', 'priority', 'starts_on', 'ends_on'], selector: 'form[action$="/tasks"]' },
};

function genericFormKind(route) {
    for (const [kind, def] of Object.entries(GENERIC_FORMS)) {
        if (def.routes.includes(route)) {
            return kind;
        }
    }

    return null;
}

function genericForm(kind) {
    const def = GENERIC_FORMS[kind];
    if (!def) {
        return null;
    }
    if (def.selector) {
        return document.querySelector(def.selector);
    }
    let best = null;
    let bestScore = 0;
    document.querySelectorAll('main form, form').forEach((f) => {
        if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') {
            return;
        }
        const score = def.fields.filter((n) => f.querySelector(`[name="${n}"]`)).length;
        if (score > bestScore) {
            best = f;
            bestScore = score;
        }
    });

    return bestScore >= 2 ? best : null;
}

function genericContext(route) {
    const kind = genericFormKind(route || window.cfAiRoute || '');
    const form = kind ? genericForm(kind) : null;
    if (!form) {
        return null;
    }
    const fields = {};
    for (const name of GENERIC_FORMS[kind].fields) {
        const el = form.querySelector(`[name="${name}"]`);
        if (el) {
            fields[name] = String(el.value ?? '').slice(0, 200);
        }
    }

    return { form: kind, fields };
}

/** Snapshot of the open form (quote/invoice lines, or client/product/project/task fields), sent as chat context. */
export function aiFormContext(route = null) {
    const root = docRoot();
    if (!root || !window.Alpine) {
        return genericContext(route);
    }
    let d = null;
    try {
        d = window.Alpine.$data(root);
    } catch {
        return null;
    }
    if (!d || !Array.isArray(d.items)) {
        return null;
    }
    const form = (root.getAttribute('x-data') || '').startsWith('quoteForm') ? 'quote' : 'invoice';
    const scope = root.tagName === 'FORM' ? root : root.closest('form') || root;
    const currency = scope.querySelector('[name="currency"]')?.value || '';

    return {
        form,
        currency,
        discount: { type: d.discountType || '', value: String(d.discountValue || '') },
        lines: d.items.slice(0, 40).map((l) => ({
            description: String([l.title, l.description].filter((v) => String(v || '').trim() !== '').join(' — ')).slice(0, 300),
            quantity: Number(l.quantity) || 1,
            unit_minor: Number(l.unit_amount_minor) || 0,
            product_id: l.product_id ? String(l.product_id) : '',
            vat_rate: d.lineRate ? d.lineRate(l) : null,
        })),
    };
}

function flash(root, name) {
    const el = root.querySelector(`[name="${name}"]`);
    if (!el) {
        return;
    }
    el.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
    highlight(el, true);
    window.setTimeout(() => highlight(el, false), 900);
}

function setLineMinor(d, line, minor) {
    const scale = d.minorScale > 0 ? d.minorScale : 100;
    line.unit_amount_minor = Math.max(0, Math.round(minor));
    line.unit_major = line.unit_amount_minor / scale;
    line.unit_display = d.formatMajorFromMinor ? d.formatMajorFromMinor(line.unit_amount_minor) : String(line.unit_major);
}

/** Apply chat follow-up edits (add / quantity / price / discount / remove / client) to the open form. Nothing is submitted. */
async function checkAssignees(form, assignees = []) {
    for (const a of assignees) {
        const box = form.querySelector(`input[name="assignee_ids[]"][value="${a.id}"]`);
        if (!box) {
            continue;
        }
        box.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
        highlight(box.closest('div') || box, true);
        box.checked = true;
        box.dispatchEvent(new Event('change', { bubbles: true }));
        if (a.role) {
            const sel = form.querySelector(`select[name="assignee_roles[${a.id}]"]`);
            if (sel) {
                sel.value = a.role;
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }
        await sleep(260);
        highlight(box.closest('div') || box, false);
    }
}

/** Field edits on client / product / project / task forms: [{op:'field', name, value}, {op:'assignee', id, role}]. */
async function applyGenericOps(ops, route) {
    const kind = genericFormKind(route || window.cfAiRoute || '');
    const form = kind ? genericForm(kind) : null;
    if (!form) {
        return false;
    }
    const allowed = GENERIC_FORMS[kind].fields;
    for (const op of ops) {
        if (op.op === 'field' && allowed.includes(op.name)) {
            await setField(form, op.name, op.value);
        } else if (op.op === 'assignee' && op.id) {
            await checkAssignees(form, [op]);
        }
    }

    return true;
}

export async function applyAiFormOps(ops = [], route = null) {
    const root = docRoot();
    if (!root || !window.Alpine) {
        return applyGenericOps(ops, route);
    }
    const d = window.Alpine.$data(root);
    if (!d || !Array.isArray(d.items)) {
        return false;
    }
    const scale = d.minorScale > 0 ? d.minorScale : 100;
    const scope = root.tagName === 'FORM' ? root : root.closest('form') || root;
    const order = { client: 0, price: 1, quantity: 2, vat: 3, discount: 4, remove: 5, add: 6 };
    const sorted = [...ops].sort((a, b) => (order[a.op] ?? 9) - (order[b.op] ?? 9) || (a.op === 'remove' ? (b.index ?? 0) - (a.index ?? 0) : 0));
    for (const op of sorted) {
        const line = Number.isInteger(op.index) ? d.items[op.index] : null;
        if (op.op === 'client' && op.client?.id) {
            if ('clientMode' in d) {
                d.clientMode = 'pick';
            }
            await sleep(60);
            await setField(scope, 'client_id', op.client.id);
        } else if (op.op === 'quantity' && line) {
            line.quantity = Math.max(1, Number(op.quantity) || 1);
            d.touchLines?.();
            flash(root, `items[${op.index}][quantity]`);
            await sleep(260);
        } else if (op.op === 'price' && line) {
            setLineMinor(d, line, (Number(op.unit_major) || 0) * scale);
            d.touchLines?.();
            flash(root, `items[${op.index}][quantity]`);
            await sleep(260);
        } else if (op.op === 'discount') {
            const type = op.type === 'amount' ? 'amount' : 'percent';
            const value = Number(op.value ?? op.percent) || 0;
            if (Number.isInteger(op.index) && line && type === 'percent') {
                // Discount on a single line: no per-line field, so lower that line's unit price and note it.
                const pct = Math.max(0, Math.min(100, value));
                setLineMinor(d, line, (Number(line.unit_amount_minor) || 0) * (1 - pct / 100));
                line.catalog_detail = [line.catalog_detail, `Remise ${String(pct).replace('.', ',')} %`].filter(Boolean).join(' · ');
            } else if ('discountType' in d) {
                // Real document discount (before VAT) — fills the "Remise" field of the form.
                d.discountType = value > 0 ? type : '';
                d.discountValue = value > 0 ? String(type === 'percent' ? Math.min(100, value) : value).replace('.', ',') : '';
                await sleep(30);
                const el = scope.querySelector('[name="discount_value"]') || scope.querySelector('[name="discount_type"]');
                el?.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
                highlight(el, true);
                window.setTimeout(() => highlight(el, false), 1000);
            }
            d.touchLines?.();
            await sleep(260);
        } else if (op.op === 'vat') {
            const rate = Math.max(0, Math.min(100, Number(op.rate) || 0));
            const value = d.rateInput ? d.rateInput(rate) : String(rate);
            const targets = line ? [op.index] : d.items.map((_, k) => k);
            targets.forEach((k) => {
                if (d.items[k]) {
                    d.items[k].vat_rate = value;
                }
            });
            d.touchLines?.();
            targets.slice(0, 6).forEach((k) => flash(root, `items[${k}][vat_rate]`));
            await sleep(260);
        } else if (op.op === 'remove' && line) {
            d.items = d.items.filter((_, k) => k !== op.index);
            d.touchLines?.();
            await sleep(200);
        } else if (op.op === 'add' && op.line) {
            await addLineLive(root, d, op.line);
        }
    }
    d.touchLines?.();

    return true;
}

export async function runAiDraftLiveFill() {
    const draft = window.cfAiDraft;
    if (!draft || window.__cfAiDraftRan) {
        return;
    }
    window.__cfAiDraftRan = true;
    const status = document.querySelector('[data-ai-draft-status]');
    await sleep(450);
    const form = targetForm(draft);
    if (!form) {
        return;
    }
    const fields = { ...(draft.fields || {}) };
    const isDoc = draft.entity === 'quote' || draft.entity === 'invoice';

    if (isDoc && fields.currency) {
        await setField(form, 'currency', fields.currency);
        delete fields.currency;
    }
    if (isDoc && draft.client && draft.client.id) {
        const root = form.matches('[x-data]') ? form : form.querySelector('[x-data]');
        try {
            const d = window.Alpine?.$data(root);
            if (d && 'clientMode' in d) {
                d.clientMode = 'pick';
            }
        } catch {
            // ignore
        }
        await sleep(80);
        await setField(form, 'client_id', draft.client.id);
    }
    for (const [name, value] of Object.entries(fields)) {
        await setField(form, name, value);
    }
    if (Array.isArray(draft.assignees) && draft.assignees.length) {
        await checkAssignees(form, draft.assignees);
    }
    if (isDoc) {
        const root = form.matches('[x-data^="quoteForm"], [x-data^="invoiceForm"]') ? form : form.querySelector('[x-data^="quoteForm"], [x-data^="invoiceForm"]');
        await fillDocumentLines(root, draft);
    }
    if (status && draft.labels?.done) {
        status.textContent = draft.labels.done;
    }
}

export function bootAiDraftLiveFill() {
    window.cfAiApplyFormOps = applyAiFormOps;
    const start = () => window.setTimeout(() => void runAiDraftLiveFill(), 50);
    if (window.Alpine && document.readyState !== 'loading') {
        document.addEventListener('alpine:initialized', start, { once: true });
        window.setTimeout(start, 600);
    } else {
        document.addEventListener('alpine:initialized', start, { once: true });
    }
}
