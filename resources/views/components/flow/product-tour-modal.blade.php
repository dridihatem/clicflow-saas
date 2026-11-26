@php
    $user = auth()->user();
    if (! $user) {
        return;
    }

    $isStaff = $user->hasAnyRole(['company_admin', 'team_member']);
    $isClient = $user->hasRole('client') && ! $user->hasAnyRole(['company_admin', 'team_member', 'business_provider']);
    $isProvider = $user->hasRole('business_provider') && ! $user->hasAnyRole(['company_admin', 'team_member']);

    if (! $isStaff && ! $isClient && ! $isProvider) {
        return;
    }

    $tourVisible = app(\App\Services\PortalVisibilityService::class)->mapForUser($user);
    $audience = $isStaff ? 'staff' : ($isClient ? 'client' : 'provider');
    $appearance = is_array($user->appearance_json) ? $user->appearance_json : [];
    $tourHidden = $user->tour_dismissed_at !== null
        || (bool) ($appearance['hide_product_tour'] ?? false);
    $brand = (string) config('flowdesk.brand_name', config('app.name'));
    $nova = (string) config('flowdesk.ai_assistant_brand_name', 'Nova');
    $supportEmail = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
    $supportMailto = is_string($supportEmail) && filter_var($supportEmail, FILTER_VALIDATE_EMAIL)
        ? 'mailto:'.$supportEmail
        : null;

    $onHome = match ($audience) {
        'client' => request()->routeIs('portal.dashboard'),
        'provider' => request()->routeIs('provider.dashboard'),
        default => request()->routeIs('dashboard'),
    };
    $autoOpen = (bool) ($autoOpen ?? (! $tourHidden && $onHome));

    if ($audience === 'client') {
        $slides = [
            [
                'key' => 'welcome',
                'title' => __('product_tour_client_welcome_title', ['brand' => $brand]),
                'body' => __('product_tour_client_welcome_body', ['brand' => $brand]),
                'art' => 'welcome',
                'links' => [],
            ],
            [
                'key' => 'work',
                'title' => __('product_tour_client_work_title'),
                'body' => __('product_tour_client_work_body'),
                'art' => 'clients',
                'links' => array_filter([
                    Route::has('portal.projects.index') && $user->can('portal.view_projects') && $tourVisible['projects']
                        ? ['url' => route('portal.projects.index'), 'label' => __('My projects'), 'icon' => 'fa-folder-open']
                        : null,
                    Route::has('portal.calendar') && $tourVisible['appointments']
                        ? ['url' => route('portal.calendar'), 'label' => __('Calendar'), 'icon' => 'fa-calendar']
                        : null,
                ]),
            ],
            [
                'key' => 'billing',
                'title' => __('product_tour_client_billing_title'),
                'body' => __('product_tour_client_billing_body'),
                'art' => 'billing',
                'links' => array_filter([
                    Route::has('portal.proposals.index') && $user->can('portal.view_proposals') && $tourVisible['proposals']
                        ? ['url' => route('portal.proposals.index'), 'label' => __('Quotes'), 'icon' => 'fa-file-signature']
                        : null,
                    Route::has('portal.payments.index') && $tourVisible['invoices']
                        ? ['url' => route('portal.payments.index'), 'label' => __('Invoices'), 'icon' => 'fa-file-invoice-dollar']
                        : null,
                    Route::has('portal.contracts.index') && $tourVisible['contracts']
                        ? ['url' => route('portal.contracts.index'), 'label' => __('Contracts'), 'icon' => 'fa-scroll']
                        : null,
                ]),
            ],
            [
                'key' => 'support',
                'title' => __('product_tour_client_support_title'),
                'body' => __('product_tour_client_support_body', ['brand' => $brand]),
                'art' => 'settings',
                'links' => array_filter([
                    Route::has('tickets.index') && $tourVisible['support']
                        ? ['url' => route('tickets.index'), 'label' => __('Tickets'), 'icon' => 'fa-ticket']
                        : null,
                    Route::has('chat.index') && $tourVisible['messages']
                        ? ['url' => route('chat.index'), 'label' => __('Messages'), 'icon' => 'fa-comments']
                        : null,
                    $supportMailto
                        ? ['url' => $supportMailto, 'label' => __('app_footer_support_link', ['brand' => $brand]), 'icon' => 'fa-life-ring']
                        : null,
                ]),
            ],
            [
                'key' => 'ready',
                'title' => __('product_tour_client_ready_title'),
                'body' => __('product_tour_client_ready_body', ['brand' => $brand]),
                'art' => 'ready',
                'links' => array_filter([
                    Route::has('portal.dashboard')
                        ? ['url' => route('portal.dashboard'), 'label' => __('Client portal'), 'icon' => 'fa-gauge-high']
                        : null,
                ]),
            ],
        ];
    } elseif ($audience === 'provider') {
        $slides = [
            [
                'key' => 'welcome',
                'title' => __('product_tour_provider_welcome_title', ['brand' => $brand]),
                'body' => __('product_tour_provider_welcome_body', ['brand' => $brand]),
                'art' => 'welcome',
                'links' => [],
            ],
            [
                'key' => 'projects',
                'title' => __('product_tour_provider_projects_title'),
                'body' => __('product_tour_provider_projects_body'),
                'art' => 'clients',
                'links' => array_filter([
                    Route::has('provider.projects.index') && $user->can('provider.manage_projects')
                        ? ['url' => route('provider.projects.index'), 'label' => __('Projects'), 'icon' => 'fa-folder-open']
                        : null,
                    Route::has('provider.dashboard')
                        ? ['url' => route('provider.dashboard'), 'label' => __('Dashboard'), 'icon' => 'fa-gauge-high']
                        : null,
                ]),
            ],
            [
                'key' => 'payments',
                'title' => __('product_tour_provider_payments_title'),
                'body' => __('product_tour_provider_payments_body'),
                'art' => 'billing',
                'links' => array_filter([
                    Route::has('provider.remittance-requests.index') && $user->can('provider.view_payments')
                        ? ['url' => route('provider.remittance-requests.index'), 'label' => __('provider_payment_requests'), 'icon' => 'fa-hand-holding-dollar']
                        : null,
                ]),
            ],
            [
                'key' => 'partnership',
                'title' => __('product_tour_provider_partnership_title'),
                'body' => __('product_tour_provider_partnership_body'),
                'art' => 'settings',
                'links' => array_filter([
                    Route::has('provider.partnership.show')
                        ? ['url' => route('provider.partnership.show'), 'label' => __('Partnership details'), 'icon' => 'fa-file-signature']
                        : null,
                    $supportMailto
                        ? ['url' => $supportMailto, 'label' => __('app_footer_support_link', ['brand' => $brand]), 'icon' => 'fa-life-ring']
                        : null,
                ]),
            ],
            [
                'key' => 'ready',
                'title' => __('product_tour_provider_ready_title'),
                'body' => __('product_tour_provider_ready_body', ['brand' => $brand]),
                'art' => 'ready',
                'links' => array_filter([
                    Route::has('provider.dashboard')
                        ? ['url' => route('provider.dashboard'), 'label' => __('Provider portal'), 'icon' => 'fa-gauge-high']
                        : null,
                ]),
            ],
        ];
    } else {
        $canClients = $user->can('workspace.manage_clients');
        $canInvoices = $user->can('workspace.manage_invoices');
        $canSettings = $user->can('workspace.manage_settings');
        $canSubscription = $user->can('workspace.manage_subscription');
        $canAssistant = $user->can('workspace.view_dashboard');

        $slides = [
            [
                'key' => 'welcome',
                'title' => __('product_tour_slide_welcome_title', ['brand' => $brand]),
                'body' => __('product_tour_slide_welcome_body', ['brand' => $brand]),
                'art' => 'welcome',
                'links' => [],
            ],
            [
                'key' => 'clients',
                'title' => __('product_tour_slide_clients_title'),
                'body' => __('product_tour_slide_clients_body'),
                'art' => 'clients',
                'links' => array_values(array_filter([
                    $canClients && Route::has('clients.index') ? ['url' => route('clients.index'), 'label' => __('Clients'), 'icon' => 'fa-users'] : null,
                    $canClients && Route::has('prospects.index') ? ['url' => route('prospects.index'), 'label' => __('Prospects'), 'icon' => 'fa-user-plus'] : null,
                ])),
            ],
            [
                'key' => 'billing',
                'title' => __('product_tour_slide_billing_title'),
                'body' => __('product_tour_slide_billing_body'),
                'art' => 'billing',
                'links' => array_values(array_filter([
                    $canInvoices && Route::has('proposals.index') ? ['url' => route('proposals.index'), 'label' => __('Quotes'), 'icon' => 'fa-file-signature'] : null,
                    $canInvoices && Route::has('invoices.index') ? ['url' => route('invoices.index'), 'label' => __('Invoices'), 'icon' => 'fa-file-invoice-dollar'] : null,
                    $canClients && Route::has('contracts.index') ? ['url' => route('contracts.index'), 'label' => __('Contracts'), 'icon' => 'fa-scroll'] : null,
                ])),
            ],
            [
                'key' => 'nova',
                'title' => __('product_tour_slide_nova_title', ['name' => $nova]),
                'body' => __('product_tour_slide_nova_body', ['name' => $nova]),
                'art' => 'nova',
                'links' => array_values(array_filter([
                    $canAssistant && Route::has('assistant.index') ? ['url' => route('assistant.index'), 'label' => __('product_tour_link_nova_help', ['name' => $nova]), 'icon' => 'fa-robot'] : null,
                    $canSettings && Route::has('settings.ai-agent') ? ['url' => route('settings.ai-agent'), 'label' => __('product_tour_link_ai_settings'), 'icon' => 'fa-sliders'] : null,
                ])),
            ],
            [
                'key' => 'settings',
                'title' => __('product_tour_slide_settings_title'),
                'body' => __('product_tour_slide_settings_body'),
                'art' => 'settings',
                'links' => array_values(array_filter([
                    $canSettings && Route::has('settings.workspace') ? ['url' => route('settings.workspace'), 'label' => __('Company settings'), 'icon' => 'fa-gear'] : null,
                    $canSettings && Route::has('settings.modules') ? ['url' => route('settings.modules'), 'label' => __('Modules'), 'icon' => 'fa-puzzle-piece'] : null,
                    $canSubscription && Route::has('billing.index') ? ['url' => route('billing.index'), 'label' => __('Plan subscription'), 'icon' => 'fa-credit-card'] : null,
                ])),
            ],
            [
                'key' => 'ready',
                'title' => __('product_tour_slide_ready_title'),
                'body' => __('product_tour_slide_ready_body', ['brand' => $brand, 'name' => $nova]),
                'art' => 'ready',
                'links' => array_values(array_filter([
                    Route::has('dashboard') ? ['url' => route('dashboard'), 'label' => __('Dashboard'), 'icon' => 'fa-gauge-high'] : null,
                    $canAssistant && Route::has('assistant.index') ? ['url' => route('assistant.index'), 'label' => __('AI assistant'), 'icon' => 'fa-robot'] : null,
                    $canSettings && Route::has('settings.workspace') ? ['url' => route('settings.workspace'), 'label' => __('Company settings'), 'icon' => 'fa-gear'] : null,
                ])),
            ],
        ];
    }
