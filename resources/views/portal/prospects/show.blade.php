<x-app-layout>
    <x-slot name="header">
        <div class="cf-page-head">
            <div class="min-w-0">
                <p class="cf-eyebrow">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="cf-page-title">{{ $prospect->name }}</h2>
                <p class="cf-page-sub">
                    {{ $prospect->campaignRelation?->name ?? $prospect->campaign ?: __('client_prospect_campaign') }}
                    @if ($prospect->isClientSourced())
                        <span class="ms-2 inline-flex rounded-md bg-orange-100 px-2 py-0.5 text-[11px] font-semibold text-orange-800 dark:bg-orange-950/50 dark:text-orange-200">{{ __('portal_prospect_source_client') }}</span>
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($prospect->telHref())
                    <a href="{{ $prospect->telHref() }}" class="cf-btn-secondary"><i class="fa-solid fa-phone text-xs" aria-hidden="true"></i> {{ __('Phone') }}</a>
                @endif
                @if ($prospect->email)
                    <a href="mailto:{{ $prospect->email }}" class="cf-btn-secondary"><i class="fa-solid fa-envelope text-xs" aria-hidden="true"></i> {{ __('Email') }}</a>
                @endif
                <a href="{{ route('portal.assistant.index', ['prospect' => $prospect->id]) }}" class="cf-btn-primary">
                    <i class="fa-solid fa-wand-magic-sparkles text-xs" aria-hidden="true"></i> {{ __('portal_ai_ask') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="cf-page-wrap grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)]">
            @if (session('status'))
                <div class="cf-flash-ok lg:col-span-2">{{ session('status') }}</div>
            @endif

            <div class="space-y-6">
                <section class="flow-panel cf-card cf-enter p-6">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_prospect_details') }}</h3>
                    <form method="POST" action="{{ route('portal.prospects.update', $prospect) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <div class="sm:col-span-2">
                            <x-input-label for="p_name" :value="__('client_prospect_name')" />
                            <x-text-input id="p_name" name="name" type="text" class="mt-1.5 block w-full" :value="$prospect->name" @disabled(! $canEditFields) required />
                        </div>
                        <div>
                            <x-input-label for="p_phone" :value="__('Phone')" />
                            <x-text-input id="p_phone" name="phone" type="text" class="mt-1.5 block w-full" :value="$prospect->phone" @disabled(! $canEditFields) />
                        </div>
                        <div>
                            <x-input-label for="p_email" :value="__('Email')" />
                            <x-text-input id="p_email" name="email" type="email" class="mt-1.5 block w-full" :value="$prospect->email" @disabled(! $canEditFields) />
                        </div>
                        <div>
                            <x-input-label for="p_campaign" :value="__('client_prospect_campaign')" />
                            <select id="p_campaign" name="campaign" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800" @disabled(! $canEditFields)>
                                <option value="">—</option>
                                @foreach ($campaigns as $c)
                                    <option value="{{ $c->name }}" @selected(($prospect->campaignRelation?->name ?? $prospect->campaign) === $c->name)>{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="p_status" :value="__('Status')" />
                            <select id="p_status" name="status" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                                @foreach ($statuses as $st)
                                    <option value="{{ $st->value }}" @selected($prospect->status === $st)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label for="p_comment" :value="__('client_prospect_comments')" />
                            <textarea id="p_comment" name="comment" rows="3" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800">{{ $prospect->comment }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                        </div>
                    </form>
                </section>

                <section class="flow-panel cf-card cf-enter p-6" style="--cf-delay:80ms">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_prospect_timeline') }}</h3>
                    <form method="POST" action="{{ route('portal.prospects.comment', $prospect) }}" class="mt-4 flex flex-col gap-2 sm:flex-row">
                        @csrf
                        <textarea name="comment" rows="2" required maxlength="5000" class="block w-full rounded-lg border-slate-300 text-sm shadow-sm dark:border-slate-600 dark:bg-slate-800" placeholder="{{ __('client_prospect_comment_placeholder') }}"></textarea>
                        <x-primary-button type="submit" class="!normal-case shrink-0 self-end">{{ __('Save') }}</x-primary-button>
                    </form>
                    <ol class="mt-5 space-y-4 border-s-2 border-orange-200 ps-4 dark:border-orange-900">
                        @forelse ($prospect->comments as $entry)
                            <li class="cf-enter">
                                <p class="text-xs text-slate-500">
                                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $entry->author?->name ?? __('Team') }}</span>
                                    <span class="tabular-nums"> · {{ $entry->created_at?->timezone('Africa/Tunis')->format('d/m/Y H:i') }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-800 dark:text-slate-100">{{ $entry->body }}</p>
                            </li>
                        @empty
                            <li class="text-sm text-slate-500">{{ __('portal_prospect_no_comments') }}</li>
                        @endforelse
                    </ol>
                </section>
            </div>

            <aside class="space-y-6">
                <section class="flow-panel cf-card cf-enter p-6" style="--cf-delay:60ms">
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_nav_appointments') }}</h3>
                    <p class="mt-2 text-sm tabular-nums text-slate-700 dark:text-slate-200">
                        {{ $prospect->appointment_at?->format('d/m/Y H:i') ?? __('portal_appointment_unset') }}
                    </p>
                    @if ($prospect->appointment_status)
                        <span class="mt-2 inline-flex rounded-md px-2 py-1 text-[11px] font-semibold {{ $prospect->appointment_status->badgeClass() }}">{{ $prospect->appointment_status->label() }}</span>
                    @endif
                    <form method="POST" action="{{ route('portal.prospects.appointment', $prospect) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="rdv_at" :value="__('client_prospect_appointment')" />
                            <x-text-input id="rdv_at" name="appointment_at" type="datetime-local" class="mt-1.5 block w-full" :value="$prospect->appointment_at?->format('Y-m-d\TH:i')" />
                        </div>
                        <div>
                            <x-input-label for="rdv_st" :value="__('portal_appointment_status')" />
                            <select id="rdv_st" name="appointment_status" class="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-800">
                                <option value="">—</option>
                                @foreach ($appointmentStatuses as $st)
                                    <option value="{{ $st->value }}" @selected($prospect->appointment_status === $st)>{{ $st->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button type="submit" class="!normal-case">{{ __('Save') }}</x-primary-button>
                    </form>
                </section>
                <a href="{{ route('portal.prospects.index') }}" class="inline-flex text-sm font-medium text-orange-600 hover:underline">← {{ __('portal_nav_prospects') }}</a>
            </aside>
        </div>
    </div>
</x-app-layout>
