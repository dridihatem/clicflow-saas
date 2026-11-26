/**
 * Play Nova voice replies using server TTS (Edge, Gemini, or OpenAI) with browser fallback.
 */

import { flowdeskNormalizeNumbersForSpeech } from './flowdesk-numbers';
import { flowdeskNotify, flowdeskNotifyLabels, flowdeskSanitizeNotifyMessage } from './flowdesk-notify';
import { flowdeskSyncAiCreditsFromHeaders, flowdeskSyncAiCreditsFromJson } from './flowdesk-ai-credits';

let _audioUnlocked = false;
const _speakCache = new Map();
let _activeAudio = null;
let _speakSessionId = 0;
let _activeSpeakText = '';
const NOVA_NAV_SPEECH_KEY = 'flowdesk_nova_nav_speech_v1';
const NOVA_SPEAK_SESSION_CACHE_KEY = 'flowdesk_nova_speak_session_v1';
const NOVA_SPEAK_SESSION_CACHE_LIMIT = 12;
const NOVA_SPEAK_SESSION_ITEM_MAX = 700_000;

function readSpeakSessionCache() {
    if (typeof window === 'undefined' || !window.sessionStorage) {
        return {};
    }

    try {
        const raw = window.sessionStorage.getItem(NOVA_SPEAK_SESSION_CACHE_KEY);
        if (!raw) {
            return {};
        }
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' ? parsed : {};
    } catch {
        return {};
    }
}

function writeSpeakSessionCache(map) {
    if (typeof window === 'undefined' || !window.sessionStorage) {
        return;
    }

    try {
        const entries = Object.entries(map || {})
            .filter(([, value]) => typeof value === 'string' && value.startsWith('data:'))
            .slice(-NOVA_SPEAK_SESSION_CACHE_LIMIT);
        window.sessionStorage.setItem(
            NOVA_SPEAK_SESSION_CACHE_KEY,
            JSON.stringify(Object.fromEntries(entries)),
        );
    } catch {
        // Quota or private mode — ignore.
    }
}

function rememberSpeakSessionCache(cacheKey, dataUrl) {
    if (!cacheKey || typeof dataUrl !== 'string' || !dataUrl.startsWith('data:')) {
        return;
    }
    if (dataUrl.length > NOVA_SPEAK_SESSION_ITEM_MAX) {
        return;
    }

    const map = readSpeakSessionCache();
    map[cacheKey] = dataUrl;
    writeSpeakSessionCache(map);
}

function hydrateSpeakCacheFromSession(cacheKey) {
    if (!cacheKey || _speakCache.has(cacheKey)) {
        return _speakCache.get(cacheKey) || null;
    }

    const dataUrl = readSpeakSessionCache()[cacheKey];
    if (typeof dataUrl === 'string' && dataUrl.startsWith('data:')) {
        _speakCache.set(cacheKey, dataUrl);
        return dataUrl;
    }

    return null;
}

export function flowdeskStopNovaSpeech(synthesis = null) {
    _speakSessionId += 1;
    _activeSpeakText = '';

    if (_activeAudio) {
        try {
            _activeAudio.pause();
            _activeAudio.currentTime = 0;
        } catch {
            // ignore
        }
        _activeAudio = null;
    }

    try {
        synthesis?.cancel?.();
    } catch {
        // ignore
    }

    if (typeof window !== 'undefined' && window.speechSynthesis) {
        try {
            window.speechSynthesis.cancel();
        } catch {
            // ignore
        }
    }

    if (typeof document !== 'undefined') {
        document.dispatchEvent(new CustomEvent('flowdesk-nova-stop'));
    }
}

export function flowdeskGetActiveNovaSpeechText() {
    return String(_activeSpeakText || '').trim();
}

function blobToDataUrl(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(typeof reader.result === 'string' ? reader.result : '');
        reader.onerror = () => reject(reader.error || new Error('read failed'));
        reader.readAsDataURL(blob);
    });
}

/**
 * Save speech so the next page can keep talking after a full navigation.
 */
