<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $contract->title }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $contract->reference }} · {{ $contract->status->label() }}</p>
            </div>
        </div>
    </x-slot>

    <div class="portal-page py-10">
        <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="portal-surface portal-enter p-6 sm:p-8">
                <div class="flow-partnership-terms-html text-sm text-slate-800 dark:text-slate-100" role="region" aria-label="{{ __('Contract') }}">
                    {!! $bodyHtml !!}
                </div>

                @if ($canSign)
                    <form method="POST" action="{{ route('portal.contracts.sign', $contract) }}" id="contract-sign-form" class="mt-8 space-y-4 border-t border-slate-200/80 pt-6 dark:border-slate-700/80" enctype="multipart/form-data">
                        @csrf
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Your signature') }}</p>
                        <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('Sign inside the box below, or upload a signature image.') }}</p>
                        <canvas
                            id="sig-canvas"
                            width="560"
                            height="160"
                            class="block h-auto max-w-full cursor-crosshair touch-none rounded-lg border border-dashed border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-950"
                            aria-label="{{ __('Signature pad') }}"
                        ></canvas>
                        <input type="hidden" name="signature_data" id="signature_data" value="" />
                        <div>
                            <button type="button" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700" id="sig-clear">{{ __('Clear signature') }}</button>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400" for="signature_file">{{ __('Or upload signature image') }}</label>
                            <input type="file" name="signature_file" id="signature_file" accept="image/png,image/jpeg" class="mt-2 block w-full text-sm text-slate-600 file:me-3 file:rounded-lg file:border-0 file:bg-teal-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-teal-700 dark:text-slate-300 dark:file:bg-teal-950/50 dark:file:text-teal-200" />
                        </div>
                        @error('signature_data')
                            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror
                        @error('signature_file')
                            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
                        @enderror

                        <label class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input type="checkbox" name="accept" value="1" required class="mt-1 rounded border-slate-300 text-teal-700 dark:border-slate-600 dark:bg-slate-800" />
                            <span>{{ __('I have read and accept this contract.') }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('accept')" class="mt-2" />

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <x-primary-button type="submit" id="sig-submit">{{ __('Sign contract') }}</x-primary-button>
                            <a href="{{ route('portal.contracts.pdf', $contract) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Download PDF') }}</a>
                        </div>
                    </form>
                @else
                    <div class="mt-8 space-y-3 border-t border-slate-200/80 pt-6 dark:border-slate-700/80">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Status') }}</p>
                        <p class="text-sm font-medium text-slate-900 dark:text-white">{{ $contract->status->label() }}</p>
                        @if ($contract->client_signed_at)
                            <p class="text-sm text-slate-600 dark:text-slate-400">
                                {{ __('Signed electronically on :datetime', ['datetime' => $contract->client_signed_at->timezone(config('app.timezone'))->format('Y-m-d H:i')]) }}
                            </p>
                        @endif
                        <div class="flex flex-wrap gap-3 pt-2">
                            <a href="{{ route('portal.contracts.pdf', $contract) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Download PDF') }}</a>
                            <a href="{{ route('portal.contracts.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Back') }}</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($canSign)
        <script>
            (function () {
                const canvas = document.getElementById('sig-canvas');
                const input = document.getElementById('signature_data');
                const form = document.getElementById('contract-sign-form');
                const fileInput = document.getElementById('signature_file');
                if (!canvas || !input || !form) return;

                const ctx = canvas.getContext('2d');
                let drawing = false;
                let hasInk = false;
                const strokeColor = () => document.documentElement.classList.contains('dark') ? '#e2e8f0' : '#0f172a';

                function pos(e) {
                    const r = canvas.getBoundingClientRect();
                    const scaleX = canvas.width / r.width;
                    const scaleY = canvas.height / r.height;
                    if (e.touches && e.touches[0]) {
                        return {
                            x: (e.touches[0].clientX - r.left) * scaleX,
                            y: (e.touches[0].clientY - r.top) * scaleY,
                        };
                    }
                    return {
                        x: (e.clientX - r.left) * scaleX,
                        y: (e.clientY - r.top) * scaleY,
                    };
                }

                function start(e) {
                    e.preventDefault();
                    drawing = true;
                    const p = pos(e);
                    ctx.beginPath();
                    ctx.moveTo(p.x, p.y);
                }

                function move(e) {
                    if (!drawing) return;
                    e.preventDefault();
                    const p = pos(e);
                    ctx.strokeStyle = strokeColor();
                    ctx.lineWidth = 2;
                    ctx.lineCap = 'round';
                    ctx.lineJoin = 'round';
                    ctx.lineTo(p.x, p.y);
                    ctx.stroke();
                    hasInk = true;
                }

                function end() {
                    drawing = false;
                }

                canvas.addEventListener('mousedown', start);
                canvas.addEventListener('mousemove', move);
                canvas.addEventListener('mouseup', end);
                canvas.addEventListener('mouseleave', end);
                canvas.addEventListener('touchstart', start, { passive: false });
                canvas.addEventListener('touchmove', move, { passive: false });
                canvas.addEventListener('touchend', end);

                document.getElementById('sig-clear')?.addEventListener('click', function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    hasInk = false;
                    input.value = '';
                    if (fileInput) fileInput.value = '';
                });

                form.addEventListener('submit', function (e) {
                    const hasFile = fileInput && fileInput.files && fileInput.files.length > 0;
                    if (hasFile) {
                        input.value = '';
                        return;
                    }
                    if (!hasInk) {
                        e.preventDefault();
                        alert(@json(__('contract_signature_required')));
                        return;
                    }
                    input.value = canvas.toDataURL('image/png');
                });
            })();
        </script>
    @endif
</x-app-layout>
