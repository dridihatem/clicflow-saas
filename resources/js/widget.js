/**
 * Flowdesk / ClicFlow embeddable form widget (vanilla JS).
 * Mount: <div data-flowdesk-widget data-base-url="…" data-form-id="…" data-token="…" data-lang="fr"></div>
 */
const WIDGET_I18N = {
    en: {
        submit: 'Submit',
        sending: 'Sending…',
        thankYou: 'Thank you — your response was recorded.',
        step: 'Step',
        captcha: 'Security check',
        captchaHint: 'Solve the math question to submit.',
        captchaPlaceholder: 'Your answer',
        loadError: 'Could not load form.',
        submitError: 'Submission failed.',
        validationFailed: 'Validation failed.',
        missingConfig: 'Flowdesk widget: missing data-base-url, data-form-id, or data-token.',
        formFallback: 'Form',
    },
    fr: {
        submit: 'Envoyer',
        sending: 'Envoi…',
        thankYou: 'Merci — votre réponse a été enregistrée.',
        step: 'Étape',
        captcha: 'Vérification de sécurité',
        captchaHint: 'Résolvez le calcul pour envoyer.',
        captchaPlaceholder: 'Votre réponse',
        loadError: 'Impossible de charger le formulaire.',
        submitError: 'Échec de l’envoi.',
        validationFailed: 'Validation échouée.',
        missingConfig: 'Widget Flowdesk : data-base-url, data-form-id ou data-token manquant.',
        formFallback: 'Formulaire',
    },
    es: {
        submit: 'Enviar',
        sending: 'Enviando…',
        thankYou: 'Gracias — tu respuesta se ha registrado.',
        step: 'Paso',
        captcha: 'Comprobación de seguridad',
        captchaHint: 'Resuelve la operación para enviar.',
        captchaPlaceholder: 'Tu respuesta',
        loadError: 'No se pudo cargar el formulario.',
        submitError: 'Error al enviar.',
        validationFailed: 'Validación fallida.',
        missingConfig: 'Widget Flowdesk: faltan data-base-url, data-form-id o data-token.',
        formFallback: 'Formulario',
    },
    ar: {
        submit: 'إرسال',
        sending: 'جارٍ الإرسال…',
        thankYou: 'شكرًا — تم تسجيل ردك.',
        step: 'الخطوة',
        captcha: 'تحقق أمني',
        captchaHint: 'حل المسألة الحسابية للإرسال.',
        captchaPlaceholder: 'إجابتك',
        loadError: 'تعذر تحميل النموذج.',
        submitError: 'فشل الإرسال.',
        validationFailed: 'فشل التحقق.',
        missingConfig: 'ودجت Flowdesk: data-base-url أو data-form-id أو data-token مفقود.',
        formFallback: 'نموذج',
    },
    hi: {
        submit: 'सबमिट करें',
        sending: 'भेजा जा रहा है…',
        thankYou: 'धन्यवाद — आपका जवाब दर्ज हो गया।',
        step: 'चरण',
        captcha: 'सुरक्षा जाँच',
        captchaHint: 'सबमिट करने के लिए गणित प्रश्न हल करें।',
        captchaPlaceholder: 'आपका उत्तर',
        loadError: 'फ़ॉर्म लोड नहीं हो सका।',
        submitError: 'सबमिट विफल।',
        validationFailed: 'सत्यापन विफल।',
        missingConfig: 'Flowdesk विजेट: data-base-url, data-form-id या data-token गायब है।',
        formFallback: 'फ़ॉर्म',
    },
    id: {
        submit: 'Kirim',
        sending: 'Mengirim…',
        thankYou: 'Terima kasih — respons Anda telah dicatat.',
        step: 'Langkah',
        captcha: 'Pemeriksaan keamanan',
        captchaHint: 'Selesaikan soal matematika untuk mengirim.',
        captchaPlaceholder: 'Jawaban Anda',
        loadError: 'Tidak dapat memuat formulir.',
        submitError: 'Pengiriman gagal.',
        validationFailed: 'Validasi gagal.',
        missingConfig: 'Widget Flowdesk: data-base-url, data-form-id, atau data-token hilang.',
        formFallback: 'Formulir',
    },
};

