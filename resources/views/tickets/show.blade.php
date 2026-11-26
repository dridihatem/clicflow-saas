<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ \Illuminate\Support\Str::limit($ticket->title, 64) }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <div class="flow-panel p-8 space-y-4">
                <dl class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase text-slate-500">{{ __('Subject') }}</dt>
                        <dd class="mt-1 text-slate-900 dark:text-white">{{ $ticket->title }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-flow.badge variant="primary">{{ $ticket->status->label() }}</x-flow.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">{{ __('Opened by') }}</dt>
                        <dd class="mt-1 text-slate-900 dark:text-white">{{ $ticket->user?->name ?? '—' }}</dd>
                    </div>
                    @if ($ticket->client_id || $ticket->provider_id)
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase text-slate-500">{{ __('Related') }}</dt>
                            <dd class="mt-1 text-slate-900 dark:text-white">
                                @if ($ticket->client_id)
                                    {{ __('Client') }}: {{ $ticket->client?->name ?? '—' }}
                                @elseif ($ticket->provider_id)
                                    {{ __('Business provider') }}: {{ $ticket->provider?->name ?? '—' }}
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-xs font-semibold uppercase text-slate-500">{{ __('Created') }}</dt>
                        <dd class="mt-1 tabular-nums text-slate-700 dark:text-slate-300">{{ $ticket->created_at->format('Y-m-d H:i') }}</dd>
                    </div>
                </dl>
                <div>
                    <h3 class="text-xs font-semibold uppercase text-slate-500">{{ __('Message') }}</h3>
                    <div class="mt-2 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $ticket->description }}</div>
                </div>
            </div>

            <div class="flow-panel p-8 space-y-5">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('ticket_replies_heading') }}</h3>
                <ol class="space-y-4">
                    @forelse ($ticket->replies as $reply)
                        <li class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/50">
                            <div class="flex flex-wrap items-baseline justify-between gap-2 text-xs text-slate-500 dark:text-slate-400">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $reply->user?->name ?? __('Team') }}</span>
                                <span class="tabular-nums">{{ $reply->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-800 dark:text-slate-200">{{ $reply->body }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500">{{ __('ticket_replies_empty') }}</li>
                    @endforelse
                </ol>

                <form method="POST" action="{{ route('tickets.replies.store', $ticket) }}" class="space-y-3 border-t border-slate-200 pt-5 dark:border-slate-700">
                    @csrf
                    <div>
                        <x-input-label for="ticket_reply" :value="__('ticket_reply_label')" />
                        <textarea id="ticket_reply" name="body" rows="4" required maxlength="20000" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                    </div>
                    <x-primary-button type="submit" class="!normal-case">{{ __('ticket_reply_submit') }}</x-primary-button>
                </form>
            </div>

            @if (auth()->user()->hasAnyRole(['company_admin', 'team_member']))
                <div class="flow-panel p-8">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('Update status') }}</h3>
                    <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="mt-4 flex flex-wrap items-end gap-4">
                        @csrf
                        @method('PATCH')
                        <div class="min-w-[12rem] flex-1">
                            <x-input-label for="status" :value="__('Status')" />
                            <select id="status" name="status" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                                @foreach (\App\Enums\SupportTicketStatus::cases() as $st)
                                    <option value="{{ $st->value }}" @selected($ticket->status === $st)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    </form>
                </div>
            @endif

            <a href="{{ route('tickets.index') }}" class="text-sm font-medium text-orange-600 hover:text-orange-500 dark:text-orange-400">{{ __('Back to tickets') }}</a>
        </div>
    </div>
</x-app-layout>
