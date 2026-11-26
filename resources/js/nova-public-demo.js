/**
 * Public marketing Nova demo: chat + STT/TTS + Gemini Live/turn.
 */

const LOCALE_SPEECH = {
    en: 'en-US',
    fr: 'fr-FR',
    es: 'es-ES',
    ar: 'ar-SA',
    hi: 'hi-IN',
    id: 'id-ID',
};

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function floatTo16BitPCM(float32Array) {
    const buffer = new ArrayBuffer(float32Array.length * 2);
    const view = new DataView(buffer);
    for (let i = 0; i < float32Array.length; i++) {
        const s = Math.max(-1, Math.min(1, float32Array[i]));
        view.setInt16(i * 2, s < 0 ? s * 0x8000 : s * 0x7fff, true);
    }
    return new Uint8Array(buffer);
}

function downsampleBuffer(buffer, sampleRate, outRate = 16000) {
    if (sampleRate === outRate) {
        return buffer;
    }
    const ratio = sampleRate / outRate;
    const newLength = Math.round(buffer.length / ratio);
    const result = new Float32Array(newLength);
    let offsetResult = 0;
    let offsetBuffer = 0;
    while (offsetResult < result.length) {
        const nextOffsetBuffer = Math.round((offsetResult + 1) * ratio);
        let accum = 0;
        let count = 0;
        for (let i = offsetBuffer; i < nextOffsetBuffer && i < buffer.length; i++) {
            accum += buffer[i];
            count += 1;
        }
        result[offsetResult] = count ? accum / count : 0;
        offsetResult += 1;
        offsetBuffer = nextOffsetBuffer;
    }
    return result;
}

function uint8ToBase64(bytes) {
    let binary = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
    }
    return btoa(binary);
}

