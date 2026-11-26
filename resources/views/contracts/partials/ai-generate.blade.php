{{--
  AI generate contract body (corps) from a prompt.
--}}
@php
    $textareaId = $textareaId ?? 'contract_body_html';
    $titleInputId = $titleInputId ?? 'contract_title';
    $typeSelectId = $typeSelectId ?? 'contract_type_key';
    $clientSelectId = $clientSelectId ?? 'contract_client_id';
    $templateSelectId = $templateSelectId ?? 'contract_template_id';
    $aiCreditCost = (int) ($flowdeskAiTaskCredits['contract_body'] ?? 150);
    $generateUrl = route('contracts.ai.generate');
@endphp

<div
    class="mb-4 rounded-xl border border-indigo-200/80 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-950/20"
    x-data='{
        prompt: "",
        busy: false,
        err: null,
        errFallback: @json(__('contract_ai_error')),
        promptRequired: @json(__('contract_ai_subject_required')),
        url: @json($generateUrl),
        textareaId: @json($textareaId),
        titleInputId: @json($titleInputId),
        typeSelectId: @json($typeSelectId),
        clientSelectId: @json($clientSelectId),
        templateSelectId: @json($templateSelectId),
        resolveType() {
            const typeEl = document.getElementById(this.typeSelectId);
            if (typeEl && typeEl.value) return typeEl.value;
            const templateEl = document.getElementById(this.templateSelectId);
            if (templateEl && templateEl.selectedOptions && templateEl.selectedOptions[0]) {
                const t = templateEl.selectedOptions[0].getAttribute("data-type");
                if (t) return t;
            }
            return "service";
        },
        async generate() {
            this.err = null;
            const promptEl = document.getElementById("contract_ai_prompt");
            const promptText = (promptEl && promptEl.value ? promptEl.value : this.prompt || "").trim();
            this.prompt = promptText;
            if (!promptText) { this.err = this.promptRequired; return; }
            const token = (document.querySelector("meta[name=\"csrf-token\"]") || {}).content;
            if (!token) { this.err = this.errFallback; return; }
            this.busy = true;
            try {
                const titleEl = document.getElementById(this.titleInputId);
                const clientEl = document.getElementById(this.clientSelectId);
                let clientName = "";
                if (clientEl && clientEl.selectedOptions && clientEl.selectedOptions[0]) {
                    clientName = (clientEl.selectedOptions[0].textContent || "").trim();
                }
                const res = await fetch(this.url, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": token,
                    },
                    body: JSON.stringify({
                        subject: promptText,
                        type_key: this.resolveType(),
                        title: titleEl ? (titleEl.value || "") : "",
                        client_name: clientName,
                    }),
                });
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) { this.err = data.message || this.errFallback; return; }
                if (data.title && titleEl && !(titleEl.value || "").trim()) {
                    titleEl.value = data.title;
                    titleEl.dispatchEvent(new Event("change", { bubbles: true }));
                }
                const editor = window["flowdeskContractEditor_" + this.textareaId];
                if (editor && typeof editor.setHtml === "function" && data.body_html) {
                    editor.setHtml(data.body_html);
                } else {
                    const ta = document.getElementById(this.textareaId);
                    if (ta && data.body_html) { ta.value = data.body_html; }
                }
                if (typeof window.flowdeskRefreshContractPreview === "function") {
                    window.flowdeskRefreshContractPreview();
                }
            } catch (e) {
                this.err = (e && e.message) ? e.message : this.errFallback;
            } finally {
                this.busy = false;
            }
        }
    }'
>
    <div class="flex items-start gap-2">
        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white">
            <i class="fa-solid fa-wand-magic-sparkles text-sm" aria-hidden="true"></i>
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{{ __('contract_ai_heading') }}</p>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('contract_ai_subtitle') }}</p>
        </div>
    </div>

    <label for="contract_ai_prompt" class="mt-3 block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
        {{ __('contract_ai_prompt_label') }}
    </label>
    <x-ai-voice-wrap target-id="contract_ai_prompt" submit-button-id="contract_ai_generate_btn" class="mt-1.5">
        <textarea
            id="contract_ai_prompt"
            rows="4"
            class="block w-full rounded-lg border border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
            placeholder="{{ __('contract_ai_subject_placeholder') }}"
            x-model="prompt"
        ></textarea>
    </x-ai-voice-wrap>

    <div class="mt-3 flex flex-wrap items-center gap-3">
        <button
            type="button"
            id="contract_ai_generate_btn"
            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 dark:bg-indigo-500 dark:hover:bg-indigo-400"
            x-on:click="generate()"
            x-bind:disabled="busy"
        >
            <i class="fa-solid fa-wand-magic-sparkles text-xs" aria-hidden="true"></i>
            <span x-show="!busy">{{ __('contract_ai_generate') }} ({{ $aiCreditCost }} {{ __('credits') }})</span>
            <span x-show="busy" x-cloak>{{ __('contract_ai_generating') }}</span>
        </button>
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('AI-generated content — review before sending to clients.') }}</p>
    </div>
    <p x-show="err" x-text="err" x-cloak class="mt-2 text-sm text-rose-600 dark:text-rose-400"></p>
</div>
