@php
    use App\Enums\ClientContractStatus;
@endphp
@push('modals')
    @if ($contract->canSend())
        <x-modal name="contract-send-preview" :show="false" maxWidth="2xl">
            <div class="p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('contract_preview_send_title') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('contract_preview_send_help') }}</p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200" x-on:click="$dispatch('close-modal', 'contract-send-preview')" aria-label="{{ __('Close') }}">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-200">
                    <p><span class="font-semibold">{{ __('Client') }}:</span> {{ $contract->client?->name ?? '—' }}</p>
                    <p class="mt-1"><span class="font-semibold">{{ __('Reference') }}:</span> {{ $contract->reference ?? '—' }}</p>
                    <p class="mt-1"><span class="font-semibold">{{ __('Title') }}:</span> {{ $contract->title }}</p>
                </div>

                <div class="mt-4 max-h-[50vh] overflow-y-auto rounded-xl border border-slate-200 bg-white p-5 dark:border-slate-600 dark:bg-slate-900">
                    <div class="prose prose-sm max-w-none dark:prose-invert flow-rich-text">
                        {!! $previewHtml !!}
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-2">
                    <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'contract-send-preview')">{{ __('Cancel') }}</x-secondary-button>
                    <a href="{{ route('contracts.pdf', $contract) }}" target="_blank" rel="noopener">
                        <x-secondary-button type="button" class="!normal-case">{{ __('Download PDF') }}</x-secondary-button>
                    </a>
                    <form method="POST" action="{{ route('contracts.send', $contract) }}">
                        @csrf
                        <x-primary-button type="submit" class="!normal-case">{{ __('Confirm send to client') }}</x-primary-button>
                    </form>
                </div>
            </div>
        </x-modal>
    @endif
@endpush

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $contract->title }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $contract->reference }} · {{ $contract->status->label() }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <a
                    href="{{ route('contracts.pdf', $contract) }}"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                    title="{{ __('Download PDF') }}"
                    aria-label="{{ __('Download PDF') }}"
                >
                    <i class="fa-solid fa-file-pdf text-sm" aria-hidden="true"></i>
                </a>
                @if ($contract->isEditable())
                    <a
                        href="{{ route('contracts.edit', $contract) }}"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-indigo-500/40 dark:hover:text-indigo-400"
                        title="{{ __('Edit') }}"
                        aria-label="{{ __('Edit') }}"
                    >
                        <i class="fa-solid fa-pen-to-square text-sm" aria-hidden="true"></i>
                    </a>
                @endif
                @if ($contract->canSend())
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-transparent bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-500"
                        title="{{ __('Preview & send') }}"
                        aria-label="{{ __('Preview & send') }}"
                        x-data=""
                        x-on:click="$dispatch('open-modal', 'contract-send-preview')"
                    >
                        <i class="fa-solid fa-paper-plane text-sm" aria-hidden="true"></i>
                    </button>
                @endif
                @if ($contract->canResend())
                    <form method="POST" action="{{ route('contracts.resend', $contract) }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-transparent bg-indigo-600 text-white shadow-sm transition hover:bg-indigo-500"
                            title="{{ __('Resend contract email') }}"
                            aria-label="{{ __('Resend contract email') }}"
                            onclick="return confirm(@js(__('contract_resend_confirm')))"
                        >
                            <i class="fa-solid fa-envelope text-sm" aria-hidden="true"></i>
                        </button>
                    </form>
                @endif
                @if ($contract->status !== ClientContractStatus::Signed)
                    <form method="POST" action="{{ route('contracts.destroy', $contract) }}" class="inline" onsubmit="return confirm(@js(__('Are you sure?')))">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200/80 bg-white text-slate-600 shadow-sm transition hover:border-rose-200 hover:text-rose-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-rose-500/40 dark:hover:text-rose-400"
                            title="{{ __('Delete contract') }}"
                            aria-label="{{ __('Delete contract') }}"
                        >
                            <i class="fa-regular fa-trash-can text-sm" aria-hidden="true"></i>
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-12xl sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            <div class="flow-panel mb-6 grid gap-4 p-6 sm:grid-cols-2 sm:p-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Client') }}</p>
                    <p class="mt-1 font-medium text-slate-900 dark:text-white">
                        @if ($contract->client)
                            <a href="{{ route('clients.show', $contract->client) }}" class="text-indigo-700 hover:underline dark:text-indigo-300">{{ $contract->client->name }}</a>
                        @else
                            —
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('contract_type_label') }}</p>
                    <p class="mt-1 text-slate-800 dark:text-slate-100">{{ __('contract_type_'.$contract->type_key) }}</p>
                </div>
                @if ($contract->template)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Contract template') }}</p>
                        <p class="mt-1 text-slate-800 dark:text-slate-100">{{ $contract->template->name }}</p>
                    </div>
                @endif
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Starts on') }}</p>
                    <p class="mt-1 text-slate-800 dark:text-slate-100">{{ $contract->starts_on?->format('d/m/Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Ends on') }}</p>
                    <p class="mt-1 text-slate-800 dark:text-slate-100">{{ $contract->ends_on?->format('d/m/Y') ?? '—' }}</p>
                </div>
                @if ($contract->sent_at)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Sent at') }}</p>
                        <p class="mt-1 text-slate-800 dark:text-slate-100">{{ $contract->sent_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
                @if ($contract->client_signed_at)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Client signed at') }}</p>
                        <p class="mt-1 text-slate-800 dark:text-slate-100">{{ $contract->client_signed_at->format('d/m/Y H:i') }}</p>
                    </div>
                @endif
            </div>

            @if ($contract->notes)
                <div class="flow-panel mb-6 p-6">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Notes') }}</p>
                    <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-200">{{ $contract->notes }}</p>
                </div>
            @endif

            <div class="flow-panel p-6 sm:p-8">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {{ $contract->isEditable() ? __('contract_preview_merged_label') : __('contract_body_label') }}
                    </p>
                    @if ($contract->isEditable())
                        <p class="text-xs text-slate-500">{{ __('contract_preview_merged_hint') }}</p>
                    @endif
                </div>
                <div class="prose prose-sm max-w-none dark:prose-invert flow-rich-text">
                    {!! $contract->isEditable() ? $previewHtml : $contract->body_html !!}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
