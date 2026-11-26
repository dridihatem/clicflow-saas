/**
 * "Assistant IA" chat panel docked on the right (full height). Conversation is stored server-side
 * (ai_conversations, channel=sidebar); open/closed state + width persist per user in localStorage.
 */
import { aiFormContext, applyAiFormOps } from './ai-draft-live';

const MIN_W = 340;
const MAX_W = 640;

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function api(url, method = 'GET', body = null) {
    const r = await fetch(url, {
        method,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
        body: body ? JSON.stringify(body) : null,
    });
    const data = await r.json().catch(() => ({}));

    return { ok: r.ok, status: r.status, data };
}

export function registerAiChatPanel(Alpine) {
    Alpine.data('aiChatPanel', (cfg = {}) => ({
        open: false,
        width: 400,
        messages: [],
        conversationId: null,
        input: '',
        sending: false,
        loaded: false,
        loading: false,
        error: '',
        onForm: false,
        formKind: '',
        key: `cf.aiChat.${cfg.userId || 'u'}`,
        labels: cfg.labels || {},
        // Voice (own dictation; only the platform admin switch turns it off; the mic only starts on an explicit click).
        voice: cfg.voice || { enabled: false },
        listening: false,
        speaking: false,
        speakOn: false,
        convMode: false,
        autoSend: true,
        voiceError: '',
        _rec: null,
        _recBase: '',
        _recFinal: '',
        _micDownAt: 0,
        _micPush: false,
        _audio: null,
        _fromVoice: false,

        init() {
            try {
                this.speakOn = localStorage.getItem(`${this.key}.speak`) === '1';
                this.autoSend = localStorage.getItem(`${this.key}.autosend`) !== '0';
                this.open = localStorage.getItem(`${this.key}.open`) === '1';
                const w = parseInt(localStorage.getItem(`${this.key}.w`) || '0', 10);
                if (w >= MIN_W && w <= MAX_W) {
                    this.width = w;
                }
            } catch {
                // storage unavailable
            }
            this.applyHtml();
            this.$nextTick(() => this.syncShell());
            document.addEventListener('alpine:initialized', () => this.syncShell(), { once: true });
            window.setTimeout(() => this.syncShell(), 250);
            if (this.open) {
                void this.load();
            }
            window.addEventListener('cf-ai-chat-toggle', () => this.toggle());
            // Document editor slide-overs (quick product / client) close the chat so they never overlap.
            window.addEventListener('cf-ai-chat-close', () => {
                if (this.open) {
                    this.setOpen(false);
                }
            });
            // The chat voice only follows the platform admin switch: when a Nova endpoint is refused,
            // re-check the status and turn the chat mic off only if the admin disabled voice.
            window.addEventListener('cf-nova-voice-disabled', async () => {
                const url = this.voice?.statusUrl;
                let adminOff = !url;
                if (url) {
                    try {
                        const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                        const s = await r.json();
                        adminOff = s && s.admin_enabled === false;
                    } catch (e) {
                        adminOff = false;
                    }
                }
                if (adminOff) {
                    this.stopVoice();
                    this.voice = { enabled: false };
                }
            });
            window.addEventListener('cf-ai-chat-open', (e) => {
                this.setOpen(true);
                if (e.detail?.prompt) {
                    this.input = String(e.detail.prompt);
                }
            });
            window.setTimeout(() => {
                const ctx = aiFormContext(cfg.route);
                this.onForm = !!ctx;
                this.formKind = ctx?.form || '';
            }, 800);
        },

        isDesktop() {
            return window.matchMedia('(min-width: 1024px)').matches;
        },

        setOpen(v) {
            if (!v) {
                this.stopVoice();
            }
            this.open = !!v;
            try {
                localStorage.setItem(`${this.key}.open`, this.open ? '1' : '0');
            } catch {
                // ignore
            }
            this.applyHtml();
            if (this.open) {
                window.dispatchEvent(new CustomEvent('cf-ai-chat-opened'));
                void this.load();
                this.$nextTick(() => {
                    this.scrollBottom();
                    if (this.isDesktop()) {
                        this.$refs.input?.focus({ preventScroll: true });
                    }
                });
            }
        },

        toggle() {
            this.setOpen(!this.open);
        },

        applyHtml() {
            const html = document.documentElement;
            html.classList.toggle('cf-ai-chat-open', this.open);
            html.style.setProperty('--cf-ai-chat-w', `${this.width}px`);
            this.syncShell();
        },

        /** On medium desktops, fold the left menu while the panel is open so forms keep their width (user preference untouched). */
        syncShell() {
            const shellEl = document.querySelector('[x-data^="clicflowShell"]');
            if (!shellEl || !window.Alpine) {
                return;
            }
            let shell = null;
            try {
                shell = window.Alpine.$data(shellEl);
            } catch {
                return;
            }
            if (!shell || !('collapsed' in shell)) {
                return;
            }
            const narrow = this.isDesktop() && window.innerWidth < 1680;
            if (this.open && narrow) {
                if (this._shellPrev === undefined) {
                    this._shellPrev = !!shell.collapsed;
                }
                shell.collapsed = true;
            } else if (this._shellPrev !== undefined) {
                shell.collapsed = this._shellPrev;
                this._shellPrev = undefined;
            }
        },

        async load(force = false) {
            if ((this.loaded && !force) || this.loading) {
                return;
            }
            this.loading = true;
            const { ok, data } = await api(cfg.historyUrl).catch(() => ({ ok: false, data: {} }));
            this.loading = false;
            if (ok) {
                this.conversationId = data.conversation_id || null;
                this.messages = Array.isArray(data.messages) ? data.messages : [];
                this.loaded = true;
                this.$nextTick(() => this.scrollBottom());
            } else {
                this.error = data.message || this.labels.error || 'Error';
            }
        },

        async newConversation() {
            if (this.sending) {
                return;
            }
            const { ok, data } = await api(cfg.newUrl, 'POST', {}).catch(() => ({ ok: false, data: {} }));
            if (ok) {
                this.conversationId = data.conversation_id || null;
                this.messages = [];
                this.error = '';
                this.loaded = true;
                this.$nextTick(() => this.$refs.input?.focus({ preventScroll: true }));
            }
        },

        useSuggestion(text) {
            this.input = text;
            this.$nextTick(() => {
                this.$refs.input?.focus({ preventScroll: true });
                this.autosize();
            });
        },

        onKeydown(e) {
            if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
                e.preventDefault();
                void this.send();
            }
        },

        autosize() {
            const el = this.$refs.input;
            if (!el) {
                return;
            }
            el.style.height = 'auto';
            el.style.height = `${Math.min(el.scrollHeight, 180)}px`;
        },

        scrollBottom() {
            const el = this.$refs.list;
            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        },

        async send() {
            const text = String(this.input || '').trim();
            if (text.length < 2 || this.sending) {
                return;
            }
            const fromVoice = this._fromVoice;
            this._fromVoice = false;
            this.stopSpeaking();
            if (this.listening) {
                this.stopListening(false);
            }
            this.error = '';
            const temp = { id: `tmp-${Date.now()}`, role: 'user', content: text, meta: null, at: new Date().toISOString() };
            this.messages = [...this.messages, temp];
            this.input = '';
            this.$nextTick(() => {
                this.autosize();
                this.scrollBottom();
            });
            this.sending = true;
            const context = aiFormContext(cfg.route);
            const { ok, data, status } = await api(cfg.sendUrl, 'POST', {
                message: text,
                conversation_id: this.conversationId,
                context: context || {},
            }).catch(() => ({ ok: false, data: {}, status: 0 }));
            this.sending = false;
            if (!ok) {
                const msg = status === 429 ? this.labels.throttled : data.message || this.labels.error;
                this.messages = [...this.messages, { id: `err-${Date.now()}`, role: 'assistant', content: msg || 'Error', meta: { type: 'error' }, at: new Date().toISOString() }];
                this.$nextTick(() => this.scrollBottom());

                return;
            }
            this.conversationId = data.conversation_id || this.conversationId;
            this.messages = [...this.messages.filter((m) => m.id !== temp.id), data.user, data.assistant].filter(Boolean);
            this.$nextTick(() => this.scrollBottom());
            if (this.voice.enabled && (this.speakOn || this.convMode) && data.assistant?.content) {
                // Spoken reply; in conversation mode the mic listens again right after.
                void this.speak(String(data.assistant.content)).then(() => {
                    if (this.convMode && this.open && (fromVoice || this.convMode)) {
                        this.startListening();
                    }
                });
            } else if (this.convMode && this.open) {
                this.startListening();
            }

            const action = data.action || null;
            if (action?.type === 'redirect' && action.url) {
                if (!this.isDesktop()) {
                    // Full-screen on mobile: close so the live fill is visible.
                    this.setOpen(false);
                }
                window.setTimeout(() => window.location.assign(action.url), 900);
            } else if (action?.type === 'ops' && Array.isArray(action.ops)) {
                if (!this.isDesktop()) {
                    this.setOpen(false);
                }
                await applyAiFormOps(action.ops, cfg.route);
            }
        },

        // ------------------------------------------------------------------ voice

        recognitionCtor() {
            return window.SpeechRecognition || window.webkitSpeechRecognition || null;
        },

        persistVoice() {
            try {
                localStorage.setItem(`${this.key}.speak`, this.speakOn ? '1' : '0');
                localStorage.setItem(`${this.key}.autosend`, this.autoSend ? '1' : '0');
            } catch {
                // ignore
            }
        },

        toggleSpeak() {
            this.speakOn = !this.speakOn;
            if (!this.speakOn) {
                this.stopSpeaking();
            }
            this.persistVoice();
        },

        toggleConversation() {
            if (this.convMode) {
                this.stopVoice();

                return;
            }
            this.convMode = true;
            this.startListening();
        },

        stopVoice() {
            this.convMode = false;
            this.stopSpeaking();
            if (this.listening) {
                this.stopListening(false);
            }
        },

        micDown(e) {
            if (e && e.button !== undefined && e.button !== 0) {
                return;
            }
            this._micDownAt = Date.now();
            this._micPush = false;
            if (this.listening) {
                return;
            }
            this._micPush = true;
            this.startListening();
        },

        micUp() {
            if (!this._micDownAt) {
                return;
            }
            const held = Date.now() - this._micDownAt;
            this._micDownAt = 0;
            if (this._micPush) {
                // Long press = push-to-talk (stop on release). Short click = toggle (stays on until silence / click).
                if (held >= 450 && this.listening) {
                    this.stopListening(true);
                }

                return;
            }
            if (this.listening) {
                this.convMode = false;
                this.stopListening(true);
            }
        },

        micLeave() {
            if (this._micDownAt && this._micPush && Date.now() - this._micDownAt >= 450 && this.listening) {
                this._micDownAt = 0;
                this.stopListening(true);
            }
        },

        micClick() {
            if (this.listening) {
                this.convMode = false;
                this.stopListening(true);
            } else {
                this.startListening();
            }
        },

        startListening() {
            if (!this.voice.enabled || this.listening) {
                return;
            }
            this.voiceError = '';
            const Ctor = this.recognitionCtor();
            if (!Ctor) {
                this.voiceError = this.labels.voiceUnsupported || 'Unsupported';
                this.convMode = false;

                return;
            }
            this.stopSpeaking();
            let rec;
            try {
                rec = new Ctor();
            } catch {
                this.voiceError = this.labels.voiceUnsupported || 'Unsupported';
                this.convMode = false;

                return;
            }
            rec.lang = this.voice.lang || 'fr-FR';
            rec.interimResults = true;
            rec.continuous = false;
            rec.maxAlternatives = 1;
            this._recBase = String(this.input || '').trim();
            this._recFinal = '';
            this._sendOnEnd = true;
            rec.onresult = (event) => {
                let interim = '';
                let finalText = '';
                for (let i = 0; i < event.results.length; i++) {
                    const r = event.results[i];
                    if (r.isFinal) {
                        finalText += r[0].transcript;
                    } else {
                        interim += r[0].transcript;
                    }
                }
                this._recFinal = finalText.trim();
                const spoken = `${finalText} ${interim}`.replace(/\s+/g, ' ').trim();
                this.input = [this._recBase, spoken].filter(Boolean).join(' ');
                this.$nextTick(() => this.autosize());
            };
            rec.onerror = (event) => {
                const code = event?.error || '';
                if (code === 'aborted') {
                    return;
                }
                this.voiceError = code === 'not-allowed' || code === 'service-not-allowed'
                    ? this.labels.voiceDenied
                    : code === 'no-speech'
                        ? this.labels.voiceNoSpeech
                        : code === 'audio-capture'
                            ? this.labels.voiceNoMic
                            : this.labels.voiceError;
                if (code !== 'no-speech') {
                    this.convMode = false;
                }
            };
            rec.onend = () => {
                const heard = this._recFinal || '';
                this.listening = false;
                this._rec = null;
                document.dispatchEvent(new CustomEvent('flowdesk-voice-idle', { detail: { source: 'ai-chat' } }));
                if (!heard && this.convMode && this.voiceError === this.labels.voiceNoSpeech) {
                    // Nothing said in conversation mode: stop instead of looping on the mic.
                    this.convMode = false;
                }
                if (heard && this._sendOnEnd && this.autoSend && String(this.input || '').trim().length >= 2) {
                    this._fromVoice = true;
                    void this.send();
                }
            };
            // Pause the Nova top-bar wake-word listener while the chat owns the microphone.
            document.dispatchEvent(new CustomEvent('flowdesk-voice-busy', { detail: { source: 'ai-chat' } }));
            try {
                rec.start();
                this._rec = rec;
                this.listening = true;
            } catch {
                document.dispatchEvent(new CustomEvent('flowdesk-voice-idle', { detail: { source: 'ai-chat' } }));
                this.voiceError = this.labels.voiceError;
                this.convMode = false;
            }
        },

        stopListening(sendIfHeard = true) {
            this._sendOnEnd = sendIfHeard;
            try {
                this._rec?.stop();
            } catch {
                // ignore
            }
        },

        cleanSpeech(text) {
            return String(text || '')
                .replace(/```[\s\S]*?```/g, ' ')
                .replace(/[*_`#>|]+/g, ' ')
                .replace(/https?:\/\/\S+/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 1800);
        },

        stopSpeaking() {
            if (this._audio) {
                try {
                    this._audio.pause();
                } catch {
                    // ignore
                }
                this._audio = null;
            }
            try {
                window.speechSynthesis?.cancel();
            } catch {
                // ignore
            }
            this.speaking = false;
            if (this._speakResolve) {
                this._speakResolve();
                this._speakResolve = null;
            }
        },

        platformTtsBroken() {
            try {
                return sessionStorage.getItem('cf.aiChat.ttsBroken') === '1';
            } catch {
                return false;
            }
        },

        async speak(text) {
            const clean = this.cleanSpeech(text);
            if (!clean) {
                return;
            }
            this.stopSpeaking();
            this.speaking = true;
            const done = new Promise((resolve) => {
                this._speakResolve = resolve;
            });
            const finish = () => {
                this.speaking = false;
                if (this._speakResolve) {
                    this._speakResolve();
                    this._speakResolve = null;
                }
            };
            if (this.voice.speakUrl && !this.platformTtsBroken()) {
                try {
                    const r = await fetch(this.voice.speakUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', Accept: 'audio/mpeg, audio/*', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ text: clean, purpose: 'content' }),
                    });
                    if (r.ok && (r.headers.get('Content-Type') || '').startsWith('audio')) {
                        if (!this.speaking) {
                            return done;
                        }
                        const url = URL.createObjectURL(await r.blob());
                        const audio = new Audio(url);
                        this._audio = audio;
                        audio.onended = () => {
                            URL.revokeObjectURL(url);
                            finish();
                        };
                        audio.onerror = () => finish();
                        await audio.play();

                        return done;
                    }
                    if (r.status === 503 || r.status === 404) {
                        sessionStorage.setItem('cf.aiChat.ttsBroken', '1');
                    }
                } catch {
                    // fall back to the browser voice
                }
            }
            const synth = window.speechSynthesis;
            if (!synth || typeof window.SpeechSynthesisUtterance === 'undefined') {
                finish();

                return done;
            }
            const u = new window.SpeechSynthesisUtterance(clean);
            const lang = this.voice.lang || 'fr-FR';
            u.lang = lang;
            const voices = synth.getVoices() || [];
            const prefix = lang.slice(0, 2).toLowerCase();
            u.voice = voices.find((v) => v.lang === lang && /google|natural|premium|enhanced/i.test(v.name))
                || voices.find((v) => v.lang === lang)
                || voices.find((v) => (v.lang || '').toLowerCase().startsWith(prefix))
                || null;
            u.rate = 1.03;
            u.onend = () => finish();
            u.onerror = () => finish();
            synth.speak(u);

            return done;
        },

        fmt(at) {
            if (!at) {
                return '';
            }
            const d = new Date(at);
            if (Number.isNaN(d.getTime())) {
                return '';
            }
            const now = new Date();
            const sameDay = d.toDateString() === now.toDateString();
            const loc = document.documentElement.lang || 'fr';

            return sameDay
                ? d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' })
                : d.toLocaleString(loc, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
        },

        draftExpired(m) {
            return !!m?.meta?.expires_at && new Date(m.meta.expires_at).getTime() < Date.now();
        },

        startResize(e) {
            if (!this.isDesktop()) {
                return;
            }
            e.preventDefault();
            const rtl = document.documentElement.dir === 'rtl';
            const move = (ev) => {
                const w = rtl ? ev.clientX : window.innerWidth - ev.clientX;
                this.width = Math.max(MIN_W, Math.min(MAX_W, Math.round(w)));
                this.applyHtml();
            };
            const up = () => {
                window.removeEventListener('pointermove', move);
                window.removeEventListener('pointerup', up);
                document.documentElement.classList.remove('cf-ai-chat-resizing');
                try {
                    localStorage.setItem(`${this.key}.w`, String(this.width));
                } catch {
                    // ignore
                }
            };
            document.documentElement.classList.add('cf-ai-chat-resizing');
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
        },
    }));
}
