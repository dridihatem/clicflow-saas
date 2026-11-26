/**
 * Web Speech API → textarea dictation for AI prompts (edit before submit).
 */
const FLOWDESK_VOICE_APP_LOCALES = new Set(['en', 'fr', 'es', 'ar', 'hi', 'id']);

export function flowdeskVoiceAppLocale(appLocale) {
    return String(appLocale || 'en').split(/[-_]/)[0].toLowerCase();
}

export function flowdeskIsVoiceLocaleSupported(appLocale) {
    return FLOWDESK_VOICE_APP_LOCALES.has(flowdeskVoiceAppLocale(appLocale));
}

function flowdeskSpeechLocale(appLocale) {
    const base = flowdeskVoiceAppLocale(appLocale);
    if (!FLOWDESK_VOICE_APP_LOCALES.has(base)) {
        return null;
    }

    const map = { en: 'en-US', fr: 'fr-FR', es: 'es-ES', ar: 'ar-SA', hi: 'hi-IN', id: 'id-ID' };

    return map[base] || 'en-US';
}

/** Ordered BCP-47 tags to try when speech recognition rejects the primary locale. */
export function flowdeskSpeechLocaleFallbacks(appLocale) {
    const primary = flowdeskSpeechLocale(appLocale);
    if (!primary) {
        return [];
    }

    const base = flowdeskVoiceAppLocale(appLocale);
    const chains = {
        en: ['en-US', 'en-GB', 'en'],
        fr: ['fr-FR', 'fr-CA', 'fr-BE', 'fr'],
        es: ['es-ES', 'es-MX', 'es-US', 'es'],
        ar: ['ar-SA', 'ar-AE', 'ar-EG', 'ar'],
        hi: ['hi-IN', 'en-IN', 'en-US'],
        id: ['id-ID', 'en-US'],
    };

    return [...new Set([...(chains[base] || []), primary])];
}

/** Microphone constraints tuned for voice commands (noise suppression + echo cancel). */
export function flowdeskVoiceMicConstraints() {
    const constraints = {
        echoCancellation: { ideal: true },
        noiseSuppression: { ideal: true },
        autoGainControl: { ideal: true },
        channelCount: { ideal: 1 },
        sampleRate: { ideal: 48000 },
    };

    try {
        if (typeof window !== 'undefined' && 'voiceIsolation' in (window.MediaTrackSupportedConstraints || {})) {
            constraints.voiceIsolation = { ideal: true };
        }
    } catch {
        // ignore
    }

    return constraints;
}

const _micEnhanceContexts = new WeakMap();

function flowdeskEnhanceVoiceMicStream(stream) {
    if (typeof window === 'undefined' || !window.AudioContext) {
        return stream;
    }

    try {
        const audioContext = new AudioContext();
        const source = audioContext.createMediaStreamSource(stream);
        const highpass = audioContext.createBiquadFilter();
        highpass.type = 'highpass';
        highpass.frequency.value = 140;
        highpass.Q.value = 0.7;

        const destination = audioContext.createMediaStreamDestination();
        source.connect(highpass);
        highpass.connect(destination);

        _micEnhanceContexts.set(destination.stream, {
            audioContext,
            sourceNodes: [source],
            rawStream: stream,
        });

        return destination.stream;
    } catch {
        return stream;
    }
}

function flowdeskVoiceMicStreamLive(stream) {
    return Boolean(stream?.getAudioTracks?.().some((track) => track.readyState === 'live'));
}

/**
 * Open the mic with browser noise suppression before speech recognition starts.
 */
export function flowdeskNovaVoiceAllowed() {
    return !(typeof window !== 'undefined' && window.flowdeskNovaVoice && window.flowdeskNovaVoice.enabled === false);
}