function base64ToUint8(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

function pcm16ToWavBlob(pcmBytes, sampleRate = 24000) {
    const header = new ArrayBuffer(44);
    const view = new DataView(header);
    const dataSize = pcmBytes.length;
    const writeString = (offset, string) => {
        for (let i = 0; i < string.length; i++) {
            view.setUint8(offset + i, string.charCodeAt(i));
        }
    };
    writeString(0, 'RIFF');
    view.setUint32(4, 36 + dataSize, true);
    writeString(8, 'WAVE');
    writeString(12, 'fmt ');
    view.setUint32(16, 16, true);
    view.setUint16(20, 1, true);
    view.setUint16(22, 1, true);
    view.setUint32(24, sampleRate, true);
    view.setUint32(28, sampleRate * 2, true);
    view.setUint16(32, 2, true);
    view.setUint16(34, 16, true);
    writeString(36, 'data');
    view.setUint32(40, dataSize, true);
    return new Blob([header, pcmBytes], { type: 'audio/wav' });
}

export function registerNovaPublicDemo(Alpine) {
    Alpine.data('novaPublicDemo', (cfg = {}) => ({
        mode: cfg.mode || 'chat',
        open: Boolean(cfg.open),
        messages: [],
        draft: '',
        conversationId: null,
        loading: false,
        listening: false,
        speaking: false,
        liveActive: false,
        liveSecondsLeft: 180,
        error: '',
        status: cfg.status || {},
        labels: cfg.labels || {},
        urls: cfg.urls || {},
        locale: cfg.locale || 'en',
        locales: cfg.locales || ['en'],
        localeLabels: cfg.localeLabels || {},
        script: cfg.script || '',
        recognition: null,
        _audioEl: null,
        _liveTimer: null,
        _mediaStream: null,
        _audioCtx: null,
        _processor: null,
        _pcmChunks: [],
        _introPlayed: false,
        _presentationAbort: false,

        get chatOk() {
            return Boolean(this.status.chat_available);
        },
        get speakOk() {
            return Boolean(this.status.speak_available);
        },
        get liveOk() {
            return Boolean(this.status.live_available);
        },
        get speechLang() {
            return LOCALE_SPEECH[this.locale] || 'en-US';
        },

        init() {
            if (!this.messages.length && this.labels.welcome) {
                this.messages.push({ role: 'assistant', text: this.labels.welcome });
            }
            this.$watch('mode', (value) => {
                if (value !== 'live') {
                    this.stopLive();
                }
            });
        },

        setMode(mode) {
            this.error = '';
            this.mode = mode;
            if (mode === 'live' && !this.liveActive) {
                // wait for user to press start
            }
        },

        async playPresentation() {
            const text = String(this.script || '').trim();
            if (!text || this.loading || this.speaking) {
                return;
            }
            if (!this.speakOk) {
                this.error = this.labels.speakFailed || 'Voice playback is not configured.';
                return;
            }
            this.error = '';
            this._presentationAbort = false;
            this.loading = true;
            try {
                await this.playSpeak(text);
            } finally {
                this.loading = false;
            }
        },

        stopPresentation() {
            this._presentationAbort = true;
            if (this._audioEl) {
                try {
                    this._audioEl.pause();
                    this._audioEl.currentTime = 0;
                } catch {
                    // ignore
                }
            }
            this.speaking = false;
            this.loading = false;
        },

        async sendChat(text = null) {
            const message = String(text ?? this.draft ?? '').trim();
            if (!message || this.loading) {
                return;
            }
            if (!this.chatOk) {
                this.error = this.labels.unavailable || 'Demo unavailable';
                return;
            }

            this.error = '';
            this.draft = '';
            this.messages.push({ role: 'user', text: message });
            this.loading = true;
            this.scrollMessages();

            try {
                const res = await fetch(this.urls.chat, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        message,
                        conversation_id: this.conversationId,
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || this.labels.requestFailed || 'Request failed');
                }
                this.conversationId = data.conversation_id || this.conversationId;
                const reply = String(data.reply || '').trim();
                this.messages.push({ role: 'assistant', text: reply });
                this.scrollMessages();
                if (this.mode === 'voice' && this.speakOk && reply) {
                    await this.playSpeak(reply);
                }
            } catch (e) {
                this.error = e?.message || this.labels.requestFailed || 'Request failed';
            } finally {
                this.loading = false;
            }
        },

        async playSpeak(text) {
            if (!this.speakOk || !text) {
                return;
            }
            this.speaking = true;
            this._presentationAbort = false;
            try {
                const res = await fetch(this.urls.speak, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ text }),
                });
                if (!res.ok) {
                    const data = await res.json().catch(() => ({}));
                    throw new Error(data.message || this.labels.speakFailed || 'Speak failed');
                }
                if (this._presentationAbort) {
                    return;
                }
                const blob = await res.blob();
                const url = URL.createObjectURL(blob);
                if (this._audioEl) {
                    this._audioEl.pause();
                    URL.revokeObjectURL(this._audioEl.src);
                }
                this._audioEl = new Audio(url);
                await new Promise((resolve, reject) => {
                    this._audioEl.onended = resolve;
                    this._audioEl.onerror = reject;
                    this._audioEl.play().catch(reject);
                });
                URL.revokeObjectURL(url);
            } catch (e) {
                if (!this._presentationAbort) {
                    this.error = e?.message || this.labels.speakFailed || 'Speak failed';
                }
            } finally {
                this.speaking = false;
            }
        },

        toggleListen() {
            if (this.listening) {
                this.stopListen();
                return;
            }
            this.startListen();
        },

        startListen() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SR) {
                this.error = this.labels.micUnsupported || 'Speech recognition not supported';
                return;
            }
            this.error = '';
            this.recognition = new SR();
            this.recognition.lang = this.speechLang;
            this.recognition.interimResults = false;
            this.recognition.maxAlternatives = 1;
            this.recognition.onresult = (event) => {
                const text = event.results?.[0]?.[0]?.transcript || '';
                this.listening = false;
                if (text.trim()) {
                    this.sendChat(text.trim());
                }
            };
            this.recognition.onerror = () => {
                this.listening = false;
            };
            this.recognition.onend = () => {
                this.listening = false;
            };
            this.listening = true;
            this.recognition.start();
        },

        stopListen() {
            try {
                this.recognition?.stop();
            } catch {
                // ignore
            }
            this.listening = false;
        },

        async startLive() {
            if (this.liveActive || this.loading) {
                return;
            }
            if (!this.liveOk) {
                this.error = this.labels.liveUnavailable || 'Live demo unavailable';
                return;
            }
            this.error = '';
            this.loading = true;
            try {
                const res = await fetch(this.urls.liveSession, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || this.labels.liveUnavailable || 'Live unavailable');
                }

                this.liveActive = true;
                this.liveSecondsLeft = Number(data.duration_seconds || 180);
                this.startLiveCountdown();

                this.messages.push({
                    role: 'assistant',
                    text: this.labels.liveStarted || 'Live session started — speak now.',
                });

                // Opening presentation turn: Gemini introduces Nova + ClicFlow in the UI locale.
                if (!this._introPlayed) {
                    this._introPlayed = true;
                    await this.sendPresentationIntro();
                }

                await this.captureAndSendTurn();
            } catch (e) {
                this.error = e?.message || this.labels.liveUnavailable || 'Live unavailable';
                this.stopLive();
            } finally {
                this.loading = false;
            }
        },

        async sendPresentationIntro() {
            try {
                const res = await fetch(this.urls.liveTurn, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        text: 'Please introduce yourself as Nova, the voice AI agent for ClicFlow. Briefly explain how you help teams run clients, invoices, and projects by voice. Keep it under 40 seconds. Speak in the current UI language.',
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    return;
                }
                if (data.text) {
                    this.messages.push({ role: 'assistant', text: data.text });
                    this.scrollMessages();
                }
                if (data.audio_base64) {
                    const rate = /rate=(\d+)/.exec(data.mime || '')?.[1];
                    const sampleRate = rate ? Number(rate) : 24000;
                    const bytes = base64ToUint8(data.audio_base64);
                    const blob = pcm16ToWavBlob(bytes, sampleRate);
                    const url = URL.createObjectURL(blob);
                    this.speaking = true;
                    this._audioEl = new Audio(url);
                    await new Promise((resolve) => {
                        this._audioEl.onended = resolve;
                        this._audioEl.onerror = resolve;
                        this._audioEl.play().catch(resolve);
                    });
                    this.speaking = false;
                    URL.revokeObjectURL(url);
                }
            } catch {
                // intro is best-effort
            }
        },

        startLiveCountdown() {
            if (this._liveTimer) {
                window.clearInterval(this._liveTimer);
            }
            this._liveTimer = window.setInterval(() => {
                this.liveSecondsLeft -= 1;
                if (this.liveSecondsLeft <= 0) {
                    this.stopLive();
                    this.messages.push({
                        role: 'assistant',
                        text: this.labels.liveEnded || 'Demo time limit reached.',
                    });
                }
            }, 1000);
        },

        stopLive() {
            if (this._liveTimer) {
                window.clearInterval(this._liveTimer);
                this._liveTimer = null;
            }
            this.teardownMic();
            this.liveActive = false;
            this.listening = false;
        },

        async captureAndSendTurn() {
            if (!this.liveActive) {
                return;
            }
            this.listening = true;
            this._pcmChunks = [];
            try {
                this._mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const source = this._audioCtx.createMediaStreamSource(this._mediaStream);
                const processor = this._audioCtx.createScriptProcessor(4096, 1, 1);
                this._processor = processor;
                processor.onaudioprocess = (e) => {
                    const input = e.inputBuffer.getChannelData(0);
                    const down = downsampleBuffer(input, this._audioCtx.sampleRate, 16000);
                    this._pcmChunks.push(floatTo16BitPCM(down));
                };
                source.connect(processor);
                processor.connect(this._audioCtx.destination);

                await new Promise((r) => window.setTimeout(r, 3500));
                this.teardownMic();
                this.listening = false;

                const total = this._pcmChunks.reduce((n, c) => n + c.length, 0);
                const merged = new Uint8Array(total);
                let offset = 0;
                for (const chunk of this._pcmChunks) {
                    merged.set(chunk, offset);
                    offset += chunk.length;
                }
                if (merged.length < 1600) {
                    this.error = this.labels.noSpeech || 'No speech captured';
                    return;
                }

                this.loading = true;
                const res = await fetch(this.urls.liveTurn, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ audio_base64: uint8ToBase64(merged) }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(data.message || this.labels.requestFailed || 'Request failed');
                }
                if (data.text) {
                    this.messages.push({ role: 'assistant', text: data.text });
                    this.scrollMessages();
                }
                if (data.audio_base64) {
                    const rate = /rate=(\d+)/.exec(data.mime || '')?.[1];
                    const sampleRate = rate ? Number(rate) : 24000;
                    const bytes = base64ToUint8(data.audio_base64);
                    const blob = pcm16ToWavBlob(bytes, sampleRate);
                    const url = URL.createObjectURL(blob);
                    this.speaking = true;
                    this._audioEl = new Audio(url);
                    await new Promise((resolve) => {
                        this._audioEl.onended = resolve;
                        this._audioEl.onerror = resolve;
                        this._audioEl.play().catch(resolve);
                    });
                    this.speaking = false;
                    URL.revokeObjectURL(url);
                }
                if (this.liveActive) {
                    window.setTimeout(() => this.captureAndSendTurn(), 400);
                }
            } catch (e) {
                this.error = e?.message || this.labels.requestFailed || 'Request failed';
                this.listening = false;
            } finally {
                this.loading = false;
            }
        },

        teardownMic() {
            try {
                this._processor?.disconnect();
            } catch {
                // ignore
            }
            this._processor = null;
            try {
                this._audioCtx?.close();
            } catch {
                // ignore
            }
            this._audioCtx = null;
            this._mediaStream?.getTracks()?.forEach((t) => t.stop());
            this._mediaStream = null;
        },

        scrollMessages() {
            this.$nextTick(() => {
                const el = this.$refs.messages;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            });
        },

        switchLocale(loc) {
            if (loc === this.locale) {
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = this.urls.locale || '/locale';
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = csrfToken();
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'locale';
            input.value = loc;
            form.appendChild(token);
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        },
    }));
}