function resolveWidgetLocale(root) {
    const raw = (root.dataset.lang
        || document.documentElement.lang
        || navigator.language
        || 'en').toLowerCase();
    const short = raw.slice(0, 2);

    return WIDGET_I18N[short] ? short : 'en';
}

function t(locale, key) {
    return (WIDGET_I18N[locale] && WIDGET_I18N[locale][key]) || WIDGET_I18N.en[key] || key;
}

function embedPageContextHeaders() {
    try {
        const ctx = {
            page_url: window.location.href,
            path: window.location.pathname + window.location.search,
            referrer: document.referrer || null,
            title: (document.title || '').slice(0, 500) || null,
        };
        const json = JSON.stringify(ctx);
        const b64 = btoa(unescape(encodeURIComponent(json)));
        return { 'X-Flowdesk-Context': b64 };
    } catch (_) {
        return {};
    }
}

function styleFieldControl(el, isDark) {
    el.style.borderRadius = '0.5rem';
    el.style.border = `1px solid ${isDark ? 'rgba(148,163,184,0.35)' : 'rgba(148,163,184,0.8)'}`;
    el.style.padding = '0.5rem 0.65rem';
    el.style.background = isDark ? 'rgba(15,23,42,0.6)' : '#fff';
    el.style.color = isDark ? '#f8fafc' : '#0f172a';
    el.style.width = '100%';
    el.style.boxSizing = 'border-box';
}

async function fetchSchema(baseUrl, formId, token, locale) {
    const url = `${baseUrl.replace(/\/$/, '')}/api/v1/embed/forms/${formId}`;
    const res = await fetch(url, {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Accept-Language': locale,
            ...embedPageContextHeaders(),
        },
    });
    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        throw new Error(err.message || `HTTP ${res.status}`);
    }
    return res.json();
}

async function submitForm(baseUrl, formId, token, payload, locale) {
    const url = `${baseUrl.replace(/\/$/, '')}/api/v1/embed/forms/${formId}/submissions`;
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'Accept-Language': locale,
            ...embedPageContextHeaders(),
        },
        body: JSON.stringify(payload),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const fieldErrors = data.errors && typeof data.errors === 'object'
            ? Object.values(data.errors).flat().filter(Boolean)
            : [];
        const detail = fieldErrors[0] || data.message || `HTTP ${res.status}`;
        const err = new Error(detail);
        err.status = res.status;
        err.payload = data;
        throw err;
    }
    return data;
}

function fieldInput(field, isDark) {
    const label = document.createElement('label');
    label.style.display = 'flex';
    label.style.flexDirection = 'column';
    label.style.gap = '0.35rem';
    label.style.fontSize = '0.875rem';

    const span = document.createElement('span');
    span.textContent = field.name + (field.required ? ' *' : '');
    span.style.color = isDark ? '#cbd5e1' : '#475569';
    label.appendChild(span);

    let input;
    if (field.type === 'textarea') {
        input = document.createElement('textarea');
        input.rows = 4;
    } else if (field.type === 'select' && Array.isArray(field.options) && field.options.length > 0) {
        input = document.createElement('select');
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = '—';
        input.appendChild(empty);
        field.options.forEach((opt) => {
            const o = document.createElement('option');
            o.value = opt;
            o.textContent = opt;
            input.appendChild(o);
        });
    } else {
        input = document.createElement('input');
        input.type = field.type === 'number' ? 'number' : field.type === 'email' ? 'email' : field.type === 'date' ? 'date' : field.type === 'url' ? 'url' : 'text';
    }
    input.name = field.name;
    input.required = !!field.required;
    if (field.placeholder) {
        input.placeholder = field.placeholder;
    }
    styleFieldControl(input, isDark);

    label.appendChild(input);
    return label;
}