export async function flowdeskPersistNovaSpeechForNavigation({
    text = '',
    speakUrl = null,
    speechLocale = 'en-US',
    purpose = 'ui',
} = {}) {
    if (typeof window === 'undefined' || !window.sessionStorage) {
        return false;
    }

    const content = flowdeskSanitizeSpeechText(text || _activeSpeakText);
    if (!content) {
        return false;
    }

    let audioDataUrl = null;
    try {
        if (speakUrl) {
            const key = speakCacheKey(speakUrl, content);
            let cachedUrl = _speakCache.get(key) || hydrateSpeakCacheFromSession(key);
            if (cachedUrl) {
                if (String(cachedUrl).startsWith('data:')) {
                    audioDataUrl = cachedUrl;
                } else {
                    const blob = await fetch(cachedUrl).then((res) => res.blob()).catch(() => null);
                    if (blob && blob.size > 0 && blob.size < 900_000) {
                        audioDataUrl = await blobToDataUrl(blob);
                        rememberSpeakSessionCache(key, audioDataUrl);
                    }
                }
            }
            if (!audioDataUrl) {
                const { blob } = await fetchNovaSpeechBlobWithRetry(speakUrl, content, 0, purpose);
                if (blob && blob.size > 0 && blob.size < 900_000) {
                    const objectUrl = URL.createObjectURL(blob);
                    _speakCache.set(key, objectUrl);
                    audioDataUrl = await blobToDataUrl(blob);
                    rememberSpeakSessionCache(key, audioDataUrl);
                }
            }
        }
    } catch {
        audioDataUrl = null;
    }

    try {
        window.sessionStorage.setItem(NOVA_NAV_SPEECH_KEY, JSON.stringify({
            text: content,
            speakUrl: speakUrl || null,
            speechLocale: speechLocale || 'en-US',
            purpose: purpose === 'content' ? 'content' : 'ui',
            audioDataUrl,
            ts: Date.now(),
        }));
        return true;
    } catch {
        try {
            window.sessionStorage.setItem(NOVA_NAV_SPEECH_KEY, JSON.stringify({
                text: content,
                speakUrl: speakUrl || null,
                speechLocale: speechLocale || 'en-US',
                purpose: purpose === 'content' ? 'content' : 'ui',
                audioDataUrl: null,
                ts: Date.now(),
            }));
            return true;
        } catch {
            return false;
        }
    }
}

/**
 * True when a nav handoff speech payload is waiting (does not consume it).
 */
export function flowdeskHasPendingNovaSpeechForNavigation() {
    if (typeof window === 'undefined' || !window.sessionStorage) {
        return false;
    }

    try {
        const raw = window.sessionStorage.getItem(NOVA_NAV_SPEECH_KEY);
        if (!raw) {
            return false;
        }
        const payload = JSON.parse(raw);
        const text = flowdeskSanitizeSpeechText(payload?.text || '');
        if (!text) {
            return false;
        }
        if (payload.ts && Date.now() - Number(payload.ts) > 45_000) {
            return false;
        }

        return true;
    } catch {
        return false;
    }
}

export function flowdeskConsumeNovaSpeechForNavigation() {
    if (typeof window === 'undefined' || !window.sessionStorage) {
        return null;
    }

    try {
        const raw = window.sessionStorage.getItem(NOVA_NAV_SPEECH_KEY);
        window.sessionStorage.removeItem(NOVA_NAV_SPEECH_KEY);
        if (!raw) {
            return null;
        }
        const payload = JSON.parse(raw);
        const text = flowdeskSanitizeSpeechText(payload?.text || '');
        if (!text) {
            return null;
        }
        if (payload.ts && Date.now() - Number(payload.ts) > 45_000) {
            return null;
        }

        return {
            text,
            speakUrl: payload.speakUrl || null,
            speechLocale: payload.speechLocale || 'en-US',
            purpose: payload.purpose === 'content' ? 'content' : 'ui',
            audioDataUrl: typeof payload.audioDataUrl === 'string' ? payload.audioDataUrl : null,
        };
    } catch {
        try {
            window.sessionStorage.removeItem(NOVA_NAV_SPEECH_KEY);
        } catch {
            // ignore
        }
        return null;
    }
}

