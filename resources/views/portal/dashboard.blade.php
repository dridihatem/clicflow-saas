<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-500/15 text-teal-700 dark:text-teal-300">
                <x-cf-icon name="home" size="md" />
            </span>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500 dark:text-slate-400">{{ __('Client portal') }}</p>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('portal_home') }}</h2>
            </div>
        </div>
    </x-slot>

    <div class="portal-page py-10">
        <div class="mx-auto w-full max-w-12xl space-y-8 px-4 sm:px-6 lg:px-8">
            <section class="portal-hero portal-enter p-6 sm:p-8">
                <span class="portal-hero__orb -end-8 -top-10 h-40 w-40 bg-teal-400/30" aria-hidden="true"></span>
                <span class="portal-hero__orb bottom-0 start-1/3 h-28 w-28 bg-sky-400/25" style="animation-delay: -2.5s" aria-hidden="true"></span>
                <div class="relative flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                    <div class="max-w-2xl">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-teal-700/80 dark:text-teal-300/90">{{ __('portal_welcome_eyebrow') }}</p>
                        <h3 class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                            {{ __('portal_welcome', ['name' => $client->name]) }}
                        </h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ __('portal_dashboard_intro') }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-2 rounded-xl border border-white/60 bg-white/80 px-4 py-2.5 text-xs font-semibold text-slate-800 shadow-sm backdrop-blur transition hover:-translate-y-0.5 hover:bg-white dark:border-slate-600 dark:bg-slate-900/70 dark:text-slate-100 dark:hover:bg-slate-800"
                        onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'product-tour' }))"
                    >
                        <x-cf-icon name="circle-question" size="sm" />
                        {{ __('product_tour_open_short') }}
                    </button>
                </div>
            </section>

            @php
                $pv = $portalVisible ?? array_fill_keys(app(\App\Services\PortalVisibilityService::class)->keys(), true);
                $showWidgets = $pv['dashboard'] ?? true;
                $hasActivityStats = $pv['prospects'] || $pv['appointments'] || $pv['campaigns'] || $pv['bookings'];
            @endphp

            @if ($showWidgets && ($canViewInvoices || $canViewProposals))
                <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @if ($canViewInvoices)
                        <a href="{{ route('portal.payments.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 80ms">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_pending_payment_invoices') }}</p>
                                <x-cf-icon name="clock" variant="tile" tone="amber" class="cf-stat-tile" />
                            </div>
                            <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $pendingPaymentInvoices }}">{{ number_format($pendingPaymentInvoices) }}</span></p>
                            <p class="mt-1 text-sm font-medium text-amber-700 dark:text-amber-300">
                                {{ flowdesk_format_minor((int) $pendingPaymentOutstandingMinor, $currency) }} {{ $currency }}
                            </p>
                        </a>
                    @endif
                    @if ($canViewProposals)
                        <a href="{{ route('portal.proposals.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 140ms">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_pending_acceptance') }}</p>
                                <x-cf-icon name="file-signature" variant="tile" tone="sky" class="cf-stat-tile" />
                            </div>
                            <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $pendingAcceptanceProposals }}">{{ number_format($pendingAcceptanceProposals) }}</span></p>
                        </a>
                    @endif
                    @if ($canViewInvoices)
                        <a href="{{ route('portal.payments.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 200ms">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_total_payments') }}</p>
                                <x-cf-icon name="wallet" variant="tile" tone="emerald" class="cf-stat-tile" />
                            </div>
                            <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white">
                                {{ flowdesk_format_minor((int) $totalPaymentsMinor, $currency) }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ $currency }}</p>
                        </a>
                    @endif
                </section>
            @endif

            @if ($showWidgets && $hasActivityStats)
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @if ($pv['prospects'])
                <a href="{{ route('portal.prospects.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 80ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_prospects') }}</p>
                        <x-cf-icon name="prospects" variant="tile" tone="orange" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $prospectCounts['total'] }}">{{ number_format($prospectCounts['total']) }}</span></p>
                </a>
                @endif
                @if ($pv['prospects'])
                <a href="{{ route('portal.prospects.index', ['status' => 'nouveau']) }}" class="portal-stat portal-enter block" style="--portal-delay: 120ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_prospects_new') }}</p>
                        <x-cf-icon name="star" variant="tile" tone="blue" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $prospectCounts['nouveau'] }}">{{ number_format($prospectCounts['nouveau']) }}</span></p>
                </a>
                @endif
                @if ($pv['appointments'])
                <a href="{{ route('portal.prospects.appointments') }}" class="portal-stat portal-enter block" style="--portal-delay: 160ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_prospects_rdv') }}</p>
                        <x-cf-icon name="calendar-check" variant="tile" tone="amber" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $prospectCounts['rdv'] }}">{{ number_format($prospectCounts['rdv']) }}</span></p>
                </a>
                @endif
                @if ($pv['prospects'])
                <a href="{{ route('portal.prospects.index', ['status' => 'a_rappeler']) }}" class="portal-stat portal-enter block" style="--portal-delay: 200ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_prospects_callback') }}</p>
                        <x-cf-icon name="phone" variant="tile" tone="rose" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) $prospectCounts['rappeler'] }}">{{ number_format($prospectCounts['rappeler']) }}</span></p>
                </a>
                @endif
                @if ($pv['campaigns'])
                <a href="{{ route('portal.campaigns.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 240ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_campaigns') }}</p>
                        <x-cf-icon name="marketing" variant="tile" tone="violet" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) ($prospectCounts['campaigns'] ?? 0) }}">{{ number_format($prospectCounts['campaigns'] ?? 0) }}</span></p>
                </a>
                @endif
                @if ($pv['bookings'])
                <a href="{{ route('portal.bookings.index') }}" class="portal-stat portal-enter block" style="--portal-delay: 280ms">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('portal_stat_bookings') }}</p>
                        <x-cf-icon name="calendar" variant="tile" tone="emerald" class="cf-stat-tile" />
                    </div>
                    <p class="mt-4 text-3xl font-semibold tabular-nums tracking-tight text-slate-900 dark:text-white"><span x-count-up="{{ (int) ($prospectCounts['bookings'] ?? 0) }}">{{ number_format($prospectCounts['bookings'] ?? 0) }}</span></p>
                </a>
                @endif
            </section>
            @endif

            <section>
                <div class="mb-3 flex items-end justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_quick_access') }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('portal_quick_access_help') }}</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @if ($pv['prospects'])
                    <x-portal.nav-card
                            :href="route('portal.prospects.index')"
                            icon="fa-address-book"
                            :title="__('portal_nav_prospects')"
                            :description="__('portal_prospects_intro')"
                            tone="bg-orange-500/12 text-orange-700 dark:text-orange-300"
                            :delay="40"
                        />
                    @endif
                    @if ($pv['campaigns'])
                    <x-portal.nav-card
                            :href="route('portal.campaigns.index')"
                            icon="fa-bullhorn"
                            :title="__('portal_nav_campaigns')"
                            :description="__('portal_campaigns_intro')"
                            tone="bg-violet-500/12 text-violet-700 dark:text-violet-300"
                            :delay="45"
                        />
                    @endif
                    @if ($pv['bookings'])
                    <x-portal.nav-card
                            :href="route('portal.bookings.index')"
                            icon="fa-calendar-days"
                            :title="__('portal_nav_bookings')"
                            :description="__('portal_bookings_intro')"
                            tone="bg-emerald-500/12 text-emerald-700 dark:text-emerald-300"
                            :delay="50"
                        />
                    @endif
                    @if ($pv['assistant'])
                    <x-portal.nav-card
                            :href="route('portal.assistant.index')"
                            icon="fa-wand-magic-sparkles"
                            :title="__('portal_nav_assistant')"
                            :description="__('portal_ai_intro')"
                            tone="bg-orange-500/12 text-orange-700 dark:text-orange-300"
                            :delay="55"
                        />
                    @endif
                    @if ($pv['support'])
                    <x-portal.nav-card
                            :href="route('tickets.create')"
                            icon="fa-headset"
                            :title="__('portal_nav_support')"
                            :description="__('Tickets')"
                            tone="bg-orange-500/12 text-orange-700 dark:text-orange-300"
                            :delay="60"
                        />
                    @endif
                    @can('portal.view_projects')
                        @if ($pv['projects'])
                        <x-portal.nav-card
                            :href="route('portal.projects.index')"
                            icon="fa-folder-open"
                            :title="__('My projects')"
                            :description="__('portal_card_projects')"
                            tone="bg-teal-500/12 text-teal-700 dark:text-teal-300"
                            :delay="80"
                        />
                        @endif
                    @endcan
                    @can('portal.request_quote')
                        @if ($pv['quote_requests'])
                        <x-portal.nav-card
                            :href="route('portal.quote-requests.create')"
                            icon="fa-file-circle-plus"
                            :title="__('portal_quote_requests')"
                            :description="__('portal_card_quote_request')"
                            tone="bg-amber-500/12 text-amber-700 dark:text-amber-300"
                            :delay="120"
                        />
                        @endif
                    @endcan
                    @can('portal.view_proposals')
                        @if ($pv['proposals'])
                        <x-portal.nav-card
                            :href="route('portal.proposals.index')"
                            icon="fa-file-contract"
                            :title="__('Quotes')"
                            :description="__('portal_card_quotes')"
                            tone="bg-sky-500/12 text-sky-700 dark:text-sky-300"
                            :delay="160"
                        />
                        @endif
                    @endcan
                    @if (auth()->user()?->can('portal.view_invoices') || auth()->user()?->can('portal.view_payments'))
                        @if ($pv['invoices'])
                        <x-portal.nav-card
                            :href="route('portal.payments.index')"
                            icon="fa-file-invoice-dollar"
                            :title="__('Invoices')"
                            :description="__('portal_card_invoices')"
                            tone="bg-emerald-500/12 text-emerald-700 dark:text-emerald-300"
                            :delay="200"
                        />
                        @endif
                    @endif
                    @if ($pv['messages'])
                    <x-portal.nav-card
                        :href="route('chat.index')"
                        icon="fa-comments"
                        :title="__('Messages')"
                        :description="__('portal_card_messages')"
                        tone="bg-slate-500/12 text-slate-700 dark:text-slate-300"
                        :delay="240"
                    />
                    @endif
                </div>
            </section>

            @if ($showWidgets && $sharedNotes->isNotEmpty())
                <section class="portal-surface portal-enter p-6" style="--portal-delay: 220ms">
                    <div class="flex items-center gap-2">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-teal-500/12 text-teal-700 dark:text-teal-300">
                            <x-cf-icon name="note-sticky" size="md" />
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_shared_notes_heading') }}</h3>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('portal_shared_notes_intro') }}</p>
                        </div>
                    </div>
                    <ul class="mt-5 space-y-3">
                        @foreach ($sharedNotes as $note)
                            <li class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-4 transition hover:border-teal-200/70 hover:bg-white dark:border-slate-700/80 dark:bg-slate-900/40 dark:hover:border-teal-800/50">
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                    <span class="inline-flex rounded-full bg-teal-500/12 px-2 py-0.5 font-medium text-teal-800 dark:text-teal-200">{{ $note->note_type?->label() }}</span>
                                    <span>{{ $note->authorLabel($client->company?->name) }}</span>
                                    <time datetime="{{ $note->noted_on->toDateString() }}">{{ $note->noted_on->format('Y-m-d') }}@if ($note->start_time) · {{ \Illuminate\Support\Str::substr((string) $note->start_time, 0, 5) }}@endif</time>
                                </div>
                                @if ($note->title)
                                    <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">{{ $note->title }}</p>
                                @endif
                                <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $note->body }}</p>
                                @if ($note->meeting_url)
                                    <a href="{{ $note->meeting_url }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-teal-700 hover:underline dark:text-teal-300">
                                        {{ __('client_meeting_join_button') }}
                                        <x-cf-icon name="arrow-up-right-from-square" size="sm" />
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($showWidgets && $pv['appointments'] && ! empty($flowdeskCalendarNav))
                <div class="portal-enter" style="--portal-delay: 260ms">
                    <x-calendar-preview-panel :preview="$flowdeskCalendarNav" />
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
