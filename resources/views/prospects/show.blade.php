@php
    use App\Enums\ProspectStatus;
    $isConverted = $prospect->status === ProspectStatus::Converted;
    $doneCount = collect($lifecycle)->where('done', true)->count();
    $totalSteps = count($lifecycle);
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('prospect_lifecycle_eyebrow') }}</p>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ $prospect->name }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $prospect->status?->label() }}
                    @if ($prospect->source)
                        · {{ $prospect->source->label() }}
                    @endif
                    · {{ __('prospect_lifecycle_progress', ['done' => $doneCount, 'total' => $totalSteps]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('prospects.edit', $prospect) }}">
                    <x-secondary-button type="button" class="!normal-case">{{ __('Edit') }}</x-secondary-button>
                </a>
                @if (! $isConverted)
                    @if (! $prospect->isVerified())
                        <form method="POST" action="{{ route('prospects.verify', $prospect) }}">
                            @csrf
                            <x-secondary-button type="submit" class="!normal-case">{{ __('prospect_mark_verified') }}</x-secondary-button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('prospects.convert', $prospect) }}" onsubmit="return confirm(@json(__('prospect_convert_confirm')))">
                        @csrf
                        <x-primary-button type="submit" class="!normal-case">{{ __('prospect_convert_to_client') }}</x-primary-button>
                    </form>
                @elseif ($prospect->convertedClient)
                    <a href="{{ route('clients.show', $prospect->convertedClient) }}">
                        <x-primary-button type="button" class="!normal-case">{{ __('View client') }}</x-primary-button>
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-12xl w-full sm:px-6 lg:px-8 space-y-6" x-data="{ panel: @js($defaultPanel) }">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (! $isConverted)
                <div class="flow-panel flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('prospect_next_step') }}</p>
                        @if ($nextStep)
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_next_step_cta', ['step' => $nextStep['label']]) }}</p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_next_step_hint') }}</p>
                        @else
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_all_steps_done') }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @if ($nextStep)
                            <button type="button" @click="panel = @js($nextStep['panel'])" class="inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
                                {{ __('prospect_next_step_cta', ['step' => $nextStep['label']]) }}
                            </button>
                        @endif
                        <button type="button" @click="panel = 'convert'" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">
                            {{ __('prospect_skip_to_convert') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Pipeline --}}
            <div class="flow-panel overflow-x-auto p-5 sm:p-6">
                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_lifecycle_title') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_lifecycle_intro') }}</p>
                <ol class="mt-5 flex min-w-[720px] items-stretch gap-2">
                    @foreach ($lifecycle as $index => $step)
                        @php
                            $done = $step['done'];
                            $type = $step['type']->value;
                            $panelKey = \App\Models\Prospect::panelForActivityType($step['type']);
                            $isCurrent = ! $isConverted && $nextStep && $nextStep['type'] === $step['type'];
                        @endphp
                        <li class="relative flex flex-1 flex-col items-center text-center">
                            @if ($index < $totalSteps - 1)
                                <span class="absolute left-[calc(50%+14px)] right-[-50%] top-3.5 h-0.5 {{ $done ? 'bg-emerald-400 dark:bg-emerald-600' : 'bg-slate-200 dark:bg-slate-700' }}" aria-hidden="true"></span>
                            @endif
                            <button
                                type="button"
                                @click="panel = @js($panelKey)"
                                class="relative z-10 flex h-7 w-7 items-center justify-center rounded-full text-[11px] font-semibold ring-2 ring-offset-2 ring-offset-white dark:ring-offset-slate-900 {{ $done ? 'bg-emerald-500 text-white ring-emerald-500' : ($isCurrent ? 'bg-indigo-600 text-white ring-indigo-500' : 'bg-slate-100 text-slate-500 ring-slate-200 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700') }}"
                            >
                                @if ($done)
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/></svg>
                                @else
                                    {{ $index + 1 }}
                                @endif
                            </button>
                            <span class="mt-2 text-[11px] font-medium leading-tight {{ $done ? 'text-emerald-700 dark:text-emerald-300' : ($isCurrent ? 'text-indigo-700 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400') }}">{{ $step['label'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="grid gap-6 lg:grid-cols-3">
                {{-- Contact card --}}
                <div class="flow-panel space-y-4 p-6 lg:col-span-1">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_contact_card') }}</h3>
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $prospect->email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Phone') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white flowdesk-ltr-num">{{ $prospect->phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('prospect_whatsapp') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white flowdesk-ltr-num">{{ $prospect->whatsapp ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('prospect_company_name') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $prospect->company_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('prospect_job_title') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $prospect->job_title ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('prospect_source_label') }}</dt>
                            <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $prospect->source?->label() ?? '—' }}</dd>
                        </div>
                        @if ($prospect->convertedClient)
                            <div>
                                <dt class="text-slate-500 dark:text-slate-400">{{ __('Client') }}</dt>
                                <dd class="mt-0.5">
                                    <a href="{{ route('clients.show', $prospect->convertedClient) }}" class="font-medium text-indigo-600 hover:underline dark:text-indigo-400">
                                        {{ $prospect->convertedClient->name }}
                                        @if ($prospect->convertedClient->status)
                                            <span class="text-slate-500">({{ $prospect->convertedClient->status->label() }})</span>
                                        @endif
                                    </a>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    @php $links = $prospect->socialLinks(); @endphp
                    @if ($links !== [])
                        <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('prospect_socials') }}</h4>
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($links as $key => $url)
                                    <li>
                                        <a href="{{ $url }}" class="text-indigo-600 hover:underline dark:text-indigo-400" target="_blank" rel="noopener noreferrer">{{ __('prospect_social_'.$key) }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (filled($prospect->notes))
                        <div class="border-t border-slate-200 pt-4 dark:border-slate-700">
                            <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Notes') }}</h4>
                            <p class="mt-2 whitespace-pre-wrap text-sm text-slate-700 dark:text-slate-300">{{ $prospect->notes }}</p>
                        </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="space-y-4 lg:col-span-2">
                    @if (! $isConverted)
                        <div class="flow-panel border border-slate-200/80 bg-white/90 p-2 dark:border-slate-700/60 dark:bg-slate-900/40">
                            <nav class="flex flex-wrap gap-1">
                                @foreach ([
                                    'call' => __('prospect_activity_call'),
                                    'feedback' => __('prospect_activity_feedback'),
                                    'meeting' => __('prospect_activity_meeting'),
                                    'quote' => __('prospect_activity_quote'),
                                    'ai' => __('prospect_activity_ai'),
                                    'mailing' => __('prospect_activity_mailing'),
                                    'reminder' => __('prospect_activity_reminder'),
                                    'convert' => __('prospect_activity_converted'),
                                ] as $key => $label)
                                    <button
                                        type="button"
                                        @click="panel = '{{ $key }}'"
                                        :class="panel === '{{ $key }}' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
                                        class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                    >{{ $label }}</button>
                                @endforeach
                            </nav>
                        </div>

                        <div x-show="panel === 'call'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_call_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_call_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.calls.store', $prospect) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="call_summary" :value="__('prospect_call_summary')" />
                                    <textarea id="call_summary" name="summary" rows="3" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('summary') }}</textarea>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="call_outcome" :value="__('prospect_call_outcome')" />
                                        <x-text-input id="call_outcome" name="outcome" class="mt-1 block w-full" :value="old('outcome')" />
                                    </div>
                                    <div>
                                        <x-input-label for="call_occurred_at" :value="__('prospect_occurred_at')" />
                                        <x-text-input id="call_occurred_at" name="occurred_at" type="datetime-local" class="mt-1 block w-full" :value="old('occurred_at')" />
                                    </div>
                                </div>
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_log_call') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'feedback'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_feedback_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_feedback_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.feedbacks.store', $prospect) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="feedback_body" :value="__('prospect_feedback_label')" />
                                    <textarea id="feedback_body" name="feedback" rows="3" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('feedback') }}</textarea>
                                </div>
                                <div>
                                    <x-input-label for="feedback_rating" :value="__('prospect_feedback_rating')" />
                                    <select id="feedback_rating" name="rating" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">
                                        <option value="">{{ __('Optional') }}</option>
                                        @for ($i = 1; $i <= 5; $i++)
                                            <option value="{{ $i }}" @selected((string) old('rating') === (string) $i)>{{ $i }}/5</option>
                                        @endfor
                                    </select>
                                </div>
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_log_feedback') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'meeting'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_meeting_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_meeting_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.meetings.store', $prospect) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="meeting_title" :value="__('Title')" />
                                    <x-text-input id="meeting_title" name="title" class="mt-1 block w-full" :value="old('title', __('prospect_meeting_default_title', ['name' => $prospect->name]))" required />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="meeting_date" :value="__('Date')" />
                                        <x-text-input id="meeting_date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->toDateString())" required />
                                    </div>
                                    <div>
                                        <x-input-label for="meeting_time" :value="__('Time')" />
                                        <x-text-input id="meeting_time" name="start_time" type="time" class="mt-1 block w-full" :value="old('start_time')" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="meeting_notes" :value="__('Notes')" />
                                    <textarea id="meeting_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('notes') }}</textarea>
                                </div>
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_schedule_meeting') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'quote'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_quote_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_quote_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.quote.prepare', $prospect) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="quote_notes" :value="__('Notes')" />
                                    <textarea id="quote_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('notes') }}</textarea>
                                </div>
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_prepare_quote') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'ai'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_ai_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_ai_intro') }}</p>
                            </div>
                            @if ($latestAi)
                                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm whitespace-pre-wrap text-slate-800 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-200">{{ $latestAi->body }}</div>
                            @endif
                            <form method="POST" action="{{ route('prospects.ai-catch', $prospect) }}">
                                @csrf
                                <x-primary-button type="submit" class="!normal-case">{{ $latestAi ? __('prospect_ai_regenerate') : __('prospect_ai_generate') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'mailing'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_mail_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_mail_intro') }}</p>
                            </div>
                            @if (! $prospect->email)
                                <p class="text-sm text-amber-700 dark:text-amber-300">{{ __('prospect_mail_no_email') }}</p>
                            @else
                                <form method="POST" action="{{ route('prospects.mail.store', $prospect) }}" class="space-y-3">
                                    @csrf
                                    <div>
                                        <x-input-label for="mail_subject" :value="__('Subject')" />
                                        <x-text-input id="mail_subject" name="subject" class="mt-1 block w-full" :value="old('subject', __('prospect_mail_default_subject', ['company' => auth()->user()->company?->name ?? '']))" required />
                                    </div>
                                    <div>
                                        <x-input-label for="mail_body" :value="__('Message')" />
                                        <textarea id="mail_body" name="body" rows="6" required class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('body', data_get($latestAi?->meta, 'pitch', '')) }}</textarea>
                                    </div>
                                    <x-primary-button type="submit" class="!normal-case">{{ __('prospect_send_mail') }}</x-primary-button>
                                </form>
                            @endif
                        </div>

                        <div x-show="panel === 'reminder'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_reminder_title') }}</h3>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_panel_reminder_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.reminders.store', $prospect) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <x-input-label for="reminder_title" :value="__('Title')" />
                                    <x-text-input id="reminder_title" name="title" class="mt-1 block w-full" :value="old('title', __('prospect_reminder_default_title', ['name' => $prospect->name]))" required />
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <x-input-label for="reminder_date" :value="__('Date')" />
                                        <x-text-input id="reminder_date" name="date" type="date" class="mt-1 block w-full" :value="old('date', now()->addDay()->toDateString())" required />
                                    </div>
                                    <div>
                                        <x-input-label for="reminder_time" :value="__('Time')" />
                                        <x-text-input id="reminder_time" name="start_time" type="time" class="mt-1 block w-full" :value="old('start_time', '09:00')" />
                                    </div>
                                </div>
                                <div>
                                    <x-input-label for="reminder_notes" :value="__('Notes')" />
                                    <textarea id="reminder_notes" name="notes" rows="2" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800 dark:text-white">{{ old('notes') }}</textarea>
                                </div>
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_schedule_reminder') }}</x-primary-button>
                            </form>
                        </div>

                        <div x-show="panel === 'convert'" x-cloak class="flow-panel space-y-4 p-6">
                            <div>
                                <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_panel_convert_title') }}</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('prospect_panel_convert_intro') }}</p>
                            </div>
                            <form method="POST" action="{{ route('prospects.convert', $prospect) }}" onsubmit="return confirm(@json(__('prospect_convert_confirm')))">
                                @csrf
                                <x-primary-button type="submit" class="!normal-case">{{ __('prospect_convert_to_client') }}</x-primary-button>
                            </form>
                        </div>
                    @else
                        <div class="flow-panel p-6 text-sm text-slate-600 dark:text-slate-300">
                            {{ __('prospect_lifecycle_done') }}
                            @if ($prospect->convertedClient)
                                <a href="{{ route('clients.show', $prospect->convertedClient) }}" class="ml-1 font-semibold text-indigo-600 hover:underline dark:text-indigo-400">{{ $prospect->convertedClient->name }}</a>
                            @endif
                        </div>
                    @endif

                    {{-- Timeline --}}
                    <div class="flow-panel p-6">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('prospect_timeline_heading') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('prospect_timeline_intro') }}</p>
                        <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($prospect->activities as $activity)
                                <li class="py-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="text-sm font-medium text-slate-900 dark:text-white">
                                                <span class="inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $activity->type->label() }}</span>
                                                <span class="ml-1.5">{{ $activity->title }}</span>
                                            </p>
                                            @if (filled($activity->body))
                                                <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($activity->body, 400) }}</p>
                                            @endif
                                            @if ($activity->user)
                                                <p class="mt-1 text-xs text-slate-400">{{ $activity->user->name }}</p>
                                            @endif
                                        </div>
                                        <time class="shrink-0 text-xs text-slate-400 flowdesk-ltr-num">{{ $activity->occurred_at?->format('Y-m-d H:i') }}</time>
                                    </div>
                                </li>
                            @empty
                                <li class="py-8 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('prospect_timeline_empty') }}</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('prospects.destroy', $prospect) }}" onsubmit="return confirm(@json(__('prospect_delete_confirm')))">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-semibold text-rose-600 hover:text-rose-500 dark:text-rose-400">{{ __('Delete') }}</button>
            </form>
        </div>
    </div>
</x-app-layout>