/**
 * Resume speech saved before a page navigation (uses cached audio when available).
 */
export async function flowdeskResumeNovaSpeechAfterNavigation({
    synthesis = null,
    onStart = null,
    onEnd = null,
    onNotice = null,
    browserFallbackHint = '',
} = {}) {
    const pending = flowdeskConsumeNovaSpeechForNavigation();
    if (!pending) {
        return false;
    }

    if (pending.audioDataUrl) {
        const sessionId = _speakSessionId;
        _activeSpeakText = pending.text;
        try {
            flowdeskUnlockNovaAudio();
            const played = await playBlobAudio(pending.audioDataUrl, onStart, () => {
                _activeSpeakText = '';
                onEnd?.();
            }, sessionId);
            if (played) {
                return true;
            }
        } catch {
            // fall through to normal speak
        }
        _activeSpeakText = '';
    }

    return flowdeskSpeakNovaText({
        text: pending.text,
        speakUrl: pending.speakUrl,
        speechLocale: pending.speechLocale,
        synthesis,
        onStart,
        onEnd,
        onNotice,
        browserFallbackHint,
        purpose: pending.purpose || 'ui',
    });
}

function isSpeakSessionActive(sessionId) {
    return sessionId === _speakSessionId;
}

/** Always play Nova at normal speed (never 1.2× or other boosts). */
const NOVA_SPEAK_PLAYBACK_RATE = 1.0;

function speakCacheKey(speakUrl, text) {
    return `${speakUrl}::${text}`;
}

async function parseSpeakErrorResponse(res) {
    const contentType = res.headers.get('Content-Type') || '';
    const labels = flowdeskNotifyLabels();

    if (contentType.includes('application/json')) {
        const data = await res.json().catch(() => ({}));
        const validationText = Array.isArray(data?.errors?.text)
            ? data.errors.text[0]
            : null;

        return normalizeSpeakError({
            message: data.message || validationText || null,
            fallback: data.fallback || (res.status === 422 ? 'browser' : null),
            code: data.code || (res.status === 422 ? 'validation' : null),
            status: res.status,
        }, labels);
    }

    const text = await res.text().catch(() => '');

    return normalizeSpeakError({
        message: flowdeskSanitizeNotifyMessage(text, '') || null,
        fallback: null,
        code: null,
        status: res.status,
    }, labels);
}

function defaultSpeakErrorMessage(error, labels = {}) {
    if (error.status === 403 || error.code === 'ai_credits_limit') {
        return labels.creditsLimit || 'Not enough AI credits for this action.';
    }

    if (error.status === 419) {
        return labels.sessionExpired || labels.requestFailed || 'Session expired. Please refresh the page.';
    }

    if (error.status === 503 || error.code === 'tts_unconfigured' || error.code === 'tts_failed') {
        return labels.serviceUnavailable || 'Nova voice is temporarily unavailable.';
    }

    return labels.requestFailed || 'Request failed. Please try again.';
}

function isAudioResponseContentType(contentType) {
    const value = String(contentType || '').toLowerCase();

    return value.includes('audio')
        || value.includes('mpeg')
        || value.includes('mp3')
        || value.includes('wav')
        || value.includes('octet-stream');
}

function buildSpeakError(error) {
    return normalizeSpeakError(error, flowdeskNotifyLabels());
}

function speakFallbackError(code, message = '') {
    const labels = flowdeskNotifyLabels();

    return buildSpeakError({
        message: message || labels.serviceUnavailable || 'Nova voice is temporarily unavailable.',
        fallback: 'browser',
        code,
        status: 503,
    });
}

function normalizeSpeakError(error, labels = {}) {
    if (!error) {
        return null;
    }

    const fallback = defaultSpeakErrorMessage(error, labels);
    const message = flowdeskSanitizeNotifyMessage(error.message, '') || fallback;

    if (error.status === 403 || error.code === 'ai_credits_limit') {
        return {
            ...error,
            message,
            fallback: error.fallback || 'browser',
        };
    }

    if (error.status === 503 || error.code === 'tts_unconfigured' || error.code === 'tts_failed') {
        return {
            ...error,
            message,
            fallback: error.fallback || 'browser',
        };
    }

    return {
        ...error,
        message,
        fallback: error.fallback || null,
    };
}