export async function flowdeskAcquireVoiceMicStream(existingStream = null) {
    if (!navigator?.mediaDevices?.getUserMedia || !flowdeskNovaVoiceAllowed()) {
        return null;
    }

    if (flowdeskVoiceMicStreamLive(existingStream)) {
        return existingStream;
    }

    try {
        const rawStream = await navigator.mediaDevices.getUserMedia({
            audio: flowdeskVoiceMicConstraints(),
        });

        return flowdeskEnhanceVoiceMicStream(rawStream);
    } catch {
        try {
            return await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch {
            return null;
        }
    }
}

export function flowdeskReleaseVoiceMicStream(stream) {
    const enhance = stream ? _micEnhanceContexts.get(stream) : null;
    if (enhance) {
        enhance.sourceNodes?.forEach((node) => {
            try {
                node.disconnect();
            } catch {
                // ignore
            }
        });
        flowdeskReleaseVoiceMicStream(enhance.rawStream);
        try {
            enhance.audioContext?.close?.();
        } catch {
            // ignore
        }
        _micEnhanceContexts.delete(stream);

        return;
    }

    stream?.getTracks?.().forEach((track) => {
        try {
            track.stop();
        } catch {
            // ignore
        }
    });
}

/** Bind a noise-filtered mic stream to Chromium speech recognition when supported. */
export function flowdeskAttachMicToSpeechRecognition(recognition, stream) {
    if (!recognition || !flowdeskVoiceMicStreamLive(stream)) {
        return;
    }

    const track = stream.getAudioTracks()[0];
    if (!track) {
        return;
    }

    try {
        if ('mediaStream' in recognition) {
            recognition.mediaStream = stream;
        } else if ('audioTrack' in recognition) {
            recognition.audioTrack = track;
        }
    } catch {
        // Non-Chromium browsers ignore custom mic binding.
    }
}

/**
 * Parse a speech-recognition event into the current utterance only (not the full session).
 *
 * @return {{ heard: string, isFinal: boolean, resultsLength: number }}
 */
export function flowdeskParseSpeechRecognitionEvent(event, committedIndex = 0) {
    const results = event?.results;
    if (!results || results.length === 0) {
        return { heard: '', isFinal: false, resultsLength: 0, alternatives: [] };
    }

    const start = Math.max(0, committedIndex);
    let finalText = '';

    for (let i = start; i < results.length; i++) {
        const part = results[i][0]?.transcript || '';
        if (results[i].isFinal) {
            finalText += part;
        }
    }

    const lastIndex = results.length - 1;
    let interim = '';
    const alternatives = [];
    if (lastIndex >= start) {
        const last = results[lastIndex];
        if (!last.isFinal) {
            interim = last[0]?.transcript || '';
        }
        const altCount = Math.min(last.length || 0, 5);
        for (let a = 0; a < altCount; a += 1) {
            const t = String(last[a]?.transcript || '').trim();
            if (t && !alternatives.includes(t)) {
                alternatives.push(t);
            }
        }
    }

    const heard = (finalText || interim).trim();
    if (heard && !alternatives.includes(heard)) {
        alternatives.unshift(heard);
    }

    return {
        heard,
        isFinal: Boolean(finalText.trim()),
        resultsLength: results.length,
        alternatives,
    };
}

export function flowdeskConfigureSpeechRecognition(recognition, { processLocally = false } = {}) {
    if (!recognition) {
        return;
    }

    recognition.continuous = true;
    recognition.interimResults = true;
    // More ASR hypotheses helps when Chrome mishears plurals / close words.
    recognition.maxAlternatives = 3;

    try {
        if ('processLocally' in recognition) {
            recognition.processLocally = Boolean(processLocally);
        }
    } catch {
        // Chrome-only; ignore elsewhere.
    }
}

function flowdeskApplySpeechLocaleFallback(recognition, appLocale, currentLocale, onApplied) {
    const fallbacks = flowdeskSpeechLocaleFallbacks(appLocale);
    const index = fallbacks.indexOf(currentLocale);
    const next = index >= 0 ? fallbacks[index + 1] : fallbacks[1];
    if (!next || !recognition) {
        return false;
    }

    recognition.lang = next;
    if (typeof onApplied === 'function') {
        onApplied(next);
    }

    return true;
}

export function flowdeskNormalizeVoicePhrase(text) {
    let s = String(text || '').toLowerCase().trim();
    const hasArabic = /\p{Script=Arabic}/u.test(s);

    if (hasArabic) {
        s = s.replace(/[\u064B-\u065F\u0670\u06D6-\u06ED]/g, '');
        s = flowdeskArabicLetterFold(s);
    } else {
        s = s.normalize('NFD').replace(/\p{M}/gu, '');
    }

    return s
        .replace(/[^\p{L}\p{N}\s]/gu, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

/** Fold common Arabic letter variants for wake-word matching. */
export function flowdeskArabicLetterFold(text) {
    return String(text || '')
        .replace(/\u0640/g, '')
        .replace(/[أإآٱ]/g, 'ا')
        .replace(/ى/g, 'ي')
        .replace(/ة/g, 'ه')
        .replace(/ؤ/g, 'و')
        .replace(/ئ/g, 'ي')
        .replace(/ڤ/g, 'ف')
        .replace(/ڥ/g, 'ف')
        .replace(/پ/g, 'ب');
}

function flowdeskSoundsLikeNovaWord(word) {
    const w = flowdeskNormalizeVoicePhrase(word);
    if (!w || w.length < 3) {
        return false;
    }

    if (/^nova$/i.test(w)) {
        return true;
    }

    if (!/\p{Script=Arabic}/u.test(w)) {
        return false;
    }

    return /^ن[او][وف][اه]?$/u.test(w) || /^نوف/u.test(w) || w === 'ناوا';
}

/** True when transcript contains a Nova wake phrase (locale + phonetic variants). */
export function flowdeskMatchesWakePhrase(text, wakePhrases) {
    const normalized = flowdeskNormalizeVoicePhrase(text);
    if (!normalized) {
        return false;
    }

    if ((wakePhrases || []).some((phrase) => phrase && normalized.includes(phrase))) {
        return true;
    }

    return normalized.split(/\s+/).some((word) => flowdeskSoundsLikeNovaWord(word));
}

/** Locale-aware wake phrases for the Nova voice assistant. */
export function flowdeskVellisWakePhrases(assistantName, wakeBrand, appLocale) {
    const brand = flowdeskNormalizeVoicePhrase(wakeBrand || 'nova');
    const full = flowdeskNormalizeVoicePhrase(assistantName || '');
    const base = flowdeskVoiceAppLocale(appLocale);
    const phrases = new Set();

    const add = (phrase) => {
        const normalized = flowdeskNormalizeVoicePhrase(phrase);
        if (normalized) {
            phrases.add(normalized);
        }
    };

    if (brand) {
        add(brand);
        if (base === 'fr') {
            add(`hé ${brand}`);
            add(`hey ${brand}`);
            add(`ok ${brand}`);
        } else if (base === 'es') {
            add(`hola ${brand}`);
            add(`oye ${brand}`);
            add(`hey ${brand}`);
        } else if (base === 'ar') {
            add('نوفا');
            add('يا نوفا');
            add('هي نوفا');
            add('هاي نوفا');
            add('أوكي نوفا');
            add('اوكي نوفا');
            add('مرحبا نوفا');
            add('سلام نوفا');
            add('ناوا');
            add(`يا ${brand}`);
            add(`hey ${brand}`);
            add(`ok ${brand}`);
            add(`hi ${brand}`);
        } else {
            add(`hey ${brand}`);
            add(`ok ${brand}`);
        }
    }

    if (full) {
        add(full);
        if (base === 'ar') {
            add(`يا ${full}`);
        } else if (base === 'fr') {
            add(`hé ${full}`);
        } else if (base === 'es') {
            add(`hola ${full}`);
        } else {
            add(`hey ${full}`);
        }
    }

    return [...phrases];
}

/** Phrases that stop Nova speech / in-flight answers. */
export function flowdeskNovaStopPhrases(wakeBrand, appLocale) {
    const brand = flowdeskNormalizeVoicePhrase(wakeBrand || 'nova');
    const base = flowdeskVoiceAppLocale(appLocale);
    const phrases = new Set();

    const add = (phrase) => {
        const normalized = flowdeskNormalizeVoicePhrase(phrase);
        if (normalized) {
            phrases.add(normalized);
        }
    };

    add(`stop ${brand}`);
    add('stop nova');
    add('nova stop');
    add('quiet nova');
    add('silence nova');

    if (base === 'fr') {
        add(`arrete ${brand}`);
        add('arrete nova');
        add('arrête nova');
        add('tais toi nova');
        add('silence nova');
    } else if (base === 'es') {
        add(`para ${brand}`);
        add('para nova');
        add('deten nova');
        add('silencio nova');
    } else if (base === 'ar') {
        add('توقف نوفا');
        add('يا نوفا توقف');
        add('اخرس نوفا');
        add('اسكت نوفا');
    }

    return [...phrases];
}

/**
 * Hesitation fillers (“humm”, “euh”…) — Nova replies “take your time” and does not run a command.
 *
 * @returns {string[]}
 */
export function flowdeskNovaFillerPhrases(appLocale) {
    const base = flowdeskVoiceAppLocale(appLocale);
    const phrases = new Set();

    const add = (phrase) => {
        const normalized = flowdeskNormalizeVoicePhrase(phrase);
        if (normalized) {
            phrases.add(normalized);
        }
    };

    // Shared across locales (ASR often returns English-style fillers).
    ['hmm', 'hm', 'humm', 'hum', 'mm', 'mmm', 'uh', 'um', 'umm', 'erm', 'uhh', 'ahh'].forEach(add);

    if (base === 'fr') {
        ['euh', 'heu'].forEach(add);
    } else if (base === 'es') {
        ['eh', 'emm'].forEach(add);
    } else if (base === 'ar') {
        ['هم', 'مم', 'اه'].forEach(add);
    } else if (base === 'hi') {
        ['अं', 'उम्म', 'हम्म'].forEach(add);
    } else if (base === 'id') {
        ['eh', 'emm'].forEach(add);
    }

    return [...phrases];
}

/** True when the whole utterance is only a hesitation filler. */
export function flowdeskMatchesFillerPhrase(text, fillerPhrases) {
    const normalized = flowdeskNormalizeVoicePhrase(text);
    if (!normalized) {
        return false;
    }

    const phrases = fillerPhrases || [];
    if (phrases.some((phrase) => normalized === phrase)) {
        return true;
    }

    // Repeated fillers: "hmm hmm", "euh euh"
    const parts = normalized.split(/\s+/).filter(Boolean);
    if (parts.length >= 2 && parts.length <= 4 && parts.every((part) => phrases.includes(part))) {
        return true;
    }

    return false;
}

/** Phrases that pause always-on voice listening (mic stays off logically, UI shows paused). */
export function flowdeskNovaStopListeningPhrases(wakeBrand, appLocale) {
    const brand = flowdeskNormalizeVoicePhrase(wakeBrand || 'nova');
    const base = flowdeskVoiceAppLocale(appLocale);
    const phrases = new Set();

    const add = (phrase) => {
        const normalized = flowdeskNormalizeVoicePhrase(phrase);
        if (normalized) {
            phrases.add(normalized);
        }
    };

    add('stop listening');
    add(`stop listening ${brand}`);
    add(`${brand} stop listening`);

    if (base === 'fr') {
        add('arrete d ecouter');
        add('arrête d écouter');
        add('arrete l ecoute');
        add('arrête l écoute');
        add('ne ecoute plus');
        add('ne écoute plus');
    } else if (base === 'es') {
        add('deja de escuchar');
        add('dejar de escuchar');
        add('para de escuchar');
    } else if (base === 'ar') {
        add('توقف عن الاستماع');
        add('لا تستمع');
    } else if (base === 'hi') {
        add('sunna band karo');
        add('suno mat');
    } else if (base === 'id') {
        add('berhenti mendengarkan');
        add('jangan dengarkan');
    }

    return [...phrases];
}

/** Phrases that resume always-on voice listening after a pause. */
export function flowdeskNovaStartListeningPhrases(wakeBrand, appLocale) {
    const brand = flowdeskNormalizeVoicePhrase(wakeBrand || 'nova');
    const base = flowdeskVoiceAppLocale(appLocale);
    const phrases = new Set();

    const add = (phrase) => {
        const normalized = flowdeskNormalizeVoicePhrase(phrase);
        if (normalized) {
            phrases.add(normalized);
        }
    };

    add('start listening');
    add(`start listening ${brand}`);
    add(`${brand} start listening`);
    add('listen again');

    if (base === 'fr') {
        add('commence a ecouter');
        add('commence à écouter');
        add('recommence a ecouter');
        add('recommence à écouter');
    } else if (base === 'es') {
        add('empieza a escuchar');
        add('comienza a escuchar');
        add('vuelve a escuchar');
    } else if (base === 'ar') {
        add('ابدأ الاستماع');
        add('استمع مجددا');
    } else if (base === 'hi') {
        add('sunna shuru karo');
        add('phir se suno');
    } else if (base === 'id') {
        add('mulai mendengarkan');
        add('dengarkan lagi');
    }

    return [...phrases];
}

/** Phrases that ask Nova to introduce itself and explain capabilities. */
export function flowdeskNovaIdentityPhrases(appLocale) {
    const base = flowdeskVoiceAppLocale(appLocale);
    const patterns = new Set([
        'who are you',
        'what are you',
        'who is nova',
        'introduce yourself',
        'present yourself',
        'tell me about yourself',
        'what can you do',
        'what do you do',
        'what are your capabilities',
        'how can you help',
        'how do you work',
        // Always include common FR / AR forms (speech and help often mix locales).
        'qui es tu',
        'qui es-tu',
        'qui etes vous',
        'presente toi',
        'présente toi',
        'présente-toi',
        'que peux tu faire',
        'que peux-tu faire',
        'que sais tu faire',
        'a quoi tu sers',
        'parle moi de toi',
        'parle-moi de toi',
        'من انت',
        'من أنت',
        'من انا',
        'من أنا',
        'عرف بنفسك',
        'عرّف بنفسك',
        'عرفي بنفسك',
        'من هي نوفا',
        'من هي nova',
        'ماذا يمكنك',
        'ماذا تستطيع',
        'ايش تقدر تسوي',
        'وش تقدر تسوي',
    ]);

    if (base === 'fr') {
        // already merged above
    } else if (base === 'es') {
        [
            'quien eres',
            'quién eres',
            'presentate',
            'preséntate',
            'que puedes hacer',
            'qué puedes hacer',
            'hablame de ti',
            'háblame de ti',
        ].forEach((p) => patterns.add(p));
    } else if (base === 'ar') {
        // already merged above
    } else if (base === 'hi') {
        ['tum kaun ho', 'aap kaun hain', 'apna parichay', 'tum kya kar sakte ho'].forEach((p) => patterns.add(p));
    } else if (base === 'id') {
        ['siapa kamu', 'perkenalkan diri', 'apa yang bisa kamu lakukan'].forEach((p) => patterns.add(p));
    }

    return [...patterns];
}

/** True when the user asks who Nova is or what Nova can do. */
export function flowdeskMatchesIdentityPhrase(text, identityPhrases) {
    const normalized = flowdeskNormalizeVoicePhrase(text);
    if (!normalized) {
        return false;
    }

    return (identityPhrases || []).some((phrase) => {
        const needle = flowdeskNormalizeVoicePhrase(phrase);
        return needle !== '' && normalized.includes(needle);
    });
}

/** True when the user wants Nova to stop speaking or cancel the current reply. */
export function flowdeskMatchesStopPhrase(text, stopPhrases) {
    const normalized = flowdeskNormalizeVoicePhrase(text);
    if (!normalized) {
        return false;
    }

    return (stopPhrases || []).some((phrase) => {
        if (!phrase) {
            return false;
        }

        return normalized === phrase
            || normalized.startsWith(`${phrase} `)
            || normalized.endsWith(` ${phrase}`)
            || normalized.includes(` ${phrase} `);
    });
}

/** French number words → digits (for « grand un », etc.). */
const FLOWDESK_VOICE_FR_NUMBERS = {
    un: '1',
    une: '1',
    deux: '2',
    trois: '3',
    quatre: '4',
    cinq: '5',
    six: '6',
    sept: '7',
    huit: '8',
    neuf: '9',
    dix: '10',
    onze: '11',
    douze: '12',
};

const FLOWDESK_VOICE_EN_NUMBERS = {
    one: '1',
    two: '2',
    three: '3',
    four: '4',
    five: '5',
    six: '6',
    seven: '7',
    eight: '8',
    nine: '9',
    ten: '10',
    eleven: '11',
    twelve: '12',
};

const FLOWDESK_VOICE_ES_NUMBERS = {
    uno: '1',
    una: '1',
    dos: '2',
    tres: '3',
    cuatro: '4',
    cinco: '5',
    seis: '6',
    siete: '7',
    ocho: '8',
    nueve: '9',
    diez: '10',
};

function flowdeskVoiceNumberToken(token, map) {
    const key = String(token || '').toLowerCase();
    if (/^\d+$/.test(key)) {
        return key;
    }

    return map[key] || token;
}

function flowdeskReplaceVoiceHeadings(text) {
    let s = String(text || '');

    s = s.replace(
        /\b(?:grand(?:e|es|s)?|titre|titres|section|chapitre)\s+(un|une|deux|trois|quatre|cinq|six|sept|huit|neuf|dix|onze|douze|\d+)\b/gi,
        (_, raw) => `\n${flowdeskVoiceNumberToken(raw, FLOWDESK_VOICE_FR_NUMBERS)}. `,
    );

    s = s.replace(
        /\b(?:number|item|heading|section)\s+(one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve|\d+)\b/gi,
        (_, raw) => `\n${flowdeskVoiceNumberToken(raw, FLOWDESK_VOICE_EN_NUMBERS)}. `,
    );

    s = s.replace(
        /\b(?:numero|n[uú]mero|titulo|t[ií]tulo|seccion|secci[oó]n)\s+(uno|una|dos|tres|cuatro|cinco|seis|siete|ocho|nueve|diez|\d+)\b/gi,
        (_, raw) => `\n${flowdeskVoiceNumberToken(raw, FLOWDESK_VOICE_ES_NUMBERS)}. `,
    );

    return s;
}

function flowdeskTrimDictation(text) {
    const s = String(text ?? '');
    if (s === '') {
        return '';
    }

    if (/^[\s\u00a0]+$/u.test(s)) {
        return s;
    }

    return s.replace(/^[ \t\u00a0]+|[ \t\u00a0]+$/g, '');
}

/** Spoken punctuation & formatting → symbols in the textarea. */
function flowdeskApplyVoiceDictation(text) {
    let s = String(text || '');
    const preserveCurrencyWords = Boolean(typeof document !== 'undefined' && document.getElementById('currency'));

    s = flowdeskReplaceVoiceHeadings(s);

    const multi = [
        // Doubles first, otherwise « retour à la ligne » consumes the inner part.
        [/double\s+retour\s+(?:à|a)\s+la\s+ligne/gi, '\n\n'],
        [/double\s+saut\s+de\s+(?:la\s+)?ligne/gi, '\n\n'],
        [/double\s+(?:à|a)\s+la\s+ligne/gi, '\n\n'],
        [/double\s+ligne/gi, '\n\n'],
        [/retour\s+(?:à|a)\s+la\s+ligne/gi, '\n'],
        [/saut\s+de\s+(?:la\s+)?ligne/gi, '\n'],
        [/point\s+d['']interrogation/gi, '?'],
        [/point\s+d['']exclamation/gi, '!'],
        [/points?\s+de\s+suspension/gi, '...'],
        [/parenthèse\s+ouvrante/gi, '('],
        [/parenthese\s+ouvrante/gi, '('],
        [/parenthèse\s+fermante/gi, ')'],
        [/parenthese\s+fermante/gi, ')'],
        [/ouvrir\s+(?:la\s+)?parenthèse/gi, '('],
        [/ouvrir\s+(?:la\s+)?parenthese/gi, '('],
        [/fermer\s+(?:la\s+)?parenthèse/gi, ')'],
        [/fermer\s+(?:la\s+)?parenthese/gi, ')'],
        [/guillemet\s+ouvrant/gi, '«'],
        [/guillemet\s+fermant/gi, '»'],
        [/ouvrir\s+(?:les\s+)?guillemets/gi, '«'],
        [/fermer\s+(?:les\s+)?guillemets/gi, '»'],
        [/trait\s+d['']union/gi, '-'],
        [/signe\s+moins/gi, '-'],
        [/signe\s+plus/gi, '+'],
        [/deux\s+points/gi, ':'],
        [/\bpoint\s+virgule\b/gi, ';'],
        [/nouvelle\s+ligne/gi, '\n'],
        [/\b(?:à|a)\s+la\s+ligne\b/gi, '\n'],
        [/\bnew\s+line\b/gi, '\n'],
        [/\bline\s+break\b/gi, '\n'],
        [/\bnext\s+line\b/gi, '\n'],
        [/\bnew\s+paragraph\b/gi, '\n\n'],
        [/\bparagraph\b/gi, '\n\n'],
        [/\bparagraphe\b/gi, '\n\n'],
        [/\bnueva\s+l[ií]nea\b/gi, '\n'],
        [/\bnuevo\s+p[aá]rrafo\b/gi, '\n\n'],
        [/\bsalto\s+de\s+l[ií]nea\b/gi, '\n'],
    ];
    for (const [re, rep] of multi) {
        s = s.replace(re, rep);
    }

    const singles = [
        [/\bespace\b/gi, ' '],
        [/\bspace\b/gi, ' '],
        [/\bespacio\b/gi, ' '],
        [/\bvirgule\b/gi, ','],
        [/\bcomma\b/gi, ','],
        [/\bcoma\b/gi, ','],
        [/\bpoint\b(?!\s+de\b)/gi, '.'],
        [/\bperiod\b/gi, '.'],
        [/\bdot\b/gi, '.'],
        [/\bpunto\b/gi, '.'],
        [/\btiret\b/gi, '-'],
        [/\bdash\b/gi, '-'],
        [/\barrobase\b/gi, '@'],
        [/\ba\s+robas\b/gi, '@'],
        [/\bat\s+sign\b/gi, '@'],
        [/\bpourcent(?:age)?\b/gi, '%'],
        [/\bpercent(?:age)?\b/gi, '%'],
        [/\bpour\s+cent\b/gi, '%'],
        ...(preserveCurrencyWords ? [] : [
            [/\beuro\b/gi, '€'],
            [/\beuro\s+sign\b/gi, '€'],
            [/\bdollar\b/gi, '$'],
            [/\bdollar\s+sign\b/gi, '$'],
        ]),
        [/\bsoulign[eé]\b/gi, '_'],
        [/\bunderscore\b/gi, '_'],
        [/\bbarre\s+oblique\b/gi, '/'],
        [/\bslash\b/gi, '/'],
        [/\bégal\b/gi, '='],
        [/\begale?\b/gi, '='],
        [/\bequals?\b/gi, '='],
        [/\bexclamation\s+mark\b/gi, '!'],
        [/\bquestion\s+mark\b/gi, '?'],
        [/\bsemicolon\b/gi, ';'],
        [/\bpuce\b/gi, '\n• '],
        [/\bbullet\s+point\b/gi, '\n• '],
    ];
    for (const [re, rep] of singles) {
        s = s.replace(re, rep);
    }

    s = s.replace(/[ \t]*\n[ \t]*/g, '\n');
    s = s.replace(/\n{3,}/g, '\n\n');
    s = s.replace(/\s+([.,!?;:…])/g, '$1');
    s = s.replace(/([.,!?;:])[ \t]*(?=[^\s])/g, '$1 ');
    s = s.replace(/[ \t]{2,}/g, ' ');

    return flowdeskTrimDictation(s);
}

/** Voice-only commands that clear the target textarea instead of appending. */
function flowdeskIsVoiceClearCommand(text) {
    const n = flowdeskNormalizeVoicePhrase(text);
    if (!n) {
        return false;
    }

    const exact = new Set([
        // French
        'effacer tout le texte',
        'effacer tout',
        'efface tout le texte',
        'efface tout',
        'effacer le texte',
        'efface le texte',
        'effacer tous le texte',
        'efface tous le texte',
        'effacer touts le texte',
        'efface touts le texte',
        'effacer tout le text',
        'efface tout le text',
        'supprimer tout',
        'supprimer tout le texte',
        'supprimer le texte',
        'vider le texte',
        'vider tout',
        'tout effacer',
        'effacer la zone de texte',
        'efface la zone de texte',
        // English
        'clear all text',
        'clear all',
        'clear everything',
        'clear text',
        'clear the text',
        'delete all text',
        'delete all',
        'delete everything',
        'erase all',
        'erase all text',
        'empty text',
        'empty the text',
        'remove all text',
        'wipe all text',
        // Spanish
        'borrar todo el texto',
        'borrar todo',
        'eliminar todo',
        'eliminar todo el texto',
        'limpiar todo',
        'vaciar todo',
        // Arabic (common STT output)
        'امسح كل النص',
        'احذف كل شيء',
        'امسح الكل',
    ]);

    if (exact.has(n)) {
        return true;
    }

    const patterns = [
        /^(efface?r?|supprimer|vider)(\s+(tout|tous|touts))(\s+(le\s+)?(texte|text|contenu|champ|zone))?$/,
        /^tout\s+efface?r$/,
        /^(clear|delete|erase|remove|empty|wipe)(\s+(all|everything|the))?\s*(text|textarea|field|content|box)?$/,
        /^(borrar|eliminar|limpiar|vaciar)\s+todo(\s+(el\s+)?texto)?$/,
        /^امسح\s+(كل\s+)?(النص|الكل)$/,
        /^احذف\s+(كل\s+)?(النص|الشيء|الكل)$/,
    ];

    return patterns.some((re) => re.test(n));
}

/** Voice commands that save the main document form (not AI lines). */
function flowdeskIsVoiceSaveCommand(text) {
    const n = flowdeskNormalizeVoicePhrase(text);
    if (!n) {
        return false;
    }

    const exact = new Set([
        'terminer',
        'termine',
        'terminez',
        'terminate',
        'termine l action',
        'terminer l action',
        'sauvegarder',
        'enregistrer',
        'save',
        'save form',
        'save invoice',
        'save quote',
        'save project',
        'fin',
        'fini',
        'c est bon',
        'envoyer le formulaire',
        'soumettre',
        'soumet',
        'soumettre le formulaire',
        'valider',
        'valide',
        'validez',
        'submit',
        'submit form',
        'done',
        'finish',
        'enviar',
        'terminar',
        'validar',
        'hecho',
        'mandar',
        'ارسل',
        'إرسال',
        'تم',
        'انهاء',
    ]);

    if (exact.has(n)) {
        return true;
    }

    return /^(terminer|termine|terminez|terminate|sauvegarder|enregistrer|save|soumettre|soumet|valider|valide|validez|submit|done|finish|enviar|terminar|validar)$/u.test(n)
        || /^(terminer|termine|sauvegarder|enregistrer|save)\s+(l?\s*action|formulaire|facture|devis|projet|invoice|quote|project|form)$/u.test(n);
}

/** Voice commands that run AI line items (Lignes IA). */
function flowdeskIsVoiceGenerateCommand(text) {
    const n = flowdeskNormalizeVoicePhrase(text);
    if (!n) {
        return false;
    }

    const exact = new Set([
        'completer',
        'compléter',
        'complete',
        'complet',
        'completez',
        'complétez',
        'generer',
        'générer',
        'generate',
        'genere',
        'génère',
        'génerez',
        'please generate',
        'ok generate',
        'execute',
        'executer',
        'exécuter',
        'executez',
        'exécutez',
        'exécute',
        'please execute',
        'ok execute',
        'ejecutar',
        'ejecuta',
        'lignes ia',
        'ligne ia',
        'creer lignes ia',
        'créer lignes ia',
        'generer lignes',
        'générer lignes',
        'generate lines',
        'generate line items',
        'ai lines',
        'ai line items',
        'lance la generation',
        'lancer la generation',
        'lance la génération',
        'lancer la génération',
        'run',
        'go',
    ]);

    if (exact.has(n)) {
        return true;
    }

    return /^(complet(er|ez)?|compléter|complete|generer|générer|generate|genere|génère|génerez|execute|executer|exécuter|executez|exécutez|exécute|ejecutar|ejecuta)$/u.test(n)
        || /^(please|ok|okay)?\s*(generer|générer|generate|genere|génère|execute|executer|exécuter|ejecutar)\b/u.test(n)
        || /^(creer|créer|generer|générer|generate|execute|executer|exécuter)\s+(les\s+)?(lignes|lines)(\s+ia)?$/u.test(n)
        || /^lignes?\s+ia$/u.test(n);
}

/** @deprecated Use save/generate helpers — kept for callers that only need "any action word". */
function flowdeskIsVoiceSubmitCommand(text) {
    return flowdeskIsVoiceSaveCommand(text) || flowdeskIsVoiceGenerateCommand(text);
}

/**
 * Detect save/generate-only commands or a trailing action phrase (e.g. "… scope terminer").
 *
 * @returns {{ text: string, save: boolean, generate: boolean }}
 */
function flowdeskParseVoiceWithSubmit(text) {
    const original = String(text || '');
    const raw = original.trim();
    if (!raw) {
        return { text: /\n/.test(original) ? original.replace(/[^\n]/g, '') : '', save: false, generate: false };
    }

    if (flowdeskIsVoiceGenerateCommand(raw)) {
        return { text: '', save: false, generate: true };
    }
    if (flowdeskIsVoiceSaveCommand(raw)) {
        return { text: '', save: true, generate: false };
    }

    const n = flowdeskNormalizeVoicePhrase(raw);
    const generateSuffix = /\s+(completer|compléter|complete|complet|completez|complétez|generer|générer|generate|genere|génère|génerez|execute|executer|exécuter|executez|exécutez|exécute|ejecutar|ejecuta|run|go)$/;
    const saveSuffix = /\s+(terminer|termine|terminez|terminate|sauvegarder|enregistrer|envoyer|envoie|valider|valide|validez|soumettre|soumet|fin|fini|lance|lancer|submit|send|done|finish|enviar|terminar|validar|hecho|mandar|ارسل|إرسال|تم)$/;

    let match = n.match(generateSuffix);
    if (match) {
        const rawWords = raw.split(/\s+/);
        const stripped = rawWords.length > 1 ? rawWords.slice(0, -1).join(' ').trim() : '';

        return { text: stripped, save: false, generate: true };
    }

    match = n.match(saveSuffix);
    if (match) {
        const rawWords = raw.split(/\s+/);
        const stripped = rawWords.length > 1 ? rawWords.slice(0, -1).join(' ').trim() : '';

        return { text: stripped, save: true, generate: false };
    }

    return { text: original.replace(/^[ \t\u00a0]+|[ \t\u00a0]+$/g, ''), save: false, generate: false };
}

const FLOWDESK_CURRENCY_ALIASES = {
    eur: 'EUR',
    euro: 'EUR',
    euros: 'EUR',
    usd: 'USD',
    dollar: 'USD',
    dollars: 'USD',
    cad: 'CAD',
    canadian: 'CAD',
    gbp: 'GBP',
    pound: 'GBP',
    pounds: 'GBP',
    chf: 'CHF',
    franc: 'CHF',
    francs: 'CHF',
    tnd: 'TND',
    dinar: 'TND',
    dinars: 'TND',
    mad: 'MAD',
    qar: 'QAR',
    riyal: 'QAR',
    riyals: 'QAR',
    xof: 'XOF',
    xaf: 'XAF',
    ngn: 'NGN',
    zar: 'ZAR',
    kes: 'KES',
    egp: 'EGP',
    aed: 'AED',
    dirham: 'AED',
    dirhams: 'AED',
};

function flowdeskResolveCurrencyToken(token) {
    const raw = String(token || '').trim().toLowerCase();
    if (!raw) {
        return null;
    }
    if (/^[a-z]{3}$/u.test(raw)) {
        return raw.toUpperCase();
    }

    return FLOWDESK_CURRENCY_ALIASES[raw] || null;
}

function flowdeskCurrencyVoiceTokenPattern() {
    const tokens = [...new Set([
        ...Object.keys(FLOWDESK_CURRENCY_ALIASES),
        ...Object.values(FLOWDESK_CURRENCY_ALIASES).map((code) => code.toLowerCase()),
    ])].sort((a, b) => b.length - a.length);

    return tokens.map((token) => token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).join('|');
}

function flowdeskStripStandaloneCurrencyFromBrief(brief, fields) {
    let text = String(brief || '').trim();
    if (!text) {
        return text;
    }

    const tokenPattern = flowdeskCurrencyVoiceTokenPattern();
    const normalized = flowdeskNormalizeVoicePhrase(text);
    const words = normalized.split(/\s+/).filter(Boolean);

    if (words.length <= 2) {
        const code = flowdeskResolveCurrencyToken(words[words.length - 1] || normalized);
        if (code && words.every((word) => flowdeskResolveCurrencyToken(word) === code || ['devise', 'currency', 'monnaie', 'en', 'in'].includes(word))) {
            fields.currency = code;
            return '';
        }
    }

    const trailingMatch = text.match(new RegExp(`\\s+(${tokenPattern})\\s*[.,!?]?\\s*$`, 'iu'));
    if (trailingMatch) {
        const code = flowdeskResolveCurrencyToken(trailingMatch[1]);
        if (code) {
            fields.currency = code;
            text = text.slice(0, trailingMatch.index).trim();
        }
    }

    const leadingMatch = text.match(new RegExp(`^(${tokenPattern})\\b\\s+`, 'iu'));
    if (leadingMatch) {
        const code = flowdeskResolveCurrencyToken(leadingMatch[1]);
        if (code) {
            fields.currency = code;
            text = text.slice(leadingMatch[0].length).trim();
        }
    }

    return text;
}

function flowdeskApplyCurrencySelect(code) {
    const sel = document.getElementById('currency');
    if (!sel) {
        return false;
    }

    const upper = String(code || '').toUpperCase();
    const hasOption = Array.from(sel.options).some((opt) => opt.value.toUpperCase() === upper);
    if (!hasOption) {
        return false;
    }

    sel.value = upper;
    sel.dispatchEvent(new Event('change', { bubbles: true }));
    sel.dispatchEvent(new Event('input', { bubbles: true }));

    const alpineRoot = sel.closest('[x-data]');
    const alpineData = alpineRoot?._x_dataStack?.[0];
    if (alpineData && typeof alpineData.syncCurrencyMoney === 'function') {
        alpineData.syncCurrencyMoney(upper);
    }

    return true;
}

/**
 * Extract form field hints from AI dictation (currency, client, title).
 * Remaining text is the AI brief for the textarea.
 *
 * @returns {{ brief: string, fields: { currency?: string, clientName?: string, name?: string } }}
 */
export function flowdeskParseAiFormVoiceSegment(text) {
    let brief = String(text || '').trim();
    const fields = {};

    const currencyPatterns = [
        /\b(?:devise|currency|monnaie)\s+([a-z]{3}|euros?|dollars?|dinars?|francs?|pounds?|riyals?|dirhams?|canadian)\b/giu,
        /\b(?:en|in)\s+([a-z]{3}|euros?|dollars?|dinars?|francs?|pounds?)\b/giu,
    ];

    for (const re of currencyPatterns) {
        let match;
        while ((match = re.exec(brief)) !== null) {
            const code = flowdeskResolveCurrencyToken(match[1]);
            if (code) {
                fields.currency = code;
                brief = (brief.slice(0, match.index) + brief.slice(match.index + match[0].length)).trim();
                re.lastIndex = 0;
            }
        }
    }

    const clientMatch = brief.match(
        /\b(?:client|cliente|for client|pour le client|pour la cliente|pour client|le client|la cliente)\s+(.+?)(?:\.|,|;|$)/iu,
    );
    if (clientMatch) {
        fields.clientName = clientMatch[1].trim();
        brief = (brief.slice(0, clientMatch.index) + brief.slice(clientMatch.index + clientMatch[0].length)).trim();
    }

    const nameMatch = brief.match(
        /\b(?:titre|title|nom de (?:la )?facture|nom de (?:le )?devis|invoice name|quote name|nom)\s+(.+?)(?:\.|,|;|$)/iu,
    );
    if (nameMatch) {
        fields.name = nameMatch[1].trim();
        brief = (brief.slice(0, nameMatch.index) + brief.slice(nameMatch.index + nameMatch[0].length)).trim();
    }

    const newClientRe = /\b(?:nouveau client|nouvelle cliente|new client|ajouter un client|ajouter client|ajout client|ajout un client|creer client|créer client|create client|add client)\b/giu;
    let newClientMatch;
    while ((newClientMatch = newClientRe.exec(brief)) !== null) {
        fields.openQuickClient = true;
        brief = (brief.slice(0, newClientMatch.index) + brief.slice(newClientMatch.index + newClientMatch[0].length)).trim();
        newClientRe.lastIndex = 0;
    }

    brief = flowdeskStripStandaloneCurrencyFromBrief(brief, fields);

    return { brief, fields };
}

const FLOWDESK_NEW_CLIENT_VOICE_LABELS = [
    'nouveau client',
    'nouvelle cliente',
    'new client',
    'ajouter un client',
    'ajouter client',
    'ajout client',
    'ajout un client',
    'creer client',
    'créer client',
    'create client',
    'add client',
];

function flowdeskIsNewClientVoiceLabel(text) {
    const normalized = flowdeskNormalizeVoicePhrase(text);
    if (!normalized) {
        return false;
    }

    return FLOWDESK_NEW_CLIENT_VOICE_LABELS.some((phrase) => normalized === phrase || normalized.includes(phrase));
}

function flowdeskSpeakBrowserText(text, locale = 'en') {
    const line = String(text || '').trim();
    if (!line || typeof window === 'undefined' || !window.speechSynthesis) {
        return;
    }

    try {
        window.speechSynthesis.cancel();
    } catch {
        // ignore
    }

    const utterance = new SpeechSynthesisUtterance(line);
    utterance.lang = locale || document.documentElement.lang || 'en';
    document.dispatchEvent(new CustomEvent('flowdesk-voice-pause-listen'));
    utterance.onend = () => {
        document.dispatchEvent(new CustomEvent('flowdesk-voice-resume-listen'));
    };
    utterance.onerror = () => {
        document.dispatchEvent(new CustomEvent('flowdesk-voice-resume-listen'));
    };
    window.speechSynthesis.speak(utterance);
}

function flowdeskFormatClientVoiceSay(client, labels = {}) {
    const name = String(client?.name || '').trim() || '—';
    const phone = String(client?.phone || '').trim() || (labels.noPhone || 'no phone');
    const email = String(client?.email || '').trim() || (labels.noEmail || 'no email');
    const template = labels.clientSelected
        || ':name. Phone: :phone. Email: :email.';

    return template
        .replaceAll(':name', name)
        .replaceAll(':phone', phone)
        .replaceAll(':email', email);
}

/**
 * @returns {{ ok: boolean, client?: { id: string, name: string, phone: string, email: string } }}
 */
function flowdeskSelectClientByVoiceName(clientName) {
    const sel = document.getElementById('client_id');
    const needle = flowdeskNormalizeVoicePhrase(clientName);
    if (!sel || !needle) {
        return { ok: false };
    }

    let best = null;
    for (const opt of sel.options) {
        if (!opt.value) {
            continue;
        }
        const label = flowdeskNormalizeVoicePhrase(opt.text);
        if (label === needle || label.includes(needle) || needle.includes(label)) {
            best = opt;
            if (label === needle || label.startsWith(needle)) {
                break;
            }
        }
    }

    if (!best) {
        return { ok: false };
    }

    sel.value = best.value;
    sel.dispatchEvent(new Event('change', { bubbles: true }));

    return {
        ok: true,
        client: {
            id: best.value,
            name: best.textContent?.trim() || best.text || '',
            phone: best.getAttribute('data-phone') || '',
            email: best.getAttribute('data-email') || '',
        },
    };
}

function flowdeskOpenQuickClientModal(name = '') {
    document.dispatchEvent(new CustomEvent('flowdesk-open-quick-client', {
        detail: { name: String(name || '').trim() },
    }));
}

function flowdeskApplyAiFormVoiceFields(fields, labels = {}) {
    if (!fields || typeof fields !== 'object') {
        return;
    }

    if (fields.currency) {
        flowdeskApplyCurrencySelect(fields.currency);
    }

    if (fields.openQuickClient) {
        flowdeskOpenQuickClientModal('');
    }

    if (fields.clientName) {
        if (flowdeskIsNewClientVoiceLabel(fields.clientName)) {
            flowdeskOpenQuickClientModal('');
        } else {
            const selected = flowdeskSelectClientByVoiceName(fields.clientName);
            if (selected.ok && selected.client) {
                const locale = labels.speechLocale || document.documentElement.lang || 'en';
                flowdeskSpeakBrowserText(flowdeskFormatClientVoiceSay(selected.client, labels), locale);
            } else {
                flowdeskOpenQuickClientModal(fields.clientName);
            }
        }
    }

    if (fields.name) {
        for (const id of ['name', 'title']) {
            const el = document.getElementById(id);
            if (el) {
                el.value = fields.name;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
                break;
            }
        }
    }
}

export function registerFlowdeskAiFormVoiceBridge() {
    if (typeof document === 'undefined' || document.documentElement.dataset.flowdeskAiFormVoiceBridge === '1') {
        return;
    }
    document.documentElement.dataset.flowdeskAiFormVoiceBridge = '1';

    document.addEventListener('flowdesk-ai-form-voice', (event) => {
        const detail = event.detail || {};
        flowdeskApplyAiFormVoiceFields(detail.fields || detail, detail.labels || {});
    });
}

export function registerAiVoiceField(Alpine) {
    Alpine.data('aiVoiceField', (cfg = {}) => ({
        listening: false,
        supported: false,
        interim: '',
        error: null,
        recognition: null,
        targetId: cfg.targetId || null,
        submitButtonId: cfg.submitButtonId || null,
        locale: cfg.locale || flowdeskSpeechLocale(cfg.appLocale) || 'en-US',
        appLocale: cfg.appLocale || 'en',
        labels: cfg.labels || {},
        autoStart: Boolean(cfg.autoStart),
        _voiceBusy: false,
        _startToken: 0,

        init() {
            const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
            const speechLocale = flowdeskSpeechLocale(this.appLocale);
            this.supported = Boolean(SR) && Boolean(speechLocale);
            if (!SR || !speechLocale) {
                return;
            }

            this.recognition = new SR();
            this.recognition.continuous = true;
            this.recognition.interimResults = true;
            this.recognition.lang = speechLocale;

            this.recognition.onresult = (event) => {
                let interim = '';
                let finalText = '';
                for (let i = event.resultIndex; i < event.results.length; i++) {
                    const part = event.results[i][0]?.transcript || '';
                    if (event.results[i].isFinal) {
                        finalText += part;
                    } else {
                        interim += part;
                    }
                }
                this.interim = interim.trim();
                if (finalText.trim()) {
                    const raw = finalText.trim();
                    const { brief: segmentBrief, fields } = flowdeskParseAiFormVoiceSegment(raw);
                    if (fields.currency || fields.clientName || fields.name || fields.openQuickClient) {
                        document.dispatchEvent(new CustomEvent('flowdesk-ai-form-voice', {
                            detail: {
                                fields,
                                labels: {
                                    ...(this.labels || {}),
                                    speechLocale: this.locale || this.appLocale || 'en',
                                },
                            },
                        }));
                    }

                    const phrase = flowdeskApplyVoiceDictation(segmentBrief);
                    if (flowdeskIsVoiceClearCommand(phrase) || flowdeskIsVoiceClearCommand(raw)) {
                        this.clearTarget();
                    } else {
                        const parsed = flowdeskParseVoiceWithSubmit(phrase);
                        if (parsed.text) {
                            this.appendToTarget(parsed.text);
                        }
                        if (parsed.generate) {
                            this.triggerGenerate();
                        } else if (parsed.save) {
                            this.triggerSave();
                        }
                    }
                    this.interim = '';
                }
            };

            this.recognition.onerror = (event) => {
                // Intentional stop() / triggerGenerate() — do not tear down the generate hold.
                if (event.error === 'aborted') {
                    return;
                }
                if (event.error === 'not-allowed') {
                    this.error = this.labels.permission || 'Microphone permission denied.';
                } else if (event.error === 'language-not-supported') {
                    const switched = flowdeskApplySpeechLocaleFallback(
                        this.recognition,
                        this.appLocale,
                        this.locale,
                        (next) => {
                            this.locale = next;
                        },
                    );
                    if (switched) {
                        return;
                    }
                } else if (event.error !== 'no-speech') {
                    this.error = event.error;
                }
                this.stop();
            };

            this.recognition.onend = () => {
                if (this.listening) {
                    try {
                        this.recognition.start();
                    } catch {
                        this.listening = false;
                    }
                }
            };

            this.$nextTick(() => {
                const el = this.getTarget();
                if (el && !el.classList.contains('pe-12')) {
                    el.classList.remove('pe-12');
                }
                if (this.autoStart && this.supported) {
                    window.setTimeout(() => this.start(), 500);
                }
            });

            this._onPauseListen = () => {
                if (this.listening || this._pausedForSpeak) {
                    this._pausedForSpeak = true;
                    this.listening = false;
                    try {
                        this.recognition?.stop();
                    } catch {
                        // ignore
                    }
                }
            };
            this._onResumeListen = () => {
                if (this._pausedForSpeak) {
                    this._pausedForSpeak = false;
                    window.setTimeout(() => this.start(), 250);
                }
            };
            document.addEventListener('flowdesk-voice-pause-listen', this._onPauseListen);
            document.addEventListener('flowdesk-voice-resume-listen', this._onResumeListen);
        },

        getTarget() {
            if (this.targetId) {
                return document.getElementById(this.targetId);
            }

            return this.$refs.voiceTarget || null;
        },

        clearTarget() {
            const el = this.getTarget();
            if (!el) {
                return;
            }
            el.value = '';
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.focus();
        },

        appendToTarget(text) {
            const el = this.getTarget();
            if (!el || text === '' || text === null || text === undefined) {
                return;
            }

            const chunk = String(text);
            const cur = String(el.value || '');
            // Trim trailing spaces/tabs only — keep newlines added by « retour à la ligne ».
            const trimmed = cur.replace(/[ \t\u00a0]+$/g, '');
            let sep = '';

            if (trimmed) {
                if (chunk.startsWith('\n')) {
                    sep = '';
                } else if (/^[,.;:!?)}\]»]/.test(chunk)) {
                    sep = '';
                } else if (chunk === ' ' || /^\s+$/.test(chunk)) {
                    sep = '';
                } else if (/[(\[«]$/.test(trimmed) || trimmed.endsWith('\n')) {
                    sep = '';
                } else {
                    sep = ' ';
                }
            }

            el.value = trimmed + sep + chunk;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
            el.focus();
        },

        triggerSubmit() {
            // Backward-compatible: prefer generate when this mic is bound to an AI button.
            if (this.submitButtonId) {
                this.triggerGenerate();
                return;
            }
            this.triggerSave();
        },

        triggerGenerate() {
            // Keep Nova paused: take generate hold BEFORE releasing field (never leave holders empty).
            flowdeskNotifyVoiceBusy('generate');
            if (this._voiceBusy) {
                flowdeskNotifyVoiceIdle('field');
                this._voiceBusy = false;
            }
            this.listening = false;
            this.interim = '';
            if (this.recognition) {
                try {
                    this.recognition.abort?.();
                } catch {
                    // ignore
                }
                try {
                    this.recognition.stop();
                } catch {
                    // ignore
                }
            }
            this.syncTargetToAlpine();
            // Run ASAP — do not wait on macOS speech teardown.
            window.setTimeout(() => this._performGenerate(), 30);
        },

        triggerSave() {
            this.stop();
            window.setTimeout(() => this._performSave(), 120);
        },

        /** Push textarea value into Alpine x-model before generate. */
        syncTargetToAlpine() {
            const el = this.getTarget();
            if (!el) {
                return;
            }
            const value = String(el.value || '');
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));

            let node = el;
            while (node) {
                const data = node._x_dataStack?.[0];
                if (data && typeof data === 'object') {
                    for (const key of ['aiBrief', 'aiPrompt', 'prompt', 'scanPrompt', 'query', 'notes']) {
                        if (Object.prototype.hasOwnProperty.call(data, key) && typeof data[key] === 'string') {
                            data[key] = value;
                        }
                    }
                }
                node = node.parentElement;
            }
        },

        _performGenerate() {
            // When this mic is bound to a specific AI button, only that button — never fall through
            // to unrelated generate controls, and never release Nova until submit succeeds or fails.
            const ids = this.submitButtonId
                ? [this.submitButtonId]
                : [
                    'invoice_ai_generate_btn',
                    'quote_ai_generate_btn',
                    'ai_desc_generate_btn',
                    'projects_ai_generate_btn',
                    'project_study_ai_generate_btn',
                    'ai_writing_generate_btn',
                    'contract_ai_generate_btn',
                ];

            for (const id of ids) {
                if (this.invokeGenerateByButtonId(id)) {
                    return;
                }
            }
            flowdeskNotifyVoiceIdle('generate');
        },

        invokeGenerateByButtonId(buttonId) {
            const btn = document.getElementById(buttonId);
            if (!btn || btn.disabled || btn.getAttribute('aria-disabled') === 'true') {
                return false;
            }

            // Prefer the closest Alpine action bound to this button.
            let node = btn;
            while (node) {
                const data = node._x_dataStack?.[0];
                if (data && typeof data === 'object') {
                    if (typeof data.suggestAiLines === 'function') {
                        void data.suggestAiLines();
                        return true;
                    }
                    if (typeof data.generate === 'function') {
                        void data.generate();
                        return true;
                    }
                    if (typeof data.generateDraft === 'function') {
                        void data.generateDraft();
                        return true;
                    }
                    if (typeof data.submitProjectsAi === 'function') {
                        const ok = data.submitProjectsAi();
                        if (!ok) {
                            flowdeskNotifyVoiceIdle('generate');
                        }
                        // Success: keep Nova on generate hold until navigation.
                        return true;
                    }
                }
                node = node.parentElement;
            }

            const form = btn.closest('form');
            if (form) {
                return this.submitFormForGenerate(form, btn);
            }

            btn.click();
            // Click may open async UI — release later only as safety valve.
            window.setTimeout(() => flowdeskNotifyVoiceIdle('generate'), 20000);
            return true;
        },

        submitFormForGenerate(form, btn) {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                form.reportValidity?.();
                flowdeskNotifyVoiceIdle('generate');
                return true;
            }

            // Keep Nova paused through the full-page POST. Do NOT idle after 400ms.
            if (btn) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
            }
            try {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(btn || undefined);
                } else {
                    form.submit();
                }
            } catch {
                try {
                    form.submit();
                } catch {
                    if (btn) {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                    }
                    flowdeskNotifyVoiceIdle('generate');
                    return true;
                }
            }

            // Safety: if validation/navigation did not leave the page, unlock later.
            window.setTimeout(() => {
                if (btn && document.body.contains(btn)) {
                    btn.disabled = false;
                    btn.removeAttribute('aria-busy');
                }
                flowdeskNotifyVoiceIdle('generate');
            }, 20000);
            return true;
        },

        _performSave() {
            const explicit = document.querySelector('[data-nova-voice-save]');
            if (explicit && !explicit.disabled) {
                explicit.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true, view: window }));
                return;
            }

            const clientSelect = document.getElementById('client_id');
            const form = clientSelect?.closest('form') || this.getTarget()?.closest('form');
            if (form) {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            }
        },

        _performSubmit() {
            this.triggerSubmit();
        },

        toggle() {
            if (!flowdeskIsVoiceLocaleSupported(this.appLocale)) {
                this.error = this.labels.localeUnsupported || this.labels.unsupported || 'Voice input is not available for this language.';
                return;
            }
            if (!this.supported) {
                this.error = this.labels.unsupported || 'Voice input is not supported in this browser.';
                return;
            }
            if (this.listening) {
                this.stop();
            } else {
                void this.start();
            }
        },

        async start() {
            this.error = null;
            this.interim = '';
            const token = ++this._startToken;

            // Pause Nova first and wait for it to release the shared mic.
            if (!this._voiceBusy) {
                flowdeskNotifyVoiceBusy('field');
                this._voiceBusy = true;
            }
            await new Promise((resolve) => window.setTimeout(resolve, 220));
            if (token !== this._startToken) {
                return;
            }

            this.listening = true;
            try {
                this.recognition.start();
            } catch {
                this.listening = false;
                if (this._voiceBusy) {
                    flowdeskNotifyVoiceIdle('field');
                    this._voiceBusy = false;
                }
            }
        },

        stop() {
            this._startToken += 1;
            this.listening = false;
            this.interim = '';
            if (this.recognition) {
                try {
                    this.recognition.stop();
                } catch {
                    // ignore
                }
            }
            if (this._voiceBusy) {
                flowdeskNotifyVoiceIdle('field');
                this._voiceBusy = false;
            }
        },

        destroy() {
            document.removeEventListener('flowdesk-voice-pause-listen', this._onPauseListen);
            document.removeEventListener('flowdesk-voice-resume-listen', this._onResumeListen);
            this.stop();
        },
    }));
}

export function flowdeskNotifyVoiceBusy(source = 'field') {
    document.dispatchEvent(new CustomEvent('flowdesk-voice-busy', {
        detail: { source: String(source || 'field') },
    }));
}

export function flowdeskNotifyVoiceIdle(source = 'field') {
    document.dispatchEvent(new CustomEvent('flowdesk-voice-idle', {
        detail: { source: String(source || 'field') },
    }));
}

export {
    flowdeskSpeechLocale,
    flowdeskApplyVoiceDictation,
    flowdeskIsVoiceClearCommand,
    flowdeskIsVoiceSubmitCommand,
    flowdeskIsVoiceSaveCommand,
    flowdeskIsVoiceGenerateCommand,
    flowdeskParseVoiceWithSubmit,
};