@endphp

<div
    x-data="productTour(@js([
        'dismissUrl' => route('product-tour.dismiss'),
        'autoOpen' => $autoOpen,
        'dismissed' => $tourHidden,
        'userId' => (string) $user->id,
        'total' => count($slides),
        'labels' => [
            'next' => __('Next'),
            'terminate' => __('product_tour_terminate'),
        ],
    ]))"
    x-on:open-modal.window="if ($event.detail === 'product-tour') open(true)"
    x-on:keydown.escape.window="if (show) close()"
>
    <div
        x-cloak
        x-show="show"
        x-transition.opacity.duration.200ms
        class="fixed inset-0 z-[80] flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
    >
        <div
            class="fixed inset-0 bg-slate-900/65 backdrop-blur-[2px]"
            @click="close()"
        ></div>

        <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="product-tour-title"
            class="relative z-10 w-full max-w-3xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl shadow-slate-900/25 dark:border-slate-700 dark:bg-slate-900"
            @click.stop
            x-show="show"
            x-transition:enter="ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        >
            <div x-show="loading" x-cloak class="flex min-h-[22rem] flex-col items-center justify-center gap-4 px-8 py-16">
                <div class="h-12 w-12 animate-spin rounded-full border-4 border-cyan-200 border-t-cyan-500" aria-hidden="true"></div>
                <p class="text-sm font-medium text-slate-600 dark:text-slate-300">{{ __('product_tour_loading') }}</p>
            </div>

            <div x-show="!loading" x-cloak>
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-cyan-600 dark:text-cyan-400">{{ __('product_tour_eyebrow') }}</p>
                        <h2 id="product-tour-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ __('product_tour_title', ['brand' => $brand]) }}</h2>
                    </div>
                    <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800" @click="close()" aria-label="{{ __('Close') }}">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="px-5 py-5 sm:px-8 sm:py-6">
                    @foreach ($slides as $i => $slide)
                        <div x-show="step === {{ $i }}" class="grid gap-6 sm:grid-cols-[1.05fr_0.95fr] sm:items-center">
                            <div class="order-2 sm:order-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('product_tour_step', ['current' => $i + 1, 'total' => count($slides)]) }}</p>
                                <h3 class="mt-2 text-xl font-semibold tracking-tight text-slate-900 dark:text-white sm:text-2xl">{{ $slide['title'] }}</h3>
                                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $slide['body'] }}</p>

                                @if ($slide['links'] !== [])
                                    <div class="mt-5 flex flex-wrap gap-2">
                                        @foreach ($slide['links'] as $link)
                                            <a
                                                href="{{ $link['url'] }}"
                                                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-cyan-300 hover:bg-cyan-50 hover:text-cyan-900 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-cyan-700 dark:hover:bg-cyan-950/40"
                                            >
                                                <i class="fa-solid {{ $link['icon'] }} text-[0.7rem] text-cyan-600" aria-hidden="true"></i>
                                                {{ $link['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="order-1 sm:order-2">
                                <div class="mx-auto flex aspect-[4/3] max-w-sm items-center justify-center rounded-2xl border border-slate-100 bg-gradient-to-br from-slate-50 via-white to-cyan-50 p-4 dark:border-slate-700 dark:from-slate-800 dark:via-slate-900 dark:to-cyan-950/40">
                                    @include('components.flow.product-tour-art', ['art' => $slide['art']])
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                        @click="dismissForever()"
                    >
                        <i class="fa-solid fa-eye-slash text-[10px]" aria-hidden="true"></i>
                        {{ __('product_tour_dont_show_again') }}
                    </button>

                    <div class="flex flex-wrap items-center justify-end gap-2">
                        <div class="me-auto flex items-center gap-1.5 sm:me-3" aria-hidden="true">
                            <template x-for="i in total" :key="i">
                                <span
                                    class="inline-block h-1.5 rounded-full transition-all"
                                    :class="(i - 1) === step ? 'w-4 bg-cyan-500' : 'w-1.5 bg-slate-300 dark:bg-slate-600'"
                                ></span>
                            </template>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
                            @click="close()"
                        >{{ __('Close') }}</button>
                        <button
                            type="button"
                            class="rounded-lg px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
                            @click="terminate()"
                        >{{ __('product_tour_terminate') }}</button>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
                            @click="prev()"
                            :disabled="step === 0"
                            :class="{ 'opacity-40 pointer-events-none': step === 0 }"
                        >{{ __('Back') }}</button>
                        <button
                            type="button"
                            class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400"
                            @click="next()"
                            x-text="isLast ? labels.terminate : labels.next"
                        ></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