function notifySpeakError(error, browserFallbackHint = '') {
    if (!error?.message && !browserFallbackHint) {
        return;
    }

    const useBrowser = shouldUseBrowserFallback(error);
    const notice = useBrowser && browserFallbackHint
        ? browserFallbackHint
        : (error?.message || browserFallbackHint);

    if (!notice) {
        return;
    }

    flowdeskNotify(notice, { type: useBrowser ? 'warning' : 'error' });
}

async function fetchNovaSpeechBlob(speakUrl, text, purpose = 'content') {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch(speakUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'audio/mpeg, audio/wav, audio/*, application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ text, purpose }),
        credentials: 'same-origin',
    });

    const contentType = res.headers.get('Content-Type') || '';
    if (res.ok && isAudioResponseContentType(contentType)) {
        flowdeskSyncAiCreditsFromHeaders(res.headers);
        const blob = await res.blob();

        return {
            blob: blob.size > 0 ? blob : null,
            error: blob.size > 0 ? null : buildSpeakError({
                message: flowdeskNotifyLabels().serviceUnavailable || 'Nova voice is temporarily unavailable.',
                fallback: 'browser',
                code: 'tts_failed',
                status: 503,
            }),
        };
    }

    const error = await parseSpeakErrorResponse(res);

    return {
        blob: null,
        error: error || speakFallbackError('unknown'),
    };
}

async function fetchNovaSpeechBlobWithRetry(speakUrl, text, retries = 1, purpose = 'content') {
    let lastResult = speakFallbackError('unknown');

    for (let attempt = 0; attempt <= retries; attempt += 1) {
        try {
            const result = await fetchNovaSpeechBlob(speakUrl, text, purpose);
            if (result.blob || result.error) {
                return result;
            }
            lastResult = result.error || speakFallbackError('tts_failed');
        } catch {
            lastResult = speakFallbackError('network_error');
        }

        if (attempt < retries) {
            await new Promise((resolve) => window.setTimeout(resolve, 350));
        }
    }

    return { blob: null, error: lastResult };
}

export async function flowdeskPrefetchNovaSpeech({ text, speakUrl = null }) {
    const content = flowdeskSanitizeSpeechText(text);
    if (!content || !speakUrl) {
        return false;
    }

    const key = speakCacheKey(speakUrl, content);
    if (_speakCache.has(key) || hydrateSpeakCacheFromSession(key)) {
        return true;
    }

    try {
        const { blob } = await fetchNovaSpeechBlobWithRetry(speakUrl, content, 0, 'ui');
        if (!blob) {
            return false;
        }
        const objectUrl = URL.createObjectURL(blob);
        _speakCache.set(key, objectUrl);
        if (blob.size > 0 && blob.size < 900_000) {
            try {
                const dataUrl = await blobToDataUrl(blob);
                rememberSpeakSessionCache(key, dataUrl);
            } catch {
                // ignore persistence failures
            }
        }
        return true;
    } catch {
        return false;
    }
}

function playBlobAudio(blobUrl, onStart, onEnd, sessionId) {
    return new Promise((resolve, reject) => {
        if (_activeAudio) {
            _activeAudio.pause();
            _activeAudio = null;
        }

        if (!isSpeakSessionActive(sessionId)) {
            onEnd?.();
            resolve(false);
            return;
        }

        const audio = new Audio(blobUrl);
        audio.preload = 'auto';
        // Always normal pace — never speed up Nova (1.0×).
        try {
            audio.defaultPlaybackRate = NOVA_SPEAK_PLAYBACK_RATE;
            audio.playbackRate = NOVA_SPEAK_PLAYBACK_RATE;
        } catch {
            // ignore
        }
        _activeAudio = audio;

        onStart?.();

        audio.onended = () => {
            if (_activeAudio === audio) {
                _activeAudio = null;
            }
            if (isSpeakSessionActive(sessionId)) {
                onEnd?.();
            }
            resolve(true);
        };
        audio.onerror = () => {
            if (_activeAudio === audio) {
                _activeAudio = null;
            }
            if (isSpeakSessionActive(sessionId)) {
                onEnd?.();
            }
            reject(new Error('Audio playback failed'));
        };

        const playPromise = audio.play();
        if (playPromise?.catch) {
            playPromise.catch((error) => {
                if (isSpeakSessionActive(sessionId)) {
                    onEnd?.();
                }
                reject(error);
            });
        }
    });
}

