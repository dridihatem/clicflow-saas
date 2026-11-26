/**
 * Gemini Live / turn-based STS for Nova (realtime speech + system tools).
 */

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

async function postJson(url, body) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body || {}),
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const error = new Error(data.message || 'Request failed');
        error.status = res.status;
        error.data = data;
        throw error;
    }

    return data;
}

export function createNovaLiveSession(ctx) {
    return {
        active: false,
        mode: null,
        _ws: null,
        _audioCtx: null,
        _processor: null,
        _source: null,
        _micStream: null,
        _playing: false,
        _pcmChunks: [],
        _turnTimer: null,
        _session: null,

        async start() {
            if (this.active || !ctx.liveSessionUrl) {
                return false;
            }

            ctx.stopNovaResponse?.();
            ctx._paused = true;
            ctx.stopRecognition?.(false);

            try {
                this._session = await postJson(ctx.liveSessionUrl, {});
            } catch (error) {
                ctx.error = error?.message || ctx.labels?.liveError || 'Live Nova unavailable.';
                ctx._paused = false;
                ctx.ensureListening?.();

                return false;
            }

            this.mode = this._session.mode || 'turn';
            this.active = true;
            ctx._liveActive = true;
            ctx.status = ctx.labels?.liveListening || 'Talk with Nova…';
            ctx.heard = '';
            ctx.showSpokenTooltip?.(ctx.labels?.liveStarted || 'I\'m listening — speak naturally.');

            try {
                await this._startMic();
                if (this.mode === 'live' && this._session.ws_url) {
                    await this._connectLiveWs();
                }
            } catch (error) {
                this.stop();
                ctx.error = error?.message || ctx.labels?.liveError || 'Microphone denied.';

                return false;
            }

            return true;
        },

        stop() {
            this.active = false;
            ctx._liveActive = false;
            this._clearTurnTimer();
            this._stopMic();
            if (this._ws) {
                try {
                    this._ws.close();
                } catch {
                    // ignore
                }
                this._ws = null;
            }
            this._session = null;
            this.mode = null;
            ctx.clearSpokenTooltip?.();
            ctx._paused = false;
            ctx.status = ctx.labels?.alwaysOn || ctx.labels?.wake || '';
            ctx.ensureListening?.();
        },

        async _startMic() {
            if (window.flowdeskNovaVoice && window.flowdeskNovaVoice.enabled === false) {
                throw new Error('nova_voice_disabled');
            }
            this._micStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    channelCount: 1,
                },
            });

            this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            this._source = this._audioCtx.createMediaStreamSource(this._micStream);
            this._processor = this._audioCtx.createScriptProcessor(4096, 1, 1);
            this._processor.onaudioprocess = (event) => {
                if (!this.active) {
                    return;
                }
                const input = event.inputBuffer.getChannelData(0);
                const down = downsampleBuffer(input, this._audioCtx.sampleRate, 16000);
                const pcm = floatTo16BitPCM(down);

                if (this.mode === 'live' && this._ws?.readyState === WebSocket.OPEN) {
                    this._ws.send(JSON.stringify({
                        realtimeInput: {
                            audio: {
                                data: uint8ToBase64(pcm),
                                mimeType: 'audio/pcm;rate=16000',
                            },
                        },
                    }));
                } else if (this.mode === 'turn') {
                    this._pcmChunks.push(pcm);
                    this._scheduleTurnFlush();
                }
            };

            this._source.connect(this._processor);
            this._processor.connect(this._audioCtx.destination);
        },

        _stopMic() {
            try {
                this._processor?.disconnect();
                this._source?.disconnect();
            } catch {
                // ignore
            }
            this._processor = null;
            this._source = null;
            this._micStream?.getTracks?.().forEach((t) => t.stop());
            this._micStream = null;
            try {
                this._audioCtx?.close?.();
            } catch {
                // ignore
            }
            this._audioCtx = null;
            this._pcmChunks = [];
        },

        async _connectLiveWs() {
            await new Promise((resolve, reject) => {
                const ws = new WebSocket(this._session.ws_url);
                this._ws = ws;
                let opened = false;

                ws.onopen = () => {
                    opened = true;
                    // Constrained tokens already embed setup; unconstrained needs setup message.
                    if (this._session.setup && !String(this._session.ws_url).includes('Constrained')) {
                        ws.send(JSON.stringify({ setup: this._session.setup }));
                    }
                    resolve();
                };

                ws.onerror = () => {
                    if (!opened) {
                        reject(new Error(ctx.labels?.liveError || 'Live connection failed.'));
                    }
                };

                ws.onclose = () => {
                    if (this.active) {
                        this.stop();
                    }
                };

                ws.onmessage = async (event) => {
                    try {
                        const message = JSON.parse(event.data);
                        await this._handleServerMessage(message);
                    } catch {
                        // ignore malformed frames
                    }
                };
            });
        },

        async _handleServerMessage(message) {
            const serverContent = message.serverContent;
            if (serverContent?.modelTurn?.parts) {
                for (const part of serverContent.modelTurn.parts) {
                    const inline = part.inlineData || part.inline_data;
                    if (inline?.data) {
                        await this._playPcmBase64(inline.data, 24000);
                    }
                    if (part.text) {
                        ctx.heard = part.text;
                        ctx.showSpokenTooltip?.(part.text);
                    }
                }
            }

            const inputTx = serverContent?.inputTranscription?.text
                || message.inputTranscription?.text;
            if (inputTx) {
                ctx.heard = inputTx;
            }

            const toolCall = message.toolCall;
            if (toolCall?.functionCalls?.length) {
                await this._runTools(toolCall.functionCalls.map((fc) => ({
                    id: fc.id || '',
                    name: fc.name,
                    args: fc.args || {},
                })), true);
            }
        },

        _scheduleTurnFlush() {
            this._clearTurnTimer();
            this._turnTimer = window.setTimeout(() => {
                void this._flushTurn();
            }, 1400);
        },

        _clearTurnTimer() {
            if (this._turnTimer) {
                window.clearTimeout(this._turnTimer);
                this._turnTimer = null;
            }
        },

        async _flushTurn() {
            if (!this.active || this.mode !== 'turn' || this._playing || !this._session?.turn_url) {
                return;
            }
            if (this._pcmChunks.length === 0) {
                return;
            }

            const total = this._pcmChunks.reduce((n, c) => n + c.length, 0);
            if (total < 16000 * 0.45) {
                return;
            }

            const merged = new Uint8Array(total);
            let offset = 0;
            for (const chunk of this._pcmChunks) {
                merged.set(chunk, offset);
                offset += chunk.length;
            }
            this._pcmChunks = [];

            ctx.status = ctx.labels?.thinking || 'Thinking…';

            try {
                const data = await postJson(this._session.turn_url, {
                    audio_base64: uint8ToBase64(merged),
                });

                if (data.text) {
                    ctx.showSpokenTooltip?.(data.text);
                }
                if (data.audio_base64) {
                    const rate = String(data.mime || '').includes('24000') ? 24000 : 24000;
                    await this._playPcmBase64(data.audio_base64, rate);
                }
                if (data.tool_results?.length) {
                    await this._applyToolResults(data.tool_results);
                }
            } catch (error) {
                ctx.error = error?.message || ctx.labels?.liveError || 'Live turn failed.';
            } finally {
                if (this.active) {
                    ctx.status = ctx.labels?.liveListening || 'Talk with Nova…';
                }
            }
        },

        async _runTools(calls, sendToWs) {
            if (!this._session?.tools_url || !calls.length) {
                return;
            }

            try {
                const data = await postJson(this._session.tools_url, { calls });
                await this._applyToolResults(data.results || []);

                if (sendToWs && this._ws?.readyState === WebSocket.OPEN) {
                    this._ws.send(JSON.stringify({
                        toolResponse: {
                            functionResponses: (data.results || []).map((row) => ({
                                id: row.id || undefined,
                                name: row.name,
                                response: row.result || { ok: false },
                            })),
                        },
                    }));
                }
            } catch (error) {
                ctx.error = error?.message || ctx.labels?.liveError || 'Tool failed.';
            }
        },

        async _applyToolResults(results) {
            for (const row of results) {
                const result = row.result || {};
                const action = result.action;

                if (result.reply) {
                    ctx.showSpokenTooltip?.(result.reply);
                }

                if (action === 'navigate' && result.url) {
                    window.setTimeout(() => {
                        window.location.href = result.url;
                    }, 700);
                    this.stop();

                    return;
                }

                if (action === 'logout' && result.url) {
                    ctx.performLogout?.(result.url);
                    this.stop();

                    return;
                }

                if (action === 'start_workflow' && result.workflow_id) {
                    this.stop();
                    ctx._workflowActive = true;
                    if (result.reply) {
                        await ctx.speakWorkflowReply?.(result.reply);
                    }
                    ctx.status = ctx.labels?.workflowListening || ctx.labels?.chatListening || '';
                    ctx.ensureListening?.();

                    return;
                }

                if (action === 'ask_chat' && result.reply) {
                    this.stop();
                    ctx.askNovaChat?.(result.reply);

                    return;
                }

                if (action === 'speak' && result.reply && !this._playing) {
                    // Already shown in tooltip; Live/turn audio may continue.
                }
            }
        },

        async _playPcmBase64(base64, sampleRate = 24000) {
            const bytes = base64ToUint8(base64);
            const blob = pcm16ToWavBlob(bytes, sampleRate);
            const url = URL.createObjectURL(blob);
            this._playing = true;

            try {
                await new Promise((resolve) => {
                    const audio = new Audio(url);
                    audio.onended = () => resolve();
                    audio.onerror = () => resolve();
                    audio.play().catch(() => resolve());
                });
            } finally {
                URL.revokeObjectURL(url);
                this._playing = false;
            }
        },
    };
}
