<x-app-layout>
    <x-slot name="header">
        <div class="cf-page-head">
            <div>
                <p class="cf-eyebrow">{{ __('client_portal_panel_eyebrow') }}</p>
                <h2 class="cf-page-title">{{ __('portal_appointments_heading') }}</h2>
                <p class="cf-page-sub">{{ __('portal_appointments_intro') }}</p>
            </div>
            <a href="{{ route('portal.calendar') }}" class="cf-btn-secondary">
                <i class="fa-solid fa-calendar-days text-xs" aria-hidden="true"></i> {{ __('Calendar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="cf-page-wrap space-y-5">
            @if (session('status'))
                <div class="cf-flash-ok">{{ session('status') }}</div>
            @endif

            <div class="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                <a href="{{ route('portal.prospects.appointments') }}" class="flex-1 rounded-lg px-4 py-2 text-center text-sm font-semibold {{ $tab === 'upcoming' ? 'bg-white text-orange-700 shadow-sm dark:bg-slate-900 dark:text-orange-300' : 'text-slate-600 dark:text-slate-300' }}">
                    {{ __('portal_appointments_upcoming') }} <span class="tabular-nums">({{ $upcomingCount }})</span>
                </a>
                <a href="{{ route('portal.prospects.appointments', ['tab' => 'past']) }}" class="flex-1 rounded-lg px-4 py-2 text-center text-sm font-semibold {{ $tab === 'past' ? 'bg-white text-orange-700 shadow-sm dark:bg-slate-900 dark:text-orange-300' : 'text-slate-600 dark:text-slate-300' }}">
                    {{ __('portal_appointments_past') }} <span class="tabular-nums">({{ $pastCount }})</span>
                </a>
            </div>

            @forelse ($grouped as $day => $items)
                <section class="cf-enter">
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        @if ($day === 'unset')
                            {{ __('portal_appointment_unset') }}
                        @else
                            {{ \Illuminate\Support\Carbon::parse($day)->translatedFormat('l d F Y') }}
                        @endif
                    </h3>
                    <div class="flow-panel cf-card divide-y divide-slate-100 overflow-hidden dark:divide-slate-800">
                        @foreach ($items as $prospect)
                            <article class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <a href="{{ route('portal.prospects.show', $prospect) }}" class="font-semibold text-slate-900 hover:text-orange-700 dark:text-white">{{ $prospect->name }}</a>
                                    <p class="mt-0.5 text-sm tabular-nums text-slate-500">
                                        {{ $prospect->appointment_at?->format('H:i') ?? '—' }}
                                        @if ($prospect->campaignRelation?->name ?? $prospect->campaign)
                                            · {{ $prospect->campaignRelation?->name ?? $prospect->campaign }}
                                        @endif
                                    </p>
                                    @if ($prospect->phone)
                                        <a href="{{ $prospect->telHref() }}" class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-orange-600"><i class="fa-solid fa-phone text-[10px]"></i> <span class="flowdesk-ltr-num" dir="ltr">{{ $prospect->phone }}</span></a>
                                    @endif
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <form method="POST" action="{{ route('portal.prospects.appointment', $prospect) }}" class="flex flex-wrap items-center gap-2">
                                        @csrf
                                        <select name="appointment_status" onchange="this.form.submit()" class="rounded-lg border-0 px-2 py-2 text-[11px] font-semibold {{ ($prospect->appointment_status ?? \App\Enums\ClientAppointmentStatus::Confirme)->badgeClass() }}">
                                            @foreach ($appointmentStatuses as $st)
                                                <option value="{{ $st->value }}" class="bg-white text-slate-900" @selected($prospect->appointment_status === $st)>{{ $st->label() }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                    <details class="relative">
                                        <summary class="cursor-pointer list-none rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 dark:border-slate-600">{{ __('portal_appointment_reschedule') }}</summary>
                                        <form method="POST" action="{{ route('portal.prospects.appointment', $prospect) }}" class="absolute end-0 z-20 mt-2 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-slate-700 dark:bg-slate-900">
                                            @csrf
                                            <input type="hidden" name="appointment_status" value="reporte" />
                                            <label class="block text-[11px] font-semibold text-slate-500">{{ __('client_prospect_appointment') }}</label>
                                            <input type="datetime-local" name="appointment_at" value="{{ $prospect->appointment_at?->format('Y-m-d\TH:i') }}" required class="mt-1 w-full rounded-lg border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800" />
                                            <button type="submit" class="mt-2 w-full rounded-lg bg-orange-600 px-3 py-1.5 text-xs font-semibold text-white">{{ __('Save') }}</button>
                                        </form>
                                    </details>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="flow-panel cf-empty">
                    <i class="fa-solid fa-calendar-check mb-3 text-2xl text-orange-400" aria-hidden="true"></i>
                    <p>{{ __('portal_appointments_empty') }}</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