/**
 * Strip markdown/HTML so voice output does not read asterisks and other symbols.
 */
export function flowdeskSanitizeSpeechText(text) {
    let s = String(text || '');
    s = s.replace(/<[^>]*>/g, ' ');
    s = s.replace(/```[\s\S]*?```/g, ' ');
    s = s.replace(/`([^`]+)`/g, '$1');
    s = s.replace(/!\[([^\]]*)\]\([^)]+\)/g, '$1');
    s = s.replace(/\[([^\]]+)\]\([^)]+\)/g, '$1');
    s = s.replace(/\*\*\*([^*]+)\*\*\*/g, '$1');
    s = s.replace(/\*\*([^*]+)\*\*/g, '$1');
    s = s.replace(/__([^_]+)__/g, '$1');
    s = s.replace(/\*([^*\n]+)\*/g, '$1');
    s = s.replace(/_([^_\n]+)_/g, '$1');
    s = s.replace(/~~([^~]+)~~/g, '$1');
    s = s.replace(/^#{1,6}\s+/gm, '');
    s = s.replace(/^>\s?/gm, '');
    s = s.replace(/^\s*[-*+•]\s+/gm, '');
    s = s.replace(/^\s*\d+[.)]\s+/gm, '');
    s = s.replace(/^[-*_]{3,}\s*$/gm, ' ');
    s = s.replace(/\.\s+-\s+/g, '. ');
    s = s.replace(/[*_`#~|\\[\]{}]/g, ' ');
    s = s.replace(/\s+/g, ' ');
    s = flowdeskNormalizeNumbersForSpeech(s);

    return s.trim();
}

export function flowdeskUnlockNovaAudio() {
    _audioUnlocked = true;
}

function pickBrowserVoice(synthesis, speechLocale) {
    if (!synthesis) {
        return null;
    }
    const voices = synthesis.getVoices?.() || [];
    const lang = String(speechLocale || 'en-US').toLowerCase();
    const base = lang.split('-')[0];
    const matchesLang = (v) => {
        const voiceLang = String(v.lang || '').toLowerCase();
        return voiceLang === lang
            || voiceLang.startsWith(`${base}-`)
            || voiceLang.startsWith(base);
    };
    // Prefer feminine Nova-like voices when the browser falls back (men's system voices often feel harsh/fast).
    const feminineHint = /(female|féminin|woman|jenny|denise|samantha|zira|aria|sonia|elvira|salma|amélie|amelie|google.*(français|french|us english).*female)/i;
    const localeVoices = voices.filter(matchesLang);
    const pool = localeVoices.length > 0 ? localeVoices : voices;

    return (
        pool.find((v) => feminineHint.test(`${v.name || ''} ${v.voiceURI || ''}`))
        || pool[0]
        || null
    );
}

function waitForSpeechVoices(synthesis, timeoutMs = 4000) {
    return new Promise((resolve) => {
        if (!synthesis) {
            resolve([]);
            return;
        }

        const existing = synthesis.getVoices?.() || [];
        if (existing.length > 0) {
            resolve(existing);
            return;
        }

        let settled = false;
        const finish = () => {
            if (settled) {
                return;
            }
            settled = true;
            synthesis.removeEventListener?.('voiceschanged', finish);
            resolve(synthesis.getVoices?.() || []);
        };

        synthesis.addEventListener?.('voiceschanged', finish);
        window.setTimeout(finish, timeoutMs);
    });
}

