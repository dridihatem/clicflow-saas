@php
    $portalUser = $client->user;
    $hasPortal = (bool) $portalUser;
    $isDisabled = $hasPortal && $client->portal_disabled_at !== null;
    $plainPassword = session('portal_plain_password');
@endphp
<section class="flow-panel overflow-hidden border border-orange-200/80 p-0 dark:border-orange-900/40" id="acces-espace-client">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-orange-100 bg-orange-50/80 px-6 py-4 dark:border-orange-900/30 dark:bg-orange-950/30">
        <div>
            <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-orange-700 dark:text-orange-300">{{ __('client_portal_panel_eyebrow') }}</p>
            <h3 class="mt-0.5 text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_portal_panel_heading') }}</h3>
            <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ __('client_portal_panel_intro') }}</p>
        </div>
        @if ($hasPortal)
            @if ($isDisabled)
                <span class="inline-flex items-center rounded-full bg-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-200">{{ __('client_portal_status_disabled') }}</span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200">{{ __('client_portal_status_active') }}</span>
            @endif
        @else
            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">{{ __('client_portal_status_none') }}</span>
        @endif
    </div>

    <div class="space-y-5 p-6" x-data="{ pwd: '', showPwd: true }">
        @if ($plainPassword)
            <div class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-100" role="status">
                <p class="font-semibold">{{ __('client_portal_password_shown_once') }}</p>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <code class="rounded-lg bg-white px-3 py-1.5 font-mono text-base tracking-wide text-slate-900 shadow-sm dark:bg-slate-900 dark:text-white" x-ref="shownPwd">{{ $plainPassword }}</code>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-amber-400 px-2.5 py-1 text-xs font-medium text-amber-900 hover:bg-amber-100 dark:border-amber-600 dark:text-amber-100 dark:hover:bg-amber-900"
                        x-on:click="navigator.clipboard.writeText($refs.shownPwd.textContent)"
                    >
                        <i class="fa-regular fa-copy" aria-hidden="true"></i>
                        {{ __('Copy') }}
                    </button>
                </div>
            </div>
        @endif

        @if ($hasPortal)
            <dl class="grid gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">{{ $portalUser->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('client_portal_last_login') }}</dt>
                    <dd class="mt-0.5 font-medium text-slate-900 dark:text-white">
                        @if ($portalUser->last_login_at)
                            <span class="flowdesk-ltr-num tabular-nums">{{ $portalUser->last_login_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
                        @else
                            {{ __('client_portal_never_logged_in') }}
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Created') }}</dt>
                    <dd class="mt-0.5 tabular-nums text-slate-900 dark:text-white">{{ $portalUser->created_at?->format('d/m/Y') }}</dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-orange-200 bg-orange-50/70 p-4 dark:border-orange-900/40 dark:bg-orange-950/20">
                <div class="flex items-start gap-3">
                    <x-cf-icon name="paper-plane" variant="tile" tone="orange" class="cf-stat-tile" />
                    <div>
                        <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_portal_send_access_heading') }}</h4>
                        <p class="mt-0.5 text-xs text-slate-600 dark:text-slate-400">{{ __('client_portal_send_access_help', ['email' => $portalUser->email]) }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('clients.portal.send-access', $client) }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-700">
                        <x-cf-icon name="envelope" size="sm" />
                        {{ __('client_portal_send_access_button') }}
                    </button>
                </form>
            </div>

            <form method="POST" action="{{ route('clients.portal.password', $client) }}" class="space-y-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                @csrf
                <h4 class="text-sm font-semibold text-slate-900 dark:text-white">{{ __('client_portal_reset_heading') }}</h4>
                <div>
                    <x-input-label for="portal_reset_password" :value="__('client_portal_password_label')" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-text-input id="portal_reset_password" name="password" type="text" class="block min-w-[12rem] flex-1" autocomplete="new-password" x-model="pwd" />
                        <x-secondary-button
                            type="button"
                            class="shrink-0 !normal-case"
                            x-on:click="pwd = (() => { const c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; return Array.from(crypto.getRandomValues(new Uint8Array(12))).map((b) => c[b % c.length]).join(''); })()"
                        >{{ __('client_portal_password_generate') }}</x-secondary-button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('client_portal_password_blank_generates') }}</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="send_credentials" value="1" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500 dark:border-slate-600 dark:bg-slate-900" />
                    {{ __('client_portal_send_credentials_label') }}
                </label>
                <div class="flex flex-wrap gap-2">
                    <x-primary-button type="submit" class="!normal-case">{{ __('client_portal_reset_submit') }}</x-primary-button>
                </div>
            </form>

            <div class="flex flex-wrap gap-2">
                @if ($isDisabled)
                    <form method="POST" action="{{ route('clients.portal.enable', $client) }}" onsubmit="return confirm(@js(__('client_portal_enable_confirm')))">
                        @csrf
                        <x-primary-button type="submit" class="!normal-case">{{ __('client_portal_enable') }}</x-primary-button>
                    </form>
                @else
                    <form method="POST" action="{{ route('clients.portal.disable', $client) }}" onsubmit="return confirm(@js(__('client_portal_disable_confirm')))">
                        @csrf
                        <x-secondary-button type="submit" class="!normal-case">{{ __('client_portal_disable') }}</x-secondary-button>
                    </form>
                @endif
            </div>
        @else
            <form method="POST" action="{{ route('clients.portal.store', $client) }}" class="space-y-3">
                @csrf
                <div>
                    <x-input-label for="portal_create_email" :value="__('Email')" />
                    <x-text-input id="portal_create_email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $client->email)" required autocomplete="off" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="portal_create_password" :value="__('client_portal_password_label')" />
                    <div class="mt-2 flex flex-wrap gap-2">
                        <x-text-input id="portal_create_password" name="password" type="text" class="block min-w-[12rem] flex-1" autocomplete="new-password" x-model="pwd" />
                        <x-secondary-button
                            type="button"
                            class="shrink-0 !normal-case"
                            x-on:click="pwd = (() => { const c = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; return Array.from(crypto.getRandomValues(new Uint8Array(12))).map((b) => c[b % c.length]).join(''); })()"
                        >{{ __('client_portal_password_generate') }}</x-secondary-button>
                    </div>
                    <p class="mt-1 text-xs text-slate-500">{{ __('client_portal_password_blank_generates') }}</p>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                @php $sendMode = old('send_mode', $client->email ? 'link' : 'none'); @endphp
                <fieldset class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/60 p-3 text-sm dark:border-slate-700 dark:bg-slate-800/40">
                    <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('client_portal_send_mode_legend') }}</legend>
                    <label class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                        <input type="radio" name="send_mode" value="link" class="mt-0.5 border-slate-300 text-orange-600 focus:ring-orange-500" @checked($sendMode === 'link') />
                        <span><span class="font-medium">{{ __('client_portal_send_mode_link') }}</span> <span class="text-xs text-emerald-700">({{ __('client_portal_recommended') }})</span></span>
                    </label>
                    <label class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                        <input type="radio" name="send_mode" value="password" class="mt-0.5 border-slate-300 text-orange-600 focus:ring-orange-500" @checked($sendMode === 'password') />
                        <span>{{ __('client_portal_send_credentials_label') }}</span>
                    </label>
                    <label class="flex items-start gap-2 text-slate-700 dark:text-slate-300">
                        <input type="radio" name="send_mode" value="none" class="mt-0.5 border-slate-300 text-orange-600 focus:ring-orange-500" @checked($sendMode === 'none') />
                        <span>{{ __('client_portal_send_mode_none') }}</span>
                    </label>
                </fieldset>
                <x-primary-button type="submit" class="!normal-case">{{ __('client_portal_create_submit') }}</x-primary-button>
            </form>
        @endif
    </div>
</section>