function captchaBlock(captcha, isDark, locale) {
    const wrap = document.createElement('div');
    wrap.dataset.flowdeskCaptcha = '1';
    wrap.style.display = 'flex';
    wrap.style.flexDirection = 'column';
    wrap.style.gap = '0.35rem';
    wrap.style.fontSize = '0.875rem';
    wrap.style.padding = '0.75rem';
    wrap.style.borderRadius = '0.75rem';
    wrap.style.border = `1px solid ${isDark ? 'rgba(148,163,184,0.25)' : 'rgba(148,163,184,0.5)'}`;
    wrap.style.background = isDark ? 'rgba(15,23,42,0.45)' : 'rgba(248,250,252,0.9)';

    const title = document.createElement('span');
    title.textContent = t(locale, 'captcha');
    title.style.fontWeight = '600';
    title.style.color = isDark ? '#e2e8f0' : '#334155';
    wrap.appendChild(title);

    const question = document.createElement('p');
    question.dataset.flowdeskCaptchaQuestion = '1';
    question.textContent = captcha.question || '';
    question.style.margin = '0';
    question.style.fontWeight = '700';
    question.style.fontVariantNumeric = 'tabular-nums';
    question.style.color = isDark ? '#f8fafc' : '#0f172a';
    wrap.appendChild(question);

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_captcha_token';
    token.value = captcha.token || '';
    wrap.appendChild(token);

    const answer = document.createElement('input');
    answer.type = 'number';
    answer.name = '_captcha_answer';
    answer.required = true;
    answer.placeholder = t(locale, 'captchaPlaceholder');
    answer.setAttribute('inputmode', 'numeric');
    styleFieldControl(answer, isDark);
    wrap.appendChild(answer);

    const hint = document.createElement('span');
    hint.textContent = t(locale, 'captchaHint');
    hint.style.fontSize = '0.75rem';
    hint.style.color = isDark ? '#94a3b8' : '#64748b';
    wrap.appendChild(hint);

    return wrap;
}

