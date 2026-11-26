import {
    flowdeskIsVoiceLocaleSupported,
    flowdeskMatchesIdentityPhrase,
    flowdeskMatchesStopPhrase,
    flowdeskMatchesWakePhrase,
    flowdeskNormalizeVoicePhrase,
    flowdeskNotifyVoiceIdle,
    flowdeskNovaIdentityPhrases,
    flowdeskNovaFillerPhrases,
    flowdeskMatchesFillerPhrase,
    flowdeskNovaStartListeningPhrases,
    flowdeskNovaStopListeningPhrases,
    flowdeskNovaStopPhrases,
    flowdeskAcquireVoiceMicStream,
    flowdeskAttachMicToSpeechRecognition,
    flowdeskConfigureSpeechRecognition,
    flowdeskParseSpeechRecognitionEvent,
    flowdeskReleaseVoiceMicStream,
    flowdeskSpeechLocale,
    flowdeskSpeechLocaleFallbacks,
    flowdeskVellisWakePhrases,
} from './ai-voice';
import { flowdeskFetchErrorMessage, flowdeskNotifyLabels, flowdeskSanitizeNotifyMessage } from './flowdesk-notify';
import { flowdeskSyncAiCreditsFromJson } from './flowdesk-ai-credits';
import {
    buildBriefingIndex,
    buildCommandIndex,
    buildWorkflowIndex,
    commandQueryVariants,
    isPartialBriefingMatch,
    matchesDirectCommand,
    resolveBestPhraseMatch,
    resolveCommandIntent,
    shouldExecuteCommand,
    stripWakeFromText,
} from './nova-voice-matching';
import {
    speakNovaVoiceLine,
    flowdeskNovaMarkWakeGreeted,
    flowdeskNovaResolveWakeReply,
    flowdeskNovaWakeReplyStorageKey,
    flowdeskStartNovaThinkingSound,
    flowdeskStopNovaThinkingSound,
} from './nova-voice-speak';
import {
    flowdeskPlayNovaBriefing,
    flowdeskPrefetchNovaSpeech,
    flowdeskHasPendingNovaSpeechForNavigation,
    flowdeskPersistNovaSpeechForNavigation,
    flowdeskResumeNovaSpeechAfterNavigation,
    flowdeskGetActiveNovaSpeechText,
    flowdeskSpeakNovaText,
    flowdeskStopNovaSpeech,
    flowdeskUnlockNovaAudio,
} from './flowdesk-nova-speech';

const NOVA_COMMAND_WINDOW_MS = 90000;
const NOVA_COMMAND_MODE_STORAGE_KEY = 'flowdesk_nova_command_mode_until';