/** Server `/assistant/speak` accepts up to this many characters (see AiAssistantController). */
const NOVA_SPEAK_SERVER_MAX = 4000;

function splitSpeechChunks(text, maxLen = 280) {
    const normalized = flowdeskSanitizeSpeechText(text);
    if (!normalized) {
        return [];
    }

    const paragraphs = normalized.split(/\n{2,}/).map((p) => p.trim()).filter(Boolean);
    const chunks = [];

    for (const paragraph of paragraphs) {
        if (paragraph.length <= maxLen) {
            chunks.push(paragraph);
            continue;
        }

        const sentences = paragraph.match(/[^.!?]+[.!?]+|[^.!?]+$/g) || [paragraph];
        let buffer = '';
        for (const sentence of sentences) {
            const part = sentence.trim();
            if (!part) {
                continue;
            }
            if ((`${buffer} ${part}`).trim().length <= maxLen) {
                buffer = `${buffer} ${part}`.trim();
            } else {
                if (buffer) {
                    chunks.push(buffer);
                }
                if (part.length <= maxLen) {
                    buffer = part;
                } else {
                    // Hard-split oversized sentences so server validation never rejects them.
                    for (let i = 0; i < part.length; i += maxLen) {
                        const slice = part.slice(i, i + maxLen);
                        if (i + maxLen < part.length) {
                            chunks.push(slice);
                        } else {
                            buffer = slice;
                        }
                    }
                }
            }
        }
        if (buffer) {
            chunks.push(buffer);
        }
    }

    return chunks;
}

/**
 * Fit speech text into the server max: prefer whole sentences, then hard-slice.
 */
function fitSpeechTextForServer(text, maxLen = NOVA_SPEAK_SERVER_MAX) {
    const normalized = flowdeskSanitizeSpeechText(text);
    if (!normalized || normalized.length <= maxLen) {
        return normalized;
    }

    const chunks = splitSpeechChunks(normalized, maxLen);
    if (chunks.length === 0) {
        return normalized.slice(0, maxLen);
    }

    let fitted = '';
    for (const chunk of chunks) {
        const next = fitted ? `${fitted} ${chunk}` : chunk;
        if (next.length > maxLen) {
            break;
        }
        fitted = next;
    }

    return fitted || chunks[0].slice(0, maxLen);
}

function speakSingleBrowserChunk({ text, speechLocale, synthesis, sessionId, voice = null }) {
    return new Promise((resolve) => {
        if (!synthesis || !isSpeakSessionActive(sessionId)) {
            resolve(false);
            return;
        }

        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = speechLocale || 'en-US';
        if (voice) {
            utterance.voice = voice;
        }
        utterance.rate = NOVA_SPEAK_PLAYBACK_RATE;
        utterance.pitch = 1;
        utterance.onend = () => resolve(isSpeakSessionActive(sessionId));
        utterance.onerror = () => resolve(false);
        synthesis.speak(utterance);
    });
}

async function speakWithBrowserChunked({ text, speechLocale, synthesis, onStart, onEnd, sessionId }) {
    const chunks = splitSpeechChunks(text);
    if (chunks.length === 0 || !synthesis || !isSpeakSessionActive(sessionId)) {
        onEnd?.();
        return false;
    }

    await waitForSpeechVoices(synthesis);
    const voice = pickBrowserVoice(synthesis, speechLocale);
    onStart?.();
    synthesis.cancel();

    for (const chunk of chunks) {
        if (!isSpeakSessionActive(sessionId)) {
            break;
        }
        const ok = await speakSingleBrowserChunk({ text: chunk, speechLocale, synthesis, sessionId, voice });
        if (!ok) {
            onEnd?.();
            return false;
        }
    }

    if (isSpeakSessionActive(sessionId)) {
        onEnd?.();
    }

    return isSpeakSessionActive(sessionId);
}

function speakWithBrowser({ text, speechLocale, synthesis, onStart, onEnd, sessionId }) {
    return speakWithBrowserChunked({ text, speechLocale, synthesis, onStart, onEnd, sessionId });
}

