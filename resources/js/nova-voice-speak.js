import { flowdeskSpeakNovaText } from './flowdesk-nova-speech';

const WAKE_GREETED_PREFIX = 'flowdesk_nova_wake_greeted:';

let _thinkingAudio = null;

/**
 * Soft looping “thinking” pulse while Nova waits on the model.
 * Returns a stop function; call {@see flowdeskStopNovaThinkingSound} to halt globally.
 */
export function flowdeskStartNovaThinkingSound() {
    flowdeskStopNovaThinkingSound();

    if (typeof window === 'undefined') {
        return () => {};
    }

    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
        return () => {};
    }

    let ctx;
    try {
        ctx = new AudioCtx();
    } catch {
        return () => {};
    }

    const master = ctx.createGain();
    master.gain.value = 0.045;
    master.connect(ctx.destination);

    let stopped = false;
    let beatTimer = null;
    const notes = [392, 494, 440]; // G4 · B4 · A4 soft triad pulse
    let noteIndex = 0;

    const playBeat = () => {
        if (stopped || ctx.state === 'closed') {
            return;
        }

        const now = ctx.currentTime;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.value = notes[noteIndex % notes.length];
        noteIndex += 1;

        gain.gain.setValueAtTime(0.0001, now);
        gain.gain.exponentialRampToValueAtTime(0.9, now + 0.04);
        gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.38);

        osc.connect(gain);
        gain.connect(master);
        osc.start(now);
        osc.stop(now + 0.42);
    };

    const resumeAndPulse = async () => {
        try {
            if (ctx.state === 'suspended') {
                await ctx.resume();
            }
        } catch {
            // ignore autoplay restrictions
        }
        if (stopped) {
            return;
        }
        playBeat();
        beatTimer = window.setInterval(playBeat, 520);
    };

    void resumeAndPulse();

    const stop = () => {
        if (stopped) {
            return;
        }
        stopped = true;
        if (beatTimer) {
            window.clearInterval(beatTimer);
            beatTimer = null;
        }
        try {
            master.gain.setTargetAtTime(0.0001, ctx.currentTime, 0.05);
        } catch {
            // ignore
        }
        window.setTimeout(() => {
            try {
                ctx.close();
            } catch {
                // ignore
            }
        }, 120);
        if (_thinkingAudio?.stop === stop) {
            _thinkingAudio = null;
        }
    };

    _thinkingAudio = { stop, ctx };

    return stop;
}

export function flowdeskStopNovaThinkingSound() {
    if (_thinkingAudio?.stop) {
        try {
            _thinkingAudio.stop();
        } catch {
            // ignore
        }
    }
    _thinkingAudio = null;
}


export function flowdeskNovaWakeReplyStorageKey(userId = '') {
    const id = String(userId || '').trim();

    return `${WAKE_GREETED_PREFIX}${id || 'guest'}`;
}

export function flowdeskNovaHasWakeGreeted(storageKey) {
    if (!storageKey || typeof window === 'undefined') {
        return false;
    }

    try {
        return window.sessionStorage.getItem(storageKey) === '1';
    } catch {
        return false;
    }
}

export function flowdeskNovaMarkWakeGreeted(storageKey) {
    if (!storageKey || typeof window === 'undefined') {
        return;
    }

    try {
        window.sessionStorage.setItem(storageKey, '1');
    } catch {
        // ignore quota / privacy mode
    }
}

/**
 * First wake in the browser session: hello line. Later wakes: listening line.
 */
export function flowdeskNovaResolveWakeReply({ storageKey, hello = '', listening = '', legacy = '' } = {}) {
    const helloText = String(hello || legacy || '').trim();
    const listeningText = String(listening || helloText).trim();
    const firstTime = !flowdeskNovaHasWakeGreeted(storageKey);

    return {
        text: firstTime ? helloText : listeningText,
        firstTime,
    };
}

/**
 * Shared TTS path for Nova top-bar voice replies.
 */
export async function speakNovaVoiceLine(ctx, {
    text,
    status = '',
    muteMs = 6000,
    afterMuteMs = 1800,
    onBeforeSpeak = null,
    onAfterSpeak = null,
    ensureListeningDelayMs = 400,
    purpose = 'ui',
    /** When true, keep the mic open for “stop Nova” / topic change. Default off to avoid TTS echo loops. */
    allowBargeIn = false,
} = {}) {
    const value = String(text || '').trim();
    if (!value || ctx._speaking) {
        return false;
    }

    if (ctx.ttsEnabled === false) {
        if (status) {
            ctx.status = status;
        }
        if (typeof onBeforeSpeak === 'function') {
            onBeforeSpeak();
        }
        if (typeof onAfterSpeak === 'function') {
            onAfterSpeak();
        }

        return true;
    }

    if (ctx._restartTimer) {
        window.clearTimeout(ctx._restartTimer);
        ctx._restartTimer = null;
    }

    ctx._speaking = true;
    ctx.heard = '';
    // Mute SaaS actions while Nova talks. Hard-mute the mic unless barge-in is requested.
    ctx.muteVoiceActions(muteMs);
    ctx.showSpokenTooltip(value);
    if (allowBargeIn) {
        ctx.beginBargeInListening?.() || ctx.ensureListeningDuringSpeech?.();
    } else {
        ctx.beginStopListening();
    }
    if (status) {
        ctx.status = status;
    }

    if (typeof onBeforeSpeak === 'function') {
        onBeforeSpeak();
    }

    try {
        await flowdeskSpeakNovaText({
            text: value,
            speakUrl: ctx.speakUrl,
            speechLocale: ctx.speechLocale,
            synthesis: ctx.synthesis,
            browserFallbackHint: ctx.labels.browserFallback || '',
            purpose,
            onNotice: (message) => ctx.showSpeakNotice(message),
            onStart: () => {
                ctx.synthesis?.cancel();
            },
            onEnd: () => {},
        });
    } finally {
        ctx._speaking = false;
        ctx._listenForStop = false;
        // Replace the long speak-mute with a short echo window only (do not keep muteMs alive).
        ctx._voiceActionsMutedUntil = Date.now() + Math.max(0, afterMuteMs);
        ctx.clearSpokenTooltip();

        if (typeof onAfterSpeak === 'function') {
            onAfterSpeak();
        }

        // Resume listening after the echo-mute window (do not require a click).
        // ensureListeningDelayMs < 0 still resumes after afterMuteMs (legacy “manual” callers).
        const resumeDelay = Math.max(
            afterMuteMs + 80,
            ensureListeningDelayMs >= 0 ? ensureListeningDelayMs : afterMuteMs + 80,
        );
        window.setTimeout(() => {
            if (ctx._speaking || ctx._chatLoading || ctx._briefingLoading || ctx._listeningPaused || ctx._paused) {
                return;
            }
            if (typeof ctx.forceEnsureListening === 'function') {
                ctx.forceEnsureListening();
            } else if (typeof ctx.ensureListening === 'function') {
                ctx.ensureListening();
            }
        }, resumeDelay);
    }

    return true;
}
