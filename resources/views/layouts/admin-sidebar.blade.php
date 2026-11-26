@php($flowdeskTheme = $flowdeskTheme ?? [])
@php($navActive = 'relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition')
@php($navInactive = 'text-slate-200/90 hover:bg-white/10 hover:text-white')
@php($navOn = 'bg-[#EA580C] text-white shadow-md shadow-[rgba(234,88,12,0.28)]')
@php($iconMuted = 'text-slate-300 group-hover:text-[#FDBA74]')
@php($iconOn = 'text-white')
<div class="min-h-screen bg-[var(--flow-surface-muted)] text-[var(--flow-text)]">
    <div class="flex w-full">
        <aside
            class="flow-sidebar-aside sticky top-0 hidden h-screen w-72 shrink-0 flex-col px-4 py-6 md:flex"
        >
            <x-flow.brand-tile :href="route('admin.dashboard')" size="lg" class="self-stretch" />

            <div class="mt-6 min-h-0 flex-1 overflow-y-auto overscroll-y-contain pe-1 [-ms-overflow-style:none] [scrollbar-width:thin] [scrollbar-color:rgba(234,88,12,0.35)_transparent] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-[rgba(234,88,12,0.35)]">
            <div class="mt-2">
                <div class="px-2 text-[10px] font-bold uppercase tracking-[0.22em] text-[#FDBA74]">
                    {{ __('Platform') }}
                </div>
                <nav class="mt-3 space-y-1">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.dashboard') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.dashboard') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-chart-bar" size="md" class="w-5 {{ request()->routeIs('admin.dashboard') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Overview') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.reports.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.reports.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.reports.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-file-lines" size="md" class="w-5 {{ request()->routeIs('admin.reports.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Reports') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.companies.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.companies.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.companies.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-building" size="md" class="w-5 {{ request()->routeIs('admin.companies.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Companies') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.users.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.users.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.users.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-users" size="md" class="w-5 {{ request()->routeIs('admin.users.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Users') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.plans.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.plans.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.plans.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-credit-card" size="md" class="w-5 {{ request()->routeIs('admin.plans.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Subscription plans') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.marketplace-modules.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.marketplace-modules.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.marketplace-modules.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-puzzle-piece" size="md" class="w-5 {{ request()->routeIs('admin.marketplace-modules.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('admin_marketplace_modules_title') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.marketplace-orders.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.marketplace-orders.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.marketplace-orders.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-bag-shopping" size="md" class="w-5 {{ request()->routeIs('admin.marketplace-orders.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('admin_marketplace_orders_title') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.subscription-orders.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.subscription-orders.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.subscription-orders.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-file-invoice-dollar" size="md" class="w-5 {{ request()->routeIs('admin.subscription-orders.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('admin_subscription_orders_title') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.payments.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.payments.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.payments.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-money-bill-1" size="md" class="w-5 {{ request()->routeIs('admin.payments.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Invoice payments') }}</span>
                    </a>
                </nav>
            </div>

            <div class="mt-8">
                <div class="px-2 text-[10px] font-bold uppercase tracking-[0.22em] text-[#FDBA74]">
                    {{ __('Settings') }}
                </div>
                <nav class="mt-3 space-y-1">
                    <a
                        href="{{ route('admin.platform-appearance.edit') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.platform-appearance.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.platform-appearance.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-pen-to-square" size="md" class="w-5 {{ request()->routeIs('admin.platform-appearance.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Default workspace theme') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.themes.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.themes.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.themes.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-palette" size="md" class="w-5 {{ request()->routeIs('admin.themes.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Theme library') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.invoice-pdf-templates.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.invoice-pdf-templates.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.invoice-pdf-templates.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-file-pdf" size="md" class="w-5 {{ request()->routeIs('admin.invoice-pdf-templates.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('admin_invoice_pdf_templates_nav') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.email-template-models.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.email-template-models.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.email-template-models.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-envelope-open" size="md" class="w-5 {{ request()->routeIs('admin.email-template-models.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('admin_email_template_models_nav') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.payment-gateways.edit') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.payment-gateways.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.payment-gateways.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-plug-circle-bolt" size="md" class="w-5 {{ request()->routeIs('admin.payment-gateways.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Payment gateways') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.platform-settings.edit') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.platform-settings.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.platform-settings.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-sliders" size="md" class="w-5 {{ request()->routeIs('admin.platform-settings.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Platform settings') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.mcp-settings.show') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.mcp-settings.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.mcp-settings.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="wand-magic-sparkles" size="md" class="w-5 {{ request()->routeIs('admin.mcp-settings.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('mcp_admin_title') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.developer-docs.index') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.developer-docs.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.developer-docs.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-book" size="md" class="w-5 {{ request()->routeIs('admin.developer-docs.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Developer documentation') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.profile.edit') }}"
                        class="{{ $navActive }} {{ request()->routeIs('admin.profile.*') ? $navOn : $navInactive }} group"
                    >
                        <span
                            class="absolute start-0 top-1/2 h-6 w-1 -translate-y-1/2 rounded-e-full bg-[#EA580C] {{ request()->routeIs('admin.profile.*') ? 'opacity-100' : 'opacity-0' }}"
                        ></span>
                        <x-cf-icon name="fa-user" size="md" class="w-5 {{ request()->routeIs('admin.profile.*') ? $iconOn : $iconMuted }}" />
                        <span class="min-w-0 flex-1 leading-snug line-clamp-2">{{ __('Profile') }}</span>
                    </a>
                </nav>
            </div>
            </div>

            <div class="mt-4 shrink-0 pt-4">
                <div class="cf-admin-signed-in rounded-2xl border border-white/10 bg-white/[0.07] p-3">
                    <div class="text-[10px] font-bold uppercase tracking-widest text-[#FDBA74]">{{ __('Signed in') }}</div>
                    <div class="mt-2 truncate text-sm font-semibold text-white">{{ Auth::guard('admin')->user()->name }}</div>
                    <div class="mt-0.5 truncate text-xs text-slate-300">{{ Auth::guard('admin')->user()->email }}</div>
                    <a
                        href="{{ route('admin.profile.edit') }}"
                        class="mt-3 inline-flex items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-semibold text-slate-200 hover:bg-white/10 hover:text-[#FDBA74] {{ request()->routeIs('admin.profile.*') ? 'bg-white/10 text-[#FDBA74]' : '' }}"
                    >
                        <x-cf-icon name="user" size="md" />
                        {{ __('Profile') }}
                    </a>
                </div>
            </div>
        </aside>

        <div class="flow-workspace-main flex min-h-screen min-w-0 flex-1 flex-col">
            <div
                class="flow-chrome-topbar hidden items-center justify-between gap-3 border-b px-4 py-3 backdrop-blur md:flex"
            >
                <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">{{ __('Platform admin') }}</div>
                <div class="flex flex-wrap items-center justify-end gap-2">
                    <a
                        href="{{ route('admin.dashboard') }}"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-[rgba(234,88,12,0.12)] text-[#EA580C] dark:bg-[#1A2238] dark:text-white' : 'text-slate-700 hover:bg-orange-50 hover:text-[#C2410C] dark:text-slate-400 dark:hover:bg-[#1A2238] dark:hover:text-white' }}"
                        title="{{ __('Admin dashboard') }}"
                    >
                        <x-cf-icon name="gauge-high" size="md" />
                        <span class="hidden lg:inline">{{ __('Dashboard') }}</span>
                    </a>
                    <a
                        href="{{ route('admin.profile.edit') }}"
                        class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.profile.*') ? 'bg-[rgba(234,88,12,0.12)] text-[#EA580C] dark:bg-[#1A2238] dark:text-white' : 'text-slate-700 hover:bg-orange-50 hover:text-[#C2410C] dark:text-slate-400 dark:hover:bg-[#1A2238] dark:hover:text-white' }}"
                        title="{{ __('Profile') }}"
                    >
                        <x-cf-icon name="user" size="md" />
                        <span class="hidden lg:inline">{{ __('Profile') }}</span>
                    </a>

                    <form method="POST" action="{{ route('locale.update') }}" class="inline-flex">
                        @csrf
                        <label class="sr-only" for="admin_topbar_locale">{{ __('Language') }}</label>
                        <select
                            id="admin_topbar_locale"
                            name="locale"
                            class="flow-chrome-select rounded-xl border px-3 py-2 text-sm font-semibold"
                            onchange="this.form.submit()"
                        >
                            @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                                <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                            @endforeach
                        </select>
                    </form>

                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-rose-500/10 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-500/15 dark:hover:text-rose-300"
                            title="{{ __('Log out') }}"
                        >
                            <x-cf-icon name="logout" size="md" />
                            <span class="hidden sm:inline">{{ __('Log out') }}</span>
                        </button>
                    </form>
                </div>
            </div>
            <div
                class="md:hidden"
                x-data="{ open: false }"
                @keydown.escape.window="open = false"
            >
            <div
                class="flow-chrome-topbar flex items-center justify-between gap-2 border-b px-4 py-3 backdrop-blur"
            >
                <x-flow.brand-tile :href="route('admin.dashboard')" size="sm" />
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-800 shadow-sm hover:border-orange-200 hover:text-[#EA580C]"
                        @click="open = !open"
                        :aria-expanded="open.toString()"
                        aria-controls="admin-mobile-nav"
                        :aria-label="open ? @js(__('Close menu')) : @js(__('Open menu'))"
                    >
                        <x-cf-icon name="bars" size="md" x-show="!open" />
                        <x-cf-icon name="xmark" size="md" x-cloak x-show="open" />
                    </button>
                    <form method="POST" action="{{ route('locale.update') }}">
                        @csrf
                        <label class="sr-only" for="admin_mobile_locale">{{ __('Language') }}</label>
                        <select
                            id="admin_mobile_locale"
                            name="locale"
                            class="flow-chrome-select max-w-[7.5rem] rounded-lg border px-2 py-1.5 text-xs font-semibold"
                            onchange="this.form.submit()"
                        >
                            @foreach (config('flowdesk.locales', ['fr', 'en', 'es', 'ar']) as $loc)
                                <option value="{{ $loc }}" @selected(app()->getLocale() === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                            @endforeach
                        </select>
                    </form>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-500/10 hover:text-rose-600"
                            title="{{ __('Log out') }}"
                            aria-label="{{ __('Log out') }}"
                        >
                            <x-cf-icon name="logout" size="md" />
                        </button>
                    </form>
                </div>
            </div>
            <nav
                id="admin-mobile-nav"
                x-cloak
                x-show="open"
                x-transition
                class="border-b border-slate-200 bg-white px-3 py-3 shadow-sm"
            >
                <p class="px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#C2410C]">{{ __('Platform') }}</p>
                @foreach ([
                    ['admin.dashboard', 'admin.dashboard', 'fa-solid fa-chart-bar', __('Overview')],
                    ['admin.reports.index', 'admin.reports.*', 'fa-solid fa-file-lines', __('Reports')],
                    ['admin.companies.index', 'admin.companies.*', 'fa-solid fa-building', __('Companies')],
                    ['admin.users.index', 'admin.users.*', 'fa-solid fa-users', __('Users')],
                    ['admin.plans.index', 'admin.plans.*', 'fa-solid fa-credit-card', __('Subscription plans')],
                    ['admin.marketplace-modules.index', 'admin.marketplace-modules.*', 'fa-solid fa-puzzle-piece', __('admin_marketplace_modules_title')],
                    ['admin.marketplace-orders.index', 'admin.marketplace-orders.*', 'fa-solid fa-bag-shopping', __('admin_marketplace_orders_title')],
                    ['admin.subscription-orders.index', 'admin.subscription-orders.*', 'fa-solid fa-file-invoice-dollar', __('admin_subscription_orders_title')],
                    ['admin.payments.index', 'admin.payments.*', 'fa-solid fa-money-bill-1', __('Invoice payments')],
                ] as $item)
                    <a href="{{ route($item[0]) }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold', 'bg-[#EA580C] text-white' => request()->routeIs($item[1]), 'text-slate-800 hover:bg-orange-50 hover:text-[#C2410C]' => ! request()->routeIs($item[1])])>
                        <x-cf-icon :name="$item[2]" size="md" class="w-5" />
                        <span>{{ $item[3] }}</span>
                    </a>
                @endforeach
                <p class="mt-3 px-3 pb-1 text-[10px] font-bold uppercase tracking-[0.18em] text-[#C2410C]">{{ __('Settings') }}</p>
                @foreach ([
                    ['admin.platform-appearance.edit', 'admin.platform-appearance.*', 'fa-solid fa-pen-to-square', __('Workspace theme')],
                    ['admin.themes.index', 'admin.themes.*', 'fa-solid fa-palette', __('Theme library')],
                    ['admin.invoice-pdf-templates.index', 'admin.invoice-pdf-templates.*', 'fa-solid fa-file-pdf', __('admin_invoice_pdf_templates_nav')],
                    ['admin.email-template-models.index', 'admin.email-template-models.*', 'fa-solid fa-envelope-open', __('admin_email_template_models_nav')],
                    ['admin.payment-gateways.edit', 'admin.payment-gateways.*', 'fa-solid fa-plug-circle-bolt', __('Payment gateways')],
                    ['admin.platform-settings.edit', 'admin.platform-settings.*', 'fa-solid fa-sliders', __('Platform settings')],
                    ['admin.mcp-settings.show', 'admin.mcp-settings.*', 'wand-magic-sparkles', __('mcp_admin_title')],
                    ['admin.developer-docs.index', 'admin.developer-docs.*', 'fa-solid fa-book', __('Developer documentation')],
                    ['admin.profile.edit', 'admin.profile.*', 'fa-solid fa-user', __('Profile')],
                ] as $item)
                    <a href="{{ route($item[0]) }}" @class(['flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold', 'bg-[#EA580C] text-white' => request()->routeIs($item[1]), 'text-slate-800 hover:bg-orange-50 hover:text-[#C2410C]' => ! request()->routeIs($item[1])])>
                        <x-cf-icon :name="$item[2]" size="md" class="w-5" />
                        <span>{{ $item[3] }}</span>
                    </a>
                @endforeach
            </nav>
            </div>

            <div class="flex w-full min-w-0 flex-1 flex-col px-4 py-8 sm:px-6 lg:px-8">
                @if (! empty($flowdeskBreadcrumbs ?? []))
                    <div class="mb-6">
                        <x-flow.breadcrumb-bar :items="$flowdeskBreadcrumbs" :back="$flowdeskBreadcrumbBack ?? null" />
                    </div>
                @endif
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-500/25 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-950/40 dark:text-emerald-100">{{ session('status') }}</div>
                @endif
                @if (session('welcome_mail_warning'))
                    <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">{{ session('welcome_mail_warning') }}</div>
                @endif
                <div class="w-full min-w-0 flex-1">
                    {{ $slot }}
                </div>
                <div class="-mx-4 mt-auto pt-8 sm:-mx-6 lg:-mx-8">
                    @include('partials.app-footer')
                </div>
            </div>
        </div>
    </div>
</div>