async function fetchBriefingPayload(briefingUrl, { textOnly = false, replay = false } = {}) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const url = new URL(briefingUrl, window.location.origin);
    if (textOnly) {
        url.searchParams.set('text_only', '1');
    }
    if (replay) {
        url.searchParams.set('replay', '1');
    }

    const res = await fetch(url.toString(), {
        method: 'POST',
        headers: {
            Accept: textOnly ? 'application/json' : 'audio/mpeg, audio/wav, audio/*, application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });

    const contentType = res.headers.get('Content-Type') || '';

    if (res.ok && contentType.includes('audio')) {
        flowdeskSyncAiCreditsFromHeaders(res.headers);
        const blob = await res.blob();
        return { blob: blob.size > 0 ? blob : null, text: null, error: null };
    }

    if (contentType.includes('application/json')) {
        const data = await res.json().catch(() => ({}));
        if (res.ok) {
            flowdeskSyncAiCreditsFromJson(data);
            flowdeskSyncAiCreditsFromHeaders(res.headers);
        }
        const text = typeof data.text === 'string' ? data.text.trim() : '';
        const error = ! res.ok
            ? normalizeSpeakError({
                message: data.message || null,
                fallback: data.fallback || null,
                code: data.code || null,
                status: res.status,
            }, flowdeskNotifyLabels())
            : null;

        if (text) {
            return { blob: null, text, error };
        }

        return { blob: null, text: null, error };
    }

    const error = await parseSpeakErrorResponse(res);

    return { blob: null, text: null, error };
}

async function tryPlayBriefingBlob(blob, onStart, onEnd, sessionId) {
    if (!blob || blob.size === 0) {
        return false;
    }

    const url = URL.createObjectURL(blob);
    try {
        await playBlobAudio(url, onStart, onEnd, sessionId);
        return isSpeakSessionActive(sessionId);
    } finally {
        URL.revokeObjectURL(url);
    }
}

function shouldUseBrowserFallback(error) {
    if (!error) {
        return false;
    }

    if (error.fallback === 'browser') {
        return true;
    }

    return error.code === 'ai_credits_limit'
        || error.code === 'tts_unconfigured'
        || error.code === 'tts_failed'
        || error.code === 'playback_failed'
        || error.code === 'network_error'
        || error.code === 'validation'
        || error.status === 403
        || error.status === 422
        || error.status === 503;
}

export async function flowdeskPlayNovaBriefing({
    briefingUrl = null,
    speechLocale = 'en-US',
    synthesis = null,
    onStart = null,
    onEnd = null,
    onNotice = null,
    browserFallbackHint = '',
}) {
    if (!briefingUrl) {
        onEnd?.();
        return false;
    }

    flowdeskUnlockNovaAudio();
    const sessionId = _speakSessionId;
    let briefingText = null;

    try {
        const audioPayload = await fetchBriefingPayload(briefingUrl);
        if (audioPayload.blob) {
            try {
                const played = await tryPlayBriefingBlob(audioPayload.blob, onStart, onEnd, sessionId);
                if (played) {
                    return true;
                }
            } catch {
                // Safari often blocks blob playback after async fetch — fall back to browser speech.
            }
        }

        if (audioPayload.text) {
            briefingText = audioPayload.text;
        }

        if (!briefingText) {
            const replayPayload = await fetchBriefingPayload(briefingUrl, { textOnly: true, replay: true });
            if (replayPayload.text) {
                briefingText = replayPayload.text;
            }
        }

        if (!briefingText && audioPayload.error?.message) {
            onNotice?.(audioPayload.error.message);
            notifySpeakError(audioPayload.error, browserFallbackHint);
            onEnd?.();
            return false;
        }

        if (briefingText && synthesis) {
            if (browserFallbackHint && audioPayload.error?.message) {
                onNotice?.(`${audioPayload.error.message} ${browserFallbackHint}`.trim());
            } else if (audioPayload.blob) {
                onNotice?.(browserFallbackHint || '');
            }

            const spoken = await speakWithBrowserChunked({
                text: briefingText,
                speechLocale,
                synthesis,
                onStart,
                onEnd,
                sessionId,
            });

            return spoken;
        }
    } catch {
        // fall through
    }

    onEnd?.();
    return false;
}

export async function flowdeskSpeakNovaText({
    text,
    speakUrl = null,
    speechLocale = 'en-US',
    synthesis = null,
    onStart = null,
    onEnd = null,
    onNotice = null,
    browserFallbackHint = '',
    purpose = 'content',
}) {
    const fullContent = flowdeskSanitizeSpeechText(text);
    if (!fullContent) {
        onEnd?.();
        return false;
    }

    // Keep request under server max so long assistant replies do not 422 / notify.
    const content = fitSpeechTextForServer(fullContent);
    if (!content) {
        onEnd?.();
        return false;
    }

    const sessionId = _speakSessionId;
    _activeSpeakText = fullContent;
    let serverError = null;

    const wrapStart = (...args) => {
        _activeSpeakText = fullContent;
        onStart?.(...args);
    };
    const wrapEnd = (...args) => {
        if (_activeSpeakText === fullContent) {
            _activeSpeakText = '';
        }
        onEnd?.(...args);
    };

    const tryServerPlayback = async (blobUrl) => {
        flowdeskUnlockNovaAudio();

        try {
            if (await playBlobAudio(blobUrl, wrapStart, wrapEnd, sessionId)) {
                return true;
            }
        } catch {
            // Retry once after unlock — Safari often blocks the first autoplay attempt.
        }

        await new Promise((resolve) => window.setTimeout(resolve, 80));
        flowdeskUnlockNovaAudio();

        try {
            return await playBlobAudio(blobUrl, wrapStart, wrapEnd, sessionId);
        } catch {
            return false;
        }
    };

    if (speakUrl) {
        const cacheKey = speakCacheKey(speakUrl, content);
        const cachedUrl = _speakCache.get(cacheKey);

        try {
            if (cachedUrl) {
                if (await tryServerPlayback(cachedUrl)) {
                    return isSpeakSessionActive(sessionId);
                }

                _speakCache.delete(cacheKey);
            }

            const sessionCached = hydrateSpeakCacheFromSession(cacheKey);
            if (sessionCached) {
                if (await tryServerPlayback(sessionCached)) {
                    return isSpeakSessionActive(sessionId);
                }
                _speakCache.delete(cacheKey);
            }

            const { blob, error } = await fetchNovaSpeechBlobWithRetry(speakUrl, content, 1, purpose);
            if (!isSpeakSessionActive(sessionId)) {
                wrapEnd();
                return false;
            }
            if (blob) {
                const url = URL.createObjectURL(blob);
                _speakCache.set(cacheKey, url);
                if (purpose === 'ui' && blob.size > 0 && blob.size < 900_000) {
                    blobToDataUrl(blob).then((dataUrl) => {
                        rememberSpeakSessionCache(cacheKey, dataUrl);
                    }).catch(() => {});
                }
                if (await tryServerPlayback(url)) {
                    return isSpeakSessionActive(sessionId);
                }

                serverError = speakFallbackError('playback_failed');
            } else if (error) {
                serverError = error;
            }
        } catch {
            serverError = speakFallbackError('network_error');
        }
    }

    if (!isSpeakSessionActive(sessionId)) {
        wrapEnd();
        return false;
    }

    if (serverError && !shouldUseBrowserFallback(serverError)) {
        notifySpeakError(serverError);
        onNotice?.(serverError.message);
        wrapEnd();
        return false;
    }

    if (serverError && shouldUseBrowserFallback(serverError)) {
        notifySpeakError(serverError, browserFallbackHint);
        if (serverError.code !== 'playback_failed') {
            onNotice?.(serverError.message);
        }
    } else if (serverError?.message) {
        notifySpeakError(serverError);
        onNotice?.(serverError.message);
    }

    if (!_audioUnlocked && typeof window !== 'undefined') {
        flowdeskUnlockNovaAudio();
    }

    await speakWithBrowser({ text: fullContent, speechLocale, synthesis, onStart: wrapStart, onEnd: wrapEnd, sessionId });
    return isSpeakSessionActive(sessionId);
}