export function registerNovaVoiceNav(Alpine) {
    Alpine.data('novaVoiceNav', (cfg = {}) => ({
        enabled: Boolean(cfg.enabled),
        ttsEnabled: cfg.ttsEnabled === true,
        chatEnabled: cfg.chatEnabled === true,
        brand: cfg.brand || 'Nova',
        userName: cfg.userName || '',
        userId: cfg.userId || '',
        companyName: cfg.companyName || '',
        voiceCreditCost: cfg.voiceCreditCost || 0,
        appLocale: cfg.appLocale || 'en',
        speechLocale: cfg.speechLocale || flowdeskSpeechLocale(cfg.appLocale),
        speakUrl: cfg.ttsEnabled === true ? (cfg.speakUrl || null) : null,
        chatUrl: cfg.chatEnabled === true ? (cfg.chatUrl || null) : null,
        intentUrl: cfg.intentUrl || null,
        chatCreditCost: cfg.chatCreditCost || 0,
        briefingUrl: cfg.ttsEnabled === true ? (cfg.briefingUrl || null) : null,
        briefingRedirectUrl: cfg.briefingRedirectUrl || null,
        briefingPhrases: cfg.briefingPhrases || [],
        briefingCreditCost: cfg.briefingCreditCost || 0,
        workflows: cfg.workflows || [],
        workflowUrl: cfg.workflowUrl || null,
        liveSessionUrl: null,
        livePhrases: [],
        commands: cfg.commands || [],
        labels: cfg.labels || {},
        supported: false,
        active: true,
        commandMode: false,
        commandModeUntil: 0,
        heard: '',
        status: '',
        error: '',
        showWakeTooltip: false,
        wakeTooltipText: '',
        showSpeakingTooltip: false,
        speakingTooltipText: '',
        creditsHint: '',
        recognition: null,
        recognitionActive: false,
        synthesis: typeof window !== 'undefined' ? window.speechSynthesis : null,
        wakePhrases: [],
        stopPhrases: [],
        stopListeningPhrases: [],
        startListeningPhrases: [],
        identityPhrases: [],
        fillerPhrases: [],
        commandIndex: { rows: [], exact: new Map() },
        briefingIndex: { rows: [], exact: new Map() },
        workflowIndex: { rows: [], exact: new Map() },
        _paused: false,
        _listeningPaused: false,
        /** Sources holding the mic for textarea AI dictation / generate (Nova stays quiet). */
        _fieldVoiceHolders: null,
        _speaking: false,
        _briefingLoading: false,
        _wakeAcked: false,
        _listenForStop: false,
        _chatAbort: null,
        _restartTimer: null,
        _audioEl: null,
        _csrf: '',
        _lastHandledKey: '',
        _lastHandledAt: 0,
        _wakePrefetchHelloStarted: false,
        _wakePrefetchListeningStarted: false,
        _identityPrefetchStarted: false,
        _takeYourTimePrefetchStarted: false,
        _lastWakeSpokenAt: 0,
        _lastFillerSpokenAt: 0,
        wakeReplyStorageKey: '',
        _chatLoading: false,
        _workflowActive: false,
        _workflowLoading: false,
        conversationId: null,
        _voiceActionsMutedUntil: 0,
        _micStream: null,
        _micStarting: false,
        _needsMicGesture: false,
        _committedResultIndex: 0,
        _lastResultsLength: 0,
        _interimCommitTimer: null,
        _pendingInterimHeard: '',
        _triedCloudRecognition: false,

        _lastWorkflowAdvanceKey: '',
        _lastWorkflowAdvanceAt: 0,

        get showStopButton() {
            return this._speaking || this._chatLoading || this._briefingLoading || this._workflowLoading;
        },

        init() {
            if (!this.enabled || !flowdeskIsVoiceLocaleSupported(this.appLocale) || !this.speechLocale) {
                return;
            }
            this.watchNovaVoiceSwitch();

            const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (!SpeechRecognition) {
                return;
            }

            this._csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            this.commandIndex = buildCommandIndex(this.commands);
            this.briefingIndex = buildBriefingIndex(this.briefingPhrases);
            this.workflowIndex = buildWorkflowIndex(this.workflows);
            this.wakePhrases = flowdeskVellisWakePhrases(this.brand, this.brand, this.appLocale);
            this.stopPhrases = flowdeskNovaStopPhrases(this.brand, this.appLocale);
            this.stopListeningPhrases = flowdeskNovaStopListeningPhrases(this.brand, this.appLocale);
            this.startListeningPhrases = flowdeskNovaStartListeningPhrases(this.brand, this.appLocale);
            this.identityPhrases = flowdeskNovaIdentityPhrases(this.appLocale);
            this.fillerPhrases = flowdeskNovaFillerPhrases(this.appLocale);
            this.creditsHint = this.labels.creditsHint || '';
            this.wakeReplyStorageKey = flowdeskNovaWakeReplyStorageKey(this.userId || this.userName);
            this.supported = true;
            this.status = this.labels.alwaysOn || this.labels.wake || '';
            this.restoreCommandMode();
            this._audioEl = typeof Audio !== 'undefined' ? new Audio() : null;

            this.recognition = new SpeechRecognition();
            this.recognition.lang = this.speechLocale;
            this._speechLocaleFallbacks = flowdeskSpeechLocaleFallbacks(this.appLocale);
            flowdeskConfigureSpeechRecognition(this.recognition);

            this.recognition.onresult = (event) => {
                const parsed = flowdeskParseSpeechRecognitionEvent(event, this._committedResultIndex);
                this._lastResultsLength = parsed.resultsLength;
                const isFinal = parsed.isFinal;
                const heard = this.pickBestHeardTranscript(parsed.heard, parsed.alternatives, isFinal);

                if (!heard) {
                    return;
                }

                // While Nova is speaking: keep listening only for “stop Nova” or a clear topic change.
                // Do not run SaaS actions from speaker echo.
                if (this._speaking) {
                    this.handleSpeechWhileSpeaking(heard, isFinal);
                    if (isFinal) {
                        this.commitSpeechResult();
                    }
                    return;
                }

                // Brief echo mute after TTS — still allow stop phrases.
                if (this.voiceActionsMuted()) {
                    if (flowdeskMatchesStopPhrase(heard, this.stopPhrases)) {
                        this.stopNovaResponse();
                        if (isFinal) {
                            this.commitSpeechResult();
                        }
                        return;
                    }
                    this.heard = '';
                    this.clearInterimCommit();
                    return;
                }

                this.heard = heard;

                if (!isFinal) {
                    this.scheduleInterimCommit(heard);
                    if (!this._shouldProcessInterim(heard)) {
                        return;
                    }
                } else {
                    this.clearInterimCommit();
                }

                this.handleSpeech(heard, isFinal);

                if (isFinal) {
                    this.commitSpeechResult();
                }
            };

            this.recognition.onerror = (event) => {
                const code = event?.error || '';
                if (code === 'not-allowed') {
                    this.error = this.labels.unsupported || 'Microphone denied.';
                    this.active = false;
                    this.releaseVoiceMic();
                    return;
                }
                if (code === 'language-not-supported') {
                    if (this.recognition?.processLocally) {
                        this.recognition.processLocally = false;
                        this._triedCloudRecognition = true;
                        this.error = '';
                        this.scheduleRestart(100);

                        return;
                    }

                    const index = this._speechLocaleFallbacks.indexOf(this.speechLocale);
                    const next = this._speechLocaleFallbacks[index + 1];
                    if (next) {
                        this.speechLocale = next;
                        this.recognition.lang = next;
                        this.error = '';
                        this.scheduleRestart(100);

                        return;
                    }

                    this.error = this.labels.localeUnsupported || this.labels.unsupported || 'Voice language not supported in this browser.';
                    this.scheduleRestart(1500);

                    return;
                }
                if (code === 'aborted' || code === 'no-speech') {
                    return;
                }
                if (code === 'network') {
                    this.scheduleRestart(1500);

                    return;
                }
                this.error = this.labels.unsupported || code;
                this.scheduleRestart(800);
            };

            this.recognition.onend = () => {
                this.recognitionActive = false;
                if (!this.active || this._paused || this._listeningPaused) {
                    return;
                }
                // Only keep the mic alive during TTS when barge-in is explicitly enabled.
                if (this._speaking) {
                    if (this._listenForStop) {
                        this.scheduleRestart(80);
                    }
                    return;
                }
                if (!this.voiceActionsMuted()) {
                    this.scheduleRestart(40);
                }
            };

            document.addEventListener('flowdesk-voice-busy', (event) => {
                this.holdMicForFieldVoice(event?.detail?.source || 'field');
            });
            document.addEventListener('flowdesk-voice-idle', (event) => {
                this.releaseMicFromFieldVoice(event?.detail?.source || 'field');
            });

            this._onNovaStop = () => this.applyNovaStopState();
            document.addEventListener('flowdesk-nova-stop', this._onNovaStop);

            if (this.synthesis) {
                this.synthesis.getVoices();
            }

            window.flowdeskNovaVoiceNav = this;
            this.prefetchVoiceLines();
            // Mobile Chrome requires a user gesture before getUserMedia / SpeechRecognition.
            // Do not open the mic before pending post-navigation TTS finishes (prevents echo loops).
            const pendingNavSpeech = this.ttsEnabled && flowdeskHasPendingNovaSpeechForNavigation();
            if (this.restoreListeningPaused()) {
                this._listeningPaused = true;
                this.status = this.labels.notListening || '';
            } else if (!this.canAutoStartListening()) {
                this._needsMicGesture = true;
                this.status = this.labels.tapToListen || this.labels.notListening || this.labels.wake || '';
            } else if (!pendingNavSpeech) {
                this.ensureListening();
            }
            window.setTimeout(() => {
                void this.resumeSpeechAfterNavigation();
            }, 120);

            const cleanup = () => {
                this.active = false;
                if (this._restartTimer) {
                    window.clearTimeout(this._restartTimer);
                }
                this.clearInterimCommit();
                this.synthesis?.cancel();
                this.stopRecognition(true);
                this.releaseVoiceMic();
                document.removeEventListener('flowdesk-nova-stop', this._onNovaStop);
                if (window.flowdeskNovaVoiceNav === this) {
                    window.flowdeskNovaVoiceNav = null;
                }
            };
            if (typeof this.$cleanup === 'function') {
                this.$cleanup(cleanup);
            } else {
                this.$el.addEventListener('alpine:destroyed', cleanup, { once: true });
            }
        },

        async resumeSpeechAfterNavigation() {
            if (!this.ttsEnabled) {
                this.ensureListening();
                return;
            }

            const played = await flowdeskResumeNovaSpeechAfterNavigation({
                synthesis: this.synthesis,
                browserFallbackHint: this.labels.browserFallback || '',
                onNotice: (message) => this.showSpeakNotice(message),
                onStart: () => {
                    this._speaking = true;
                    this.heard = '';
                    this.beginStopListening();
                    this.muteVoiceActions(20000);
                    this.status = this.labels.answering || this.labels.navigating || this.status;
                },
                onEnd: () => {
                    this._speaking = false;
                    this._listenForStop = false;
                    this.clearSpokenTooltip();
                    this.openCommandWindow();
                    this.resumeListeningAfterMute(900);
                },
            });

            if (played) {
                this.showSpokenTooltip(this.status || this.labels.navigating || '');
            } else if (this.canAutoStartListening() && !this.recognitionActive) {
                this.ensureListening();
            }
        },

        /**
         * Hard-mute echo window after TTS, then reopen the mic.
         */
        resumeListeningAfterMute(delayMs = 1800) {
            const wait = Math.max(400, Number(delayMs) || 0);
            this._voiceActionsMutedUntil = Date.now() + wait;
            window.setTimeout(() => {
                if (this._speaking || this._chatLoading || this._briefingLoading || this._listeningPaused || this._paused) {
                    return;
                }
                this.forceEnsureListening();
            }, wait + 80);
        },

        isAlreadyOnTargetUrl(url) {
            const target = String(url || '').trim();
            if (!target || typeof window === 'undefined') {
                return false;
            }
            try {
                const next = new URL(target, window.location.origin);
                const cur = new URL(window.location.href);
                return next.pathname === cur.pathname && next.search === cur.search;
            } catch {
                return false;
            }
        },

        _shouldProcessInterim(heard) {
            if (this._workflowActive || this._listeningPaused) {
                return true;
            }
            if (this.commandMode && Date.now() < this.commandModeUntil) {
                return true;
            }
            if (flowdeskMatchesWakePhrase(heard, this.wakePhrases)) {
                return true;
            }
            if (flowdeskMatchesFillerPhrase(heard, this.fillerPhrases)) {
                return true;
            }
            if (flowdeskMatchesStopPhrase(heard, this.stopListeningPhrases)) {
                return true;
            }
            if (flowdeskMatchesStopPhrase(heard, this.startListeningPhrases)) {
                return true;
            }

            const normalized = flowdeskNormalizeVoicePhrase(heard);
            return matchesDirectCommand(normalized, this.commandIndex);
        },

        commitSpeechResult() {
            this._committedResultIndex = this._lastResultsLength;
            this._pendingInterimHeard = '';
        },

        clearInterimCommit() {
            if (this._interimCommitTimer) {
                window.clearTimeout(this._interimCommitTimer);
                this._interimCommitTimer = null;
            }
            this._pendingInterimHeard = '';
        },

        scheduleInterimCommit(heard) {
            const inWorkflow = this._workflowActive && !this._workflowLoading;
            const inCommandWindow = this.commandMode && Date.now() < this.commandModeUntil && !this._chatLoading;
            const readyCommand = this.shouldExecuteCommand(heard, false);

            // Auto-finalize stable interim for clear commands (don't wait on Chrome's slow "final").
            if (!inWorkflow && !inCommandWindow && !readyCommand) {
                return;
            }

            this._pendingInterimHeard = heard;
            if (this._interimCommitTimer) {
                window.clearTimeout(this._interimCommitTimer);
            }

            this._interimCommitTimer = window.setTimeout(() => {
                this._interimCommitTimer = null;
                if (this._pendingInterimHeard !== heard || !heard.trim()) {
                    return;
                }
                if (this._speaking || this._workflowLoading || this._chatLoading || this.voiceActionsMuted()) {
                    return;
                }

                this.commitSpeechResult();
                this.handleSpeech(heard, true);
            }, readyCommand ? 90 : 160);
        },

        openCommandWindow(ms = NOVA_COMMAND_WINDOW_MS) {
            this.commandMode = true;
            this.commandModeUntil = Date.now() + Math.max(5000, Number(ms) || NOVA_COMMAND_WINDOW_MS);
            this._wakeAcked = true;
            this.status = this.labels.chatListening || this.labels.listening || this.labels.alwaysOn || '';
            this.persistCommandMode();
        },

        persistCommandMode() {
            if (typeof window === 'undefined' || !window.sessionStorage) {
                return;
            }
            try {
                if (this.commandMode && this.commandModeUntil > Date.now()) {
                    window.sessionStorage.setItem(NOVA_COMMAND_MODE_STORAGE_KEY, String(this.commandModeUntil));
                } else {
                    window.sessionStorage.removeItem(NOVA_COMMAND_MODE_STORAGE_KEY);
                }
            } catch {
                // ignore quota / private mode
            }
        },

        restoreCommandMode() {
            if (typeof window === 'undefined' || !window.sessionStorage) {
                return false;
            }
            try {
                const until = Number(window.sessionStorage.getItem(NOVA_COMMAND_MODE_STORAGE_KEY) || 0);
                if (!Number.isFinite(until) || until <= Date.now()) {
                    window.sessionStorage.removeItem(NOVA_COMMAND_MODE_STORAGE_KEY);

                    return false;
                }
                this.commandMode = true;
                this.commandModeUntil = until;
                this._wakeAcked = true;
                this.status = this.labels.chatListening || this.labels.listening || this.labels.alwaysOn || '';

                return true;
            } catch {
                return false;
            }
        },

        clearCommandMode() {
            this.commandMode = false;
            this.commandModeUntil = 0;
            this._wakeAcked = false;
            this.persistCommandMode();
        },

        /**
         * Prefer the ASR alternative that best matches a known Nova command.
         */
        pickBestHeardTranscript(primary, alternatives = [], isFinal = false) {
            const list = [];
            const pushUnique = (value) => {
                const text = String(value || '').trim();
                if (!text) {
                    return;
                }
                const key = text.toLowerCase();
                if (list.some((item) => item.toLowerCase() === key)) {
                    return;
                }
                list.push(text);
            };

            pushUnique(primary);
            (alternatives || []).forEach(pushUnique);

            if (list.length === 0) {
                return '';
            }
            if (list.length === 1) {
                return list[0];
            }

            let best = list[0];
            let bestScore = 0;
            for (const text of list) {
                const intent = resolveCommandIntent(text, this.wakePhrases, {
                    briefing: this.briefingIndex,
                    workflow: this.workflowIndex,
                    command: this.commandIndex,
                }, { isFinal: true });
                const score = Math.max(
                    intent.briefing?.score || 0,
                    intent.workflow?.score || 0,
                    intent.command?.score || 0,
                );
                if (score > bestScore) {
                    bestScore = score;
                    best = text;
                }
            }

            return bestScore >= 7500 ? best : (String(primary || '').trim() || best);
        },

        restartRecognitionSession(delayMs = 30) {
            this.clearInterimCommit();
            this._committedResultIndex = 0;
            this._lastResultsLength = 0;
            this.heard = '';
            this.stopRecognition(false);
            this.scheduleRestart(delayMs);
        },

        unlockAudio() {
            this._needsMicGesture = false;
            flowdeskUnlockNovaAudio();
            if (this._audioEl) {
                this._audioEl.src = '';
                this._audioEl.play().catch(() => {});
            }
            if (this.synthesis && this.synthesis.getVoices().length === 0) {
                this.synthesis.getVoices();
            }
            this.error = '';
            if (this._listeningPaused) {
                this.resumeListening();
            } else {
                this.ensureListening();
            }
            this.prefetchVoiceLines();
        },

        toggleMic() {
            flowdeskUnlockNovaAudio();
            if (this._listeningPaused) {
                this._needsMicGesture = false;
                this.persistListeningPaused(false);
                this.resumeListening(true);
                return;
            }
            // First tap on mobile (no gesture yet) starts listening; otherwise a tap turns listening off.
            if (this._needsMicGesture || !this.recognitionActive && !this._micStarting && !this._restartTimer) {
                this._needsMicGesture = false;
                this.unlockAudio();
                return;
            }
            this.pauseListening();
        },

        /**
         * Admin or company can switch Nova voice off while this page is open: stop the mic when a voice
         * endpoint answers 403 nova_voice_disabled, and re-check the status when the tab regains focus.
         */
        watchNovaVoiceSwitch() {
            if (!window.__cfNovaFetchWrapped && typeof window.fetch === 'function') {
                window.__cfNovaFetchWrapped = true;
                const originalFetch = window.fetch.bind(window);
                window.fetch = async (...args) => {
                    const res = await originalFetch(...args);
                    if (res && res.status === 403) {
                        res.clone().json().then((data) => {
                            if (data && data.error === 'nova_voice_disabled') {
                                window.dispatchEvent(new CustomEvent('cf-nova-voice-disabled'));
                            }
                        }).catch(() => {});
                    }

                    return res;
                };
            }
            window.addEventListener('cf-nova-voice-disabled', () => this.shutdownNovaVoice());
            const statusUrl = window.flowdeskNovaVoice?.statusUrl || null;
            if (!statusUrl) {
                return;
            }
            let lastCheck = 0;
            const check = async () => {
                if (document.visibilityState === 'hidden' || Date.now() - lastCheck < 5000) {
                    return;
                }
                lastCheck = Date.now();
                try {
                    const r = await fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                    const data = await r.json().catch(() => ({}));
                    if (r.ok && data && data.enabled === false) {
                        this.shutdownNovaVoice();
                    }
                } catch {
                    // offline: keep current state
                }
            };
            window.addEventListener('focus', check);
            document.addEventListener('visibilitychange', check);
        },

        shutdownNovaVoice() {
            if (window.flowdeskNovaVoice) {
                window.flowdeskNovaVoice.enabled = false;
            }
            try {
                window.clearTimeout(this._restartTimer);
            } catch {
                // ignore
            }
            this._restartTimer = null;
            this.active = false;
            this.stopRecognition(false);
            this.releaseVoiceMic();
            this.supported = false;
            this.enabled = false;
            this.status = '';
            // Forget the per-user pause so the mic listens again once Nova is re-enabled.
            this.persistListeningPaused(false);
            this.$root?.classList?.add('hidden');
        },

        listeningPausedStorageKey() {
            return 'flowdesk.nova.listeningPaused.' + String(this.userId || this.userName || 'me');
        },

        persistListeningPaused(paused) {
            try {
                if (paused) {
                    window.localStorage.setItem(this.listeningPausedStorageKey(), '1');
                } else {
                    window.localStorage.removeItem(this.listeningPausedStorageKey());
                }
            } catch {
                // ignore
            }
        },

        restoreListeningPaused() {
            try {
                return window.localStorage.getItem(this.listeningPausedStorageKey()) === '1';
            } catch {
                return false;
            }
        },

        canAutoStartListening() {
            try {
                if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
                    return false;
                }
            } catch {
                // ignore
            }

            return ! /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent || '');
        },

        pauseListening() {
            if (this._listeningPaused) {
                return;
            }

            this.stopNovaResponse();
            this._listeningPaused = true;
            this.persistListeningPaused(true);
            this.clearCommandMode();
            this.heard = '';
            this.showWakeTooltip = false;
            this.status = this.labels.notListening || '';
            if (this._restartTimer) {
                window.clearTimeout(this._restartTimer);
                this._restartTimer = null;
            }
            // Really stop the recognizer: no wake word, no commands until the user taps the mic again.
            this.stopRecognition(false);
            this.releaseVoiceMic();
            this.speakNotListening();
        },

        /**
         * Quiet pause while a form textarea mic or AI generate uses the microphone.
         */
        holdMicForFieldVoice(source = 'field') {
            if (!this._fieldVoiceHolders) {
                this._fieldVoiceHolders = new Set();
            }
            const key = String(source || 'field');
            const wasEmpty = this._fieldVoiceHolders.size === 0;
            this._fieldVoiceHolders.add(key);
            if (!wasEmpty && this._paused) {
                return;
            }

            this._paused = true;
            this.clearInterimCommit?.();
            this.heard = '';
            this.showWakeTooltip = false;
            if (this._restartTimer) {
                window.clearTimeout(this._restartTimer);
                this._restartTimer = null;
            }
            this.stopRecognition(false);
            this.releaseVoiceMic();
            this.status = this.labels.notListening || this.labels.tapToListen || '';
        },

        releaseMicFromFieldVoice(source = 'field') {
            if (!this._fieldVoiceHolders) {
                this._fieldVoiceHolders = new Set();
            }
            this._fieldVoiceHolders.delete(String(source || 'field'));
            if (this._fieldVoiceHolders.size > 0) {
                return;
            }

            this._paused = false;
            this.error = '';
            if (!this.active || this._listeningPaused || this._speaking || this.voiceActionsMuted() || this._needsMicGesture) {
                return;
            }
            this.status = this.labels.alwaysOn || this.labels.wake || '';
            // Small delay so the form SpeechRecognition can fully release the mic.
            this.scheduleRestart(280);
        },

        resumeListening(fromMic = false) {
            if (!this._listeningPaused) {
                return;
            }

            this._listeningPaused = false;
            this.error = '';
            this.heard = '';
            this.status = this.labels.alwaysOn || this.labels.wake || '';
            this.ensureListening();

            if (fromMic && (this.labels.listeningAgain || '').trim()) {
                this.speakListeningAgain();
            }
        },

        prefetchVoiceLines() {
            if (!this.ttsEnabled || !this.speakUrl) {
                return;
            }

            this.prefetchSpeechLine(this.labels.wakeReplyHello, '_wakePrefetchHelloStarted');
            this.prefetchSpeechLine(this.labels.wakeReplyListening, '_wakePrefetchListeningStarted');
            this.prefetchSpeechLine(this.labels.identityReply, '_identityPrefetchStarted');
            this.prefetchSpeechLine(this.labels.takeYourTime, '_takeYourTimePrefetchStarted');
        },

        prefetchSpeechLine(text, flagName) {
            const value = String(text || '').trim();
            if (!value || !this.speakUrl || this[flagName]) {
                return;
            }
            this[flagName] = true;
            flowdeskPrefetchNovaSpeech({ text: value, speakUrl: this.speakUrl }).catch(() => {});
        },

        voiceActionsMuted() {
            return Date.now() < this._voiceActionsMutedUntil;
        },

        muteVoiceActions(durationMs = 3000) {
            this._voiceActionsMutedUntil = Math.max(
                this._voiceActionsMutedUntil,
                Date.now() + Math.max(0, durationMs),
            );
        },

        handleSpeech(raw, isFinal) {
            if (flowdeskMatchesStopPhrase(raw, this.stopListeningPhrases)) {
                this.pauseListening();
                return;
            }

            if (this._listeningPaused) {
                const fromStartPhrase = flowdeskMatchesStopPhrase(raw, this.startListeningPhrases);
                const fromWake = flowdeskMatchesWakePhrase(raw, this.wakePhrases);
                if (!fromStartPhrase && !fromWake) {
                    return;
                }
                this._listeningPaused = false;
                this.error = '';
                this.status = this.labels.alwaysOn || this.labels.wake || '';
                if (fromStartPhrase && !fromWake) {
                    if ((this.labels.listeningAgain || '').trim()) {
                        this.speakListeningAgain();
                    } else {
                        this.forceEnsureListening();
                    }
                    return;
                }
            }

            // Hesitation (“humm” / “euh”) — reassure and wait; do not run nav/chat/workflow.
            if (flowdeskMatchesFillerPhrase(raw, this.fillerPhrases)) {
                if (isFinal || this.shouldAcknowledgeFiller(raw)) {
                    this.speakTakeYourTime();
                }
                return;
            }

            if (this._workflowActive) {
                if (isFinal) {
                    const key = raw.trim().toLowerCase();
                    if (key === this._lastWorkflowAdvanceKey && Date.now() - this._lastWorkflowAdvanceAt < 3000) {
                        return;
                    }
                    this._lastWorkflowAdvanceKey = key;
                    this._lastWorkflowAdvanceAt = Date.now();
                    this.advanceWorkflow(raw);
                } else {
                    this.status = this.labels.workflowListening || this.labels.chatListening || '';
                }
                return;
            }

            if (flowdeskMatchesStopPhrase(raw, this.stopPhrases)) {
                this.stopNovaResponse();
                return;
            }

            const hasWake = flowdeskMatchesWakePhrase(raw, this.wakePhrases);
            const afterWake = hasWake ? stripWakeFromText(raw, this.wakePhrases) : '';

            // “Nova” alone = wake only (no SaaS action). Wait for the next command.
            if (hasWake && !afterWake && isFinal && !this._listeningPaused && !this._workflowActive) {
                if (this.handleWakeOnly()) {
                    return;
                }
            }

            if (isFinal && this.matchesIdentityQuestion(raw)) {
                if (this.ttsEnabled) {
                    this.speakIdentityReply();
                }
                return;
            }

            // While TTS is playing, never navigate / open pages — barge-in is handled separately.
            if (this._speaking || this._chatLoading || this._briefingLoading || this._workflowLoading) {
                return;
            }

            if (this.voiceActionsMuted()) {
                return;
            }

            if (Date.now() > this.commandModeUntil) {
                this.clearCommandMode();
            }

            const normalized = flowdeskNormalizeVoicePhrase(raw);
            if (!normalized) {
                return;
            }

            const inCommandWindow = this.commandMode && Date.now() < this.commandModeUntil;

            if (hasWake) {
                this.openCommandWindow();

                if (afterWake && this.matchesIdentityQuestion(afterWake) && isFinal) {
                    if (this.ttsEnabled) {
                        this.speakIdentityReply();
                    }
                    this.restartRecognitionSession(40);
                    return;
                }

                if (afterWake) {
                    // Wake + request: run nav/workflow when clear, otherwise chat so Nova understands free speech.
                    if (isFinal || this.shouldExecuteCommand(afterWake, isFinal)) {
                        this.tryHandleCommand(afterWake, isFinal);
                        if (isFinal) {
                            this.restartRecognitionSession(40);
                        }
                    }
                    return;
                }

                if (!isFinal) {
                    return;
                }

                return;
            }

            if (inCommandWindow) {
                const commandText = stripWakeFromText(raw, this.wakePhrases);
                if (isFinal) {
                    this.tryHandleCommand(commandText, true);
                } else if (this.shouldExecuteCommand(commandText, false)) {
                    this.tryHandleCommand(commandText, false);
                } else {
                    this.status = this.labels.chatListening || this.labels.listening || '';
                }
                return;
            }

            // Clear nav / workflow phrases can run without saying “Nova” again.
            if (matchesDirectCommand(normalized, this.commandIndex) || this.matchesBriefing(normalized)) {
                const commandText = stripWakeFromText(raw, this.wakePhrases);
                if (this.shouldExecuteCommand(commandText, isFinal)) {
                    this.openCommandWindow();
                    this.tryHandleCommand(commandText, isFinal);
                } else {
                    this.status = this.labels.chatListening || this.labels.listening || '';
                }
            }
        },

        /**
         * During TTS: only stop Nova, or interrupt and switch topic — never navigate from echo.
         */
        handleSpeechWhileSpeaking(heard, isFinal) {
            const raw = String(heard || '').trim();
            if (!raw) {
                return;
            }

            if (flowdeskMatchesStopPhrase(raw, this.stopPhrases)) {
                this.stopNovaResponse();
                return;
            }

            if (!isFinal) {
                return;
            }

            if (this.isLikelySpeechEcho(raw)) {
                return;
            }

            const hasWake = flowdeskMatchesWakePhrase(raw, this.wakePhrases);
            const afterWake = hasWake ? stripWakeFromText(raw, this.wakePhrases) : '';
            const commandText = (afterWake || raw).trim();

            // Wake alone while speaking: just stop TTS and stay in command mode.
            if (hasWake && !afterWake) {
                flowdeskStopNovaSpeech(this.synthesis);
                this._speaking = false;
                this._listenForStop = false;
                this.openCommandWindow();
                this.clearSpokenTooltip();
                this.forceEnsureListening();
                return;
            }

            // Clear topic change / command: stop speaking, then run the new request.
            if (commandText.length < 3) {
                return;
            }

            const looksLikeCommand = this.shouldExecuteCommand(commandText, true)
                || matchesDirectCommand(flowdeskNormalizeVoicePhrase(commandText), this.commandIndex)
                || hasWake;

            if (!looksLikeCommand && commandText.length < 8) {
                return;
            }

            flowdeskStopNovaSpeech(this.synthesis);
            this._speaking = false;
            this._listenForStop = false;
            this._chatLoading = false;
            this._briefingLoading = false;
            this.clearSpokenTooltip();
            this.openCommandWindow();
            this._voiceActionsMutedUntil = 0;
            this.forceEnsureListening();
            this.tryHandleCommand(commandText, true);
        },

        isLikelySpeechEcho(heard) {
            const spoken = flowdeskNormalizeVoicePhrase(this.speakingTooltipText || '');
            const h = flowdeskNormalizeVoicePhrase(heard);
            if (!spoken || !h || h.length < 3) {
                return false;
            }
            if (spoken.includes(h) || h.includes(spoken.slice(0, Math.min(48, spoken.length)))) {
                return true;
            }
            // Overlap of significant words with the line Nova is reading.
            const spokenWords = spoken.split(/\s+/).filter((w) => w.length > 3);
            const heardWords = h.split(/\s+/).filter((w) => w.length > 3);
            if (spokenWords.length === 0 || heardWords.length === 0) {
                return false;
            }
            const hits = heardWords.filter((w) => spokenWords.includes(w)).length;
            return hits >= Math.ceil(heardWords.length * 0.6);
        },

        shouldExecuteCommand(text, isFinal) {
            return shouldExecuteCommand(text, isFinal, this.briefingIndex, this.commandIndex);
        },

        matchesBriefing(normalized) {
            return Boolean(resolveBestPhraseMatch(normalized, this.wakePhrases, this.briefingIndex, { fromNormalized: true }));
        },

        matchesIdentityQuestion(text) {
            return flowdeskMatchesIdentityPhrase(text, this.identityPhrases);
        },

        tryHandleCommand(text, isFinal = true) {
            const question = stripWakeFromText(text, this.wakePhrases).trim();

            // "supprimer client Dupont" / "edit client 0612…" / "delete quote DEV-01"
            // must resolve via intelligent intent (record URL), not generic list phrases.
            if (isFinal && question.length >= 5 && this.looksLikeTargetedEntityAction(question)) {
                const handleKey = `entity|${question}`;
                if (handleKey === this._lastHandledKey && Date.now() - this._lastHandledAt < 2000) {
                    return;
                }
                this._lastHandledKey = handleKey;
                this._lastHandledAt = Date.now();
                this.openCommandWindow();
                void this.resolveIntentOrChat(question);
                return;
            }

            const intent = resolveCommandIntent(text, this.wakePhrases, {
                briefing: this.briefingIndex,
                workflow: this.workflowIndex,
                command: this.commandIndex,
            }, { isFinal });

            const { variants, briefing, workflow, command } = intent;
            if (variants.length === 0) {
                if (isFinal) {
                    if (question.length >= 3) {
                        this.openCommandWindow();
                        void this.resolveIntentOrChat(question);
                    }
                }
                return;
            }

            const handleKey = variants.join('|');
            if (handleKey === this._lastHandledKey && Date.now() - this._lastHandledAt < 2000) {
                return;
            }

            if (briefing && briefing.score >= 5000) {
                if (!this.ttsEnabled) {
                    return;
                }
                this._lastHandledKey = handleKey;
                this._lastHandledAt = Date.now();
                this.playBriefing();
                return;
            }

            if (!isFinal) {
                for (const query of variants) {
                    if (isPartialBriefingMatch(query, this.briefingIndex)) {
                        return;
                    }
                }
            }

            // Strong matches only — weak hits like “clients” inside a question should go to chat.
            // Prefer the higher score so “new project” (command) beats “new event” (workflow).
            const workflowScore = workflow?.score || 0;
            const commandScore = command?.score || 0;
            const strongWorkflow = workflowScore >= 7500;
            const strongCommand = commandScore >= 7500;

            if (strongWorkflow && workflowScore >= commandScore) {
                // Near-exact fuzzy matches can run on interim; wait only for weak partials.
                if (!isFinal && workflowScore < 8000) {
                    return;
                }

                this._lastHandledKey = handleKey;
                this._lastHandledAt = Date.now();
                this.openCommandWindow();
                this.startWorkflow(workflow.cmd.id);
                return;
            }

            if (strongCommand) {
                if (!isFinal && commandScore < 8000) {
                    return;
                }

                this._lastHandledKey = handleKey;
                this._lastHandledAt = Date.now();
                this.openCommandWindow();
                if (command.cmd?.workflow) {
                    this.startWorkflow(command.cmd.workflow, command.cmd.workflow_seed || {});
                    return;
                }
                this.navigateToMatch(command);
                return;
            }

            if (isFinal) {
                this.openCommandWindow();
                if (question.length >= 3 && this.matchesIdentityQuestion(question)) {
                    if (this.ttsEnabled) {
                        this.speakIdentityReply();
                    }
                    return;
                }
                if (question.length >= 3) {
                    this._lastHandledKey = handleKey;
                    this._lastHandledAt = Date.now();
                    void this.resolveIntentOrChat(question);
                    return;
                }

                if (Date.now() < this.commandModeUntil) {
                    this.error = this.labels.unknown || '';
                    window.setTimeout(() => {
                        this.error = '';
                    }, 3500);
                }
            }
        },

        looksLikeTargetedEntityAction(text) {
            const n = flowdeskNormalizeVoicePhrase(text);
            if (!n) {
                return false;
            }
            return /^(modifier|editer|edit|modify|update|changer|supprimer|effacer|delete|remove)(\s+(le|la|un|une|the|a|an))?(\s+(client|cliente|clients|devis|proposition|propositions|quote|quotes|proposal|proposals|facture|factures|invoice|invoices))(\s+(numero|number|ref|reference|nommé|nommee|named|appelé|appelee))?\s+\S+/u.test(n);
        },

        async resolveIntentOrChat(question) {
            const text = String(question || '').trim();
            if (text.length < 3) {
                return;
            }

            if (this.intentUrl) {
                try {
                    const res = await fetch(this.intentUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': this._csrf,
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ text }),
                        credentials: 'same-origin',
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok) {
                        if (data.type === 'speak' && data.reply) {
                            if (this.ttsEnabled) {
                                await this.speakWorkflowReply(String(data.reply));
                            }
                            return;
                        }
                        if ((data.type === 'workflow' || data.workflow) && (data.id || data.workflow)) {
                            const workflowId = data.workflow || data.id;
                            if (data.reply) {
                                this.openCommandWindow(120000);
                                this._workflowActive = !data.done;
                                if (this.ttsEnabled) {
                                    await this.speakWorkflowReply(String(data.reply));
                                }
                                this.status = this._workflowActive
                                    ? (this.labels.workflowListening || this.labels.chatListening || '')
                                    : (this.labels.chatListening || this.labels.alwaysOn || this.labels.wake || '');
                                return;
                            }
                            this.startWorkflow(workflowId, data.workflow_seed || {});
                            return;
                        }
                        if (data.type === 'command' && data.workflow) {
                            this.startWorkflow(data.workflow, data.workflow_seed || {});
                            return;
                        }
                        if (data.type === 'command' && (data.url || data.action)) {
                            this.navigateToMatch({
                                cmd: {
                                    id: data.id,
                                    label: data.label || data.id || '',
                                    url: data.url || '',
                                    action: data.action || null,
                                },
                            });
                            return;
                        }
                        if (data.type === 'workflow' && data.id) {
                            this.startWorkflow(data.id, data.workflow_seed || {});
                            return;
                        }
                        if (data.type === 'chat') {
                            if (this.chatUrl) {
                                this.askNovaChat(text);
                            }
                            return;
                        }
                    } else {
                        const message = flowdeskFetchErrorMessage(res, data, this.labels.requestFailed);
                        this.showSpeakNotice(message);
                        return;
                    }
                } catch {
                    // Fall through to chat.
                }
            }

            if (this.chatUrl) {
                this.askNovaChat(text);
            }
        },

        showSpeakNotice(message) {
            const text = flowdeskSanitizeNotifyMessage(
                message,
                flowdeskNotifyLabels().requestFailed || 'Request failed. Please try again.',
            );
            if (!text) {
                return;
            }
            if (typeof window.flowdeskNotify === 'function') {
                window.flowdeskNotify(text, { type: 'warning' });
            }
            this.error = text;
            if (this._speakNoticeTimer) {
                window.clearTimeout(this._speakNoticeTimer);
            }
            this._speakNoticeTimer = window.setTimeout(() => {
                this.error = '';
            }, 7000);
        },

        applyNovaStopState() {
            this._speaking = false;
            this._chatLoading = false;
            this._briefingLoading = false;
            this._listenForStop = false;
            this.clearCommandMode();
            this.clearSpokenTooltip();
            this.showWakeTooltip = false;
            if (this._chatAbort) {
                try {
                    this._chatAbort.abort();
                } catch {
                    // ignore
                }
                this._chatAbort = null;
            }
            this.status = this._listeningPaused
                ? (this.labels.notListening || '')
                : (this.labels.alwaysOn || this.labels.wake || '');
        },

        stopNovaResponse() {
            flowdeskStopNovaThinkingSound();
            flowdeskStopNovaSpeech(this.synthesis);
            if (this._workflowLoading) {
                this._workflowLoading = false;
            }
            if (this._workflowActive && this.workflowUrl) {
                this.postWorkflow({ action: 'cancel' }).catch(() => {});
                this._workflowActive = false;
                this.clearCommandMode();
            }
            this.applyNovaStopState();
            if (this._listeningPaused) {
                this.scheduleRestart(400);
            } else {
                this.forceEnsureListening();
            }
        },

        beginStopListening() {
            // Hard mute: used when barge-in is disabled.
            this._listenForStop = false;
            this.clearInterimCommit();
            this.heard = '';
            if (this._restartTimer) {
                window.clearTimeout(this._restartTimer);
                this._restartTimer = null;
            }
            this.stopRecognition(false);
        },

        /**
         * Keep the mic open while Nova speaks so the user can say “stop Nova” or change topic.
         * SaaS navigation stays blocked via `_speaking` / muted actions.
         */
        beginBargeInListening() {
            this._listenForStop = true;
            this.clearInterimCommit();
            this.heard = '';
            this.forceEnsureListening();
        },

        ensureListeningDuringSpeech() {
            this.beginBargeInListening();
        },

        /**
         * Resume mic even during/after TTS mute windows (avoids “click to listen again”).
         */
        forceEnsureListening() {
            if (!this.active || this._paused || this._listeningPaused || this._needsMicGesture) {
                return;
            }
            // Hard mute while Nova is talking (unless barge-in was enabled).
            if (this._speaking && !this._listenForStop) {
                return;
            }
            this.scheduleRestart(0);
        },

        ensureListening() {
            if (!this.active || this._paused || this._listeningPaused || this._speaking || this.voiceActionsMuted()) {
                return;
            }
            if (this._needsMicGesture) {
                return;
            }
            this.scheduleRestart(0);
        },

        scheduleRestart(delayMs) {
            if (this._restartTimer) {
                window.clearTimeout(this._restartTimer);
            }
            this._restartTimer = window.setTimeout(() => {
                this._restartTimer = null;
                void this.startRecognition();
            }, delayMs);
        },

        async ensureVoiceMic() {
            const stream = await flowdeskAcquireVoiceMicStream(this._micStream);
            if (!stream) {
                return false;
            }

            this._micStream = stream;
            flowdeskAttachMicToSpeechRecognition(this.recognition, stream);

            return true;
        },

        releaseVoiceMic() {
            flowdeskReleaseVoiceMicStream(this._micStream);
            this._micStream = null;
        },

        async startRecognition() {
            if (!this.recognition || this.recognitionActive || this._paused || this._listeningPaused || !this.active || this._micStarting) {
                return;
            }
            if (this._fieldVoiceHolders?.size) {
                return;
            }
            if (this._workflowLoading) {
                return;
            }
            // Hard mute while speaking unless barge-in (_listenForStop) is on.
            if (this._speaking && !this._listenForStop) {
                return;
            }
            // Soft mute after TTS: wait it out, then reopen the mic for navigation.
            if (!this._speaking && this.voiceActionsMuted()) {
                const wait = Math.max(60, this._voiceActionsMutedUntil - Date.now() + 40);
                this.scheduleRestart(wait);
                return;
            }

            this._micStarting = true;
            try {
                await this.ensureVoiceMic();
                this.recognition.start();
                this.recognitionActive = true;
                const inCommandWindow = this.commandMode && Date.now() < this.commandModeUntil;
                this.status = this._listeningPaused
                    ? (this.labels.notListening || '')
                    : (this._speaking
                        ? (this.labels.answering || this.labels.chatListening || this.labels.listening || '')
                        : (inCommandWindow
                            ? (this.labels.chatListening || this.labels.listening || this.labels.alwaysOn || '')
                            : (this.labels.alwaysOn || this.labels.wake || '')));
                this.error = '';
            } catch {
                this.recognitionActive = false;
                this.scheduleRestart(150);
            } finally {
                this._micStarting = false;
            }
        },

        stopRecognition(notifyIdle) {
            if (!this.recognition || !this.recognitionActive) {
                return;
            }
            try {
                this.recognition.stop();
            } catch {
                // ignore
            }
            this.recognitionActive = false;
            if (notifyIdle) {
                flowdeskNotifyVoiceIdle();
            }
        },

        async askNovaChat(question) {
            if (this._chatLoading || !this.chatUrl) {
                return;
            }

            this._chatLoading = true;
            this.showWakeTooltip = false;
            this.error = '';
            this.status = this.labels.thinking || '';
            flowdeskUnlockNovaAudio();
            flowdeskStartNovaThinkingSound();

            if (this._chatAbort) {
                try {
                    this._chatAbort.abort();
                } catch {
                    // ignore
                }
            }
            this._chatAbort = new AbortController();

            try {
                const res = await fetch(this.chatUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this._csrf,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        message: question,
                        conversation_id: this.conversationId,
                    }),
                    signal: this._chatAbort.signal,
                });

                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    throw new Error(flowdeskFetchErrorMessage(res, data, this.labels.requestFailed));
                }

                this.conversationId = data.conversation_id || this.conversationId;
                flowdeskSyncAiCreditsFromJson(data);
                const reply = (data.reply || '').trim();
                if (!reply) {
                    throw new Error(this.labels.unknown || 'No reply');
                }

                flowdeskStopNovaThinkingSound();
                this.status = this.labels.answering || '';
                this.showSpokenTooltip(reply);
                this.muteVoiceActions(Math.min(12000, Math.max(3000, reply.length * 45)));
                await flowdeskSpeakNovaText({
                    text: reply,
                    speakUrl: this.speakUrl,
                    speechLocale: this.speechLocale,
                    synthesis: this.synthesis,
                    browserFallbackHint: this.labels.browserFallback || '',
                    onNotice: (message) => this.showSpeakNotice(message),
                    onStart: () => {
                        this._speaking = true;
                        this.heard = '';
                        this.beginStopListening();
                    },
                    onEnd: () => {
                        this._speaking = false;
                        this._listenForStop = false;
                        this.clearSpokenTooltip();
                        this.openCommandWindow();
                        this.resumeListeningAfterMute(700);
                    },
                });
            } catch (e) {
                if (e?.name === 'AbortError') {
                    return;
                }
                const message = flowdeskSanitizeNotifyMessage(
                    e?.message,
                    flowdeskNotifyLabels().requestFailed || this.labels.unknown || '',
                );
                this.showSpeakNotice(message);
                window.setTimeout(() => {
                    this.error = '';
                }, 4500);
            } finally {
                flowdeskStopNovaThinkingSound();
                this._chatLoading = false;
                this._speaking = false;
                this._listenForStop = false;
                this._chatAbort = null;
                this.clearSpokenTooltip();
                this.openCommandWindow();
                this.resumeListeningAfterMute(700);
            }
        },

        async playBriefing() {
            if (this._briefingLoading || this._speaking || !this.briefingUrl) {
                return;
            }

            this._briefingLoading = true;
            this.showWakeTooltip = false;
            this.error = '';
            this.status = this.labels.briefing || '';

            flowdeskUnlockNovaAudio();
            this.muteVoiceActions(2000);

            const ok = await flowdeskPlayNovaBriefing({
                briefingUrl: this.briefingUrl,
                speechLocale: this.speechLocale,
                synthesis: this.synthesis,
                browserFallbackHint: this.labels.browserFallback || '',
                onNotice: (message) => this.showSpeakNotice(message),
                onStart: () => {
                    this._speaking = true;
                    this.heard = '';
                    this.beginStopListening();
                },
                onEnd: () => {
                    this._speaking = false;
                    this._briefingLoading = false;
                    this._listenForStop = false;
                    this.clearSpokenTooltip();
                    this.openCommandWindow();
                    this.resumeListeningAfterMute(700);
                },
            });

            this._speaking = false;
            this._briefingLoading = false;
            this._listenForStop = false;
            this.clearSpokenTooltip();

            if (!ok) {
                this.error = this.labels.briefingError || '';
                window.setTimeout(() => {
                    this.error = '';
                }, 4500);
            }

            this.openCommandWindow();
            this.resumeListeningAfterMute(700);
        },

        showSpokenTooltip(text) {
            const value = String(text || '').trim();
            if (!value) {
                return;
            }

            this.speakingTooltipText = value;
            this.showSpeakingTooltip = true;
            this.showWakeTooltip = false;
            this.heard = '';
            if (this._speakingTooltipTimer) {
                window.clearTimeout(this._speakingTooltipTimer);
            }
            this._speakingTooltipTimer = window.setTimeout(() => {
                if (!this._speaking) {
                    this.clearSpokenTooltip();
                }
            }, 120000);
        },

        clearSpokenTooltip() {
            this.showSpeakingTooltip = false;
            this.speakingTooltipText = '';
            if (this._speakingTooltipTimer) {
                window.clearTimeout(this._speakingTooltipTimer);
                this._speakingTooltipTimer = null;
            }
        },

        speakWakeReply(text = '') {
            const line = String(text || '').trim();
            if (!line) {
                return false;
            }

            flowdeskUnlockNovaAudio();

            return speakNovaVoiceLine(this, {
                text: line,
                status: this.labels.chatListening || this.labels.listening || '',
                muteMs: 1800,
                afterMuteMs: 450,
                allowBargeIn: false,
                onAfterSpeak: () => {
                    this.openCommandWindow();
                },
                ensureListeningDelayMs: 450,
            });
        },

        handleWakeOnly() {
            const now = Date.now();
            if (now - this._lastWakeSpokenAt < 900) {
                return true;
            }

            // Already listening for a command — don't force another “say Nova” round-trip.
            if (this.commandMode && this.commandModeUntil - now > 4000) {
                this.openCommandWindow();
                this._lastWakeSpokenAt = now;
                this.forceEnsureListening();

                return true;
            }

            const { text, firstTime } = flowdeskNovaResolveWakeReply({
                storageKey: this.wakeReplyStorageKey,
                hello: this.labels.wakeReplyHello,
                listening: this.labels.wakeReplyListening,
                legacy: this.labels.wakeReply,
            });

            if (!text) {
                this.openCommandWindow();

                return true;
            }

            if (firstTime) {
                flowdeskNovaMarkWakeGreeted(this.wakeReplyStorageKey);
            }

            this._lastWakeSpokenAt = now;
            this.openCommandWindow();

            if (!this.ttsEnabled) {
                this.forceEnsureListening();
                return true;
            }

            // Mic stays off while Nova greets; speakWakeReply reopens it after TTS.
            if (this.labels.wakeTooltip) {
                this.wakeTooltipText = this.labels.wakeTooltip;
                this.showWakeTooltip = true;
                window.setTimeout(() => {
                    this.showWakeTooltip = false;
                }, 6000);
            }

            this.speakWakeReply(text);

            return true;
        },

        speakNotListening() {
            return speakNovaVoiceLine(this, {
                text: this.labels.notListening,
                muteMs: 8000,
                afterMuteMs: 3000,
                onAfterSpeak: () => {
                    this.status = this.labels.notListening || '';
                    if (this._listeningPaused) {
                        this.scheduleRestart(400);
                    }
                },
                ensureListeningDelayMs: -1,
            });
        },

        speakListeningAgain() {
            return speakNovaVoiceLine(this, {
                text: this.labels.listeningAgain,
                muteMs: 2500,
                afterMuteMs: 900,
                allowBargeIn: false,
                onAfterSpeak: () => {
                    this.status = this.labels.alwaysOn || this.labels.wake || '';
                },
                ensureListeningDelayMs: 900,
            });
        },

        shouldAcknowledgeFiller() {
            return Date.now() - this._lastFillerSpokenAt > 3500;
        },

        speakTakeYourTime() {
            const line = String(this.labels.takeYourTime || '').trim();
            if (!line) {
                return false;
            }
            if (!this.shouldAcknowledgeFiller()) {
                return false;
            }
            if (this._speaking || this._chatLoading || this._briefingLoading) {
                return false;
            }

            this._lastFillerSpokenAt = Date.now();
            this.openCommandWindow();

            return speakNovaVoiceLine(this, {
                text: line,
                status: this.labels.chatListening || this.labels.listening || '',
                muteMs: 1800,
                afterMuteMs: 450,
                allowBargeIn: false,
                purpose: 'ui',
                onAfterSpeak: () => {
                    this.openCommandWindow();
                },
                ensureListeningDelayMs: 450,
            });
        },

        speakIdentityReply() {
            this.openCommandWindow(30000);
            this.showWakeTooltip = false;

            return speakNovaVoiceLine(this, {
                text: this.labels.identityReply,
                status: this.labels.answering || this.labels.chatListening || '',
                muteMs: 20000,
                afterMuteMs: 900,
                onAfterSpeak: () => {
                    this.openCommandWindow();
                },
            });
        },

        async postWorkflow(payload) {
            const response = await fetch(this.workflowUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': this._csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                throw new Error(data.message || this.labels.workflowError || this.labels.unknown || 'Request failed');
            }

            return data;
        },

        async startWorkflow(workflowId, seed = {}) {
            if (!this.workflowUrl || this._workflowLoading || !workflowId) {
                return;
            }

            this._workflowLoading = true;
            this.openCommandWindow(120000);
            this._workflowActive = true;
            this.error = '';
            this.heard = '';
            this.showWakeTooltip = false;

            try {
                const payload = { action: 'start', workflow: workflowId };
                if (seed && typeof seed === 'object' && Object.keys(seed).length > 0) {
                    payload.seed = seed;
                }
                const result = await this.postWorkflow(payload);
                await this.speakWorkflowReply(result.reply || '');
                this.status = result.active
                    ? (this.labels.workflowListening || this.labels.chatListening || '')
                    : (this.labels.chatListening || this.labels.alwaysOn || this.labels.wake || '');
                if (result.done) {
                    this._workflowActive = false;
                    this.openCommandWindow();
                }
            } catch (error) {
                this._workflowActive = false;
                this.openCommandWindow();
                this.error = error?.message || this.labels.workflowError || '';
                window.setTimeout(() => {
                    this.error = '';
                }, 4000);
            } finally {
                this._workflowLoading = false;
                this.ensureListening();
            }
        },

        async advanceWorkflow(input) {
            if (!this.workflowUrl || this._workflowLoading) {
                return;
            }

            this._workflowLoading = true;
            this.error = '';

            try {
                const result = await this.postWorkflow({ action: 'advance', input });
                if (result.done) {
                    this._workflowActive = false;
                    this.openCommandWindow();
                    this.status = this.labels.chatListening || this.labels.alwaysOn || this.labels.wake || '';
                    if (result.redirect_url) {
                        const redirectUrl = result.redirect_url;
                        const reply = String(result.reply || '').trim();
                        this.beginStopListening();
                        this.muteVoiceActions(this.ttsEnabled ? 5000 : 400);
                        if (this.ttsEnabled && reply) {
                            await flowdeskPersistNovaSpeechForNavigation({
                                text: reply,
                                speakUrl: this.speakUrl,
                                speechLocale: this.speechLocale,
                                purpose: 'content',
                            });
                        }
                        window.location.href = redirectUrl;
                        return;
                    }
                    await this.speakWorkflowReply(result.reply || '');
                } else {
                    await this.speakWorkflowReply(result.reply || '');
                    this.status = this.labels.workflowListening || this.labels.chatListening || '';
                }
            } catch (error) {
                this.error = error?.message || this.labels.workflowError || '';
                window.setTimeout(() => {
                    this.error = '';
                }, 4000);
            } finally {
                this._workflowLoading = false;
                this.ensureListening();
            }
        },

        speakWorkflowReply(text) {
            return speakNovaVoiceLine(this, {
                text,
                purpose: 'content',
                muteMs: 6000,
                afterMuteMs: 700,
                ensureListeningDelayMs: 700,
                onAfterSpeak: () => {
                    if (this._workflowActive) {
                        this.status = this.labels.workflowListening || this.labels.chatListening || '';
                    } else {
                        this.openCommandWindow();
                    }
                },
            });
        },

        navigateToMatch(match) {
            this.error = '';
            this.showWakeTooltip = false;
            // Keep command mode across the page load so the next order doesn't need “Nova” again.
            this.openCommandWindow();

            if (match.cmd.action === 'logout') {
                this.clearCommandMode();
                this.status = this.labels.loggingOut || '';
                window.setTimeout(() => this.performLogout(match.cmd.url), 80);
                return;
            }

            const navLine = `${this.labels.navigating || ''} ${match.cmd.label}`.trim();
            this.status = navLine;

            // Stop the mic before TTS / navigation so speaker echo cannot re-fire the command.
            this.beginStopListening();
            this.muteVoiceActions(this.ttsEnabled ? 5000 : 400);

            // Already on this page: announce once, do not reload (avoids dashboard loops).
            if (this.isAlreadyOnTargetUrl(match.cmd.url)) {
                if (this.ttsEnabled) {
                    void speakNovaVoiceLine(this, {
                        text: navLine,
                        status: navLine,
                        allowBargeIn: false,
                        muteMs: 5000,
                        afterMuteMs: 700,
                        onAfterSpeak: () => {
                            this.openCommandWindow();
                        },
                        ensureListeningDelayMs: 700,
                    });
                } else {
                    this.openCommandWindow();
                    this.forceEnsureListening();
                }
                return;
            }

            if (!this.ttsEnabled) {
                window.location.href = match.cmd.url;
                return;
            }

            const continueText = flowdeskGetActiveNovaSpeechText() || navLine;

            void (async () => {
                await flowdeskPersistNovaSpeechForNavigation({
                    text: continueText,
                    speakUrl: this.speakUrl,
                    speechLocale: this.speechLocale,
                    purpose: 'ui',
                });
                window.location.href = match.cmd.url;
            })();
        },

        performLogout(url) {
            const action = String(url || '').trim();
            if (!action) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = action;

            if (this._csrf) {
                const token = document.createElement('input');
                token.type = 'hidden';
                token.name = '_token';
                token.value = this._csrf;
                form.appendChild(token);
            }

            document.body.appendChild(form);
            form.submit();
        },
    }));
}