function mount(root) {
    const baseUrl = root.dataset.baseUrl;
    const formId = root.dataset.formId;
    const token = root.dataset.token;
    const locale = resolveWidgetLocale(root);

    if (locale === 'ar') {
        root.setAttribute('dir', 'rtl');
    }

    if (!baseUrl || !formId || !token) {
        root.textContent = t(locale, 'missingConfig');
        return;
    }

    root.innerHTML = '';

    fetchSchema(baseUrl, formId, token, locale)
        .then((schema) => {
            const widget = schema.meta?.widget || {};
            const primary = widget.primary || '#4f46e5';
            const theme = widget.theme || 'light';
            const isDark = theme === 'dark';
            const fields = (schema.fields || []).filter((f) => !['heading', 'paragraph'].includes(f.type));
            const layout = schema.layout || 'simple';
            const captchaEnabled = !!(schema.captcha_enabled
                || schema.captcha?.question
                || schema.meta?.captcha?.enabled);
            const captcha = captchaEnabled && schema.captcha && schema.captcha.question && schema.captcha.token
                ? schema.captcha
                : null;

            const title = document.createElement('h3');
            title.textContent = schema.form?.name || t(locale, 'formFallback');
            title.style.margin = '0 0 0.75rem';
            title.style.fontSize = '1.125rem';
            title.style.fontWeight = '700';
            title.style.color = isDark ? '#f8fafc' : '#0f172a';
            root.appendChild(title);

            const card = document.createElement('div');
            card.style.borderRadius = '1rem';
            card.style.padding = '1.25rem';
            card.style.border = `1px solid ${isDark ? 'rgba(255,255,255,0.12)' : 'rgba(15,23,42,0.12)'}`;
            card.style.background = isDark ? 'rgba(15,23,42,0.92)' : '#ffffff';
            card.style.boxShadow = '0 10px 40px rgba(15,23,42,0.08)';

            const formEl = document.createElement('form');
            formEl.style.display = 'flex';
            formEl.style.flexDirection = 'column';
            formEl.style.gap = '0.75rem';
            formEl.noValidate = false;

            if (layout === 'wizard') {
                const byStep = new Map();
                fields.forEach((f) => {
                    const s = f.step ?? 0;
                    if (!byStep.has(s)) {
                        byStep.set(s, []);
                    }
                    byStep.get(s).push(f);
                });
                const steps = [...byStep.keys()].sort((a, b) => a - b);
                steps.forEach((stepNum) => {
                    const fs = document.createElement('fieldset');
                    fs.style.border = `1px solid ${isDark ? 'rgba(148,163,184,0.25)' : 'rgba(148,163,184,0.5)'}`;
                    fs.style.borderRadius = '0.75rem';
                    fs.style.padding = '0.75rem';
                    const leg = document.createElement('legend');
                    leg.textContent = `${String.fromCharCode(65 + stepNum)} — ${t(locale, 'step')} ${stepNum + 1}`;
                    leg.style.padding = '0 0.25rem';
                    leg.style.color = isDark ? '#94a3b8' : '#64748b';
                    fs.appendChild(leg);
                    const inner = document.createElement('div');
                    inner.style.display = 'flex';
                    inner.style.flexDirection = 'column';
                    inner.style.gap = '0.75rem';
                    byStep.get(stepNum).forEach((f) => inner.appendChild(fieldInput(f, isDark)));
                    fs.appendChild(inner);
                    formEl.appendChild(fs);
                });
            } else {
                fields.forEach((f) => formEl.appendChild(fieldInput(f, isDark)));
            }

            if (captcha) {
                formEl.appendChild(captchaBlock(captcha, isDark, locale));
            }

            const errorBox = document.createElement('p');
            errorBox.style.display = 'none';
            errorBox.style.margin = '0';
            errorBox.style.color = '#b91c1c';
            errorBox.style.fontSize = '0.875rem';
            formEl.appendChild(errorBox);

            const submit = document.createElement('button');
            submit.type = 'submit';
            submit.textContent = t(locale, 'submit');
            submit.style.padding = '0.6rem 1rem';
            submit.style.borderRadius = '0.5rem';
            submit.style.border = 'none';
            submit.style.background = primary;
            submit.style.color = '#fff';
            submit.style.fontWeight = '600';
            submit.style.cursor = 'pointer';

            formEl.appendChild(submit);
            card.appendChild(formEl);
            root.appendChild(card);

            formEl.addEventListener('submit', async (e) => {
                e.preventDefault();
                errorBox.style.display = 'none';
                errorBox.textContent = '';

                const fd = new FormData(formEl);
                const payload = {};
                fields.forEach((f) => {
                    payload[f.name] = fd.get(f.name)?.toString() ?? '';
                });
                if (captcha) {
                    payload._captcha_token = fd.get('_captcha_token')?.toString() ?? '';
                    payload._captcha_answer = fd.get('_captcha_answer')?.toString() ?? '';
                }

                submit.disabled = true;
                submit.textContent = t(locale, 'sending');
                try {
                    await submitForm(baseUrl, formId, token, payload, locale);
                    root.innerHTML = '';
                    const ok = document.createElement('p');
                    ok.textContent = t(locale, 'thankYou');
                    ok.style.color = isDark ? '#bbf7d0' : '#166534';
                    root.appendChild(ok);
                } catch (err) {
                    errorBox.textContent = err.message || t(locale, 'submitError');
                    errorBox.style.display = 'block';
                    submit.disabled = false;
                    submit.textContent = t(locale, 'submit');

                    // Refresh captcha challenge after a failed attempt.
                    if (captcha) {
                        fetchSchema(baseUrl, formId, token, locale)
                            .then((fresh) => {
                                if (!fresh.captcha?.token) {
                                    return;
                                }
                                const block = formEl.querySelector('[data-flowdesk-captcha="1"]');
                                if (!block) {
                                    return;
                                }
                                const tokenInput = block.querySelector('input[name="_captcha_token"]');
                                const answerInput = block.querySelector('input[name="_captcha_answer"]');
                                const questionEl = block.querySelector('[data-flowdesk-captcha-question="1"]');
                                if (tokenInput) {
                                    tokenInput.value = fresh.captcha.token;
                                }
                                if (answerInput) {
                                    answerInput.value = '';
                                }
                                if (questionEl) {
                                    questionEl.textContent = fresh.captcha.question || '';
                                }
                            })
                            .catch(() => {});
                    }
                }
            });
        })
        .catch((e) => {
            root.textContent = e.message || t(locale, 'loadError');
        });
}

document.querySelectorAll('[data-flowdesk-widget]').forEach(mount);
