<x-admin-layout :title="$company->name">
    <div class="mb-6">
        <a
            href="{{ route('admin.companies.index') }}"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]"
        >
            <x-cf-icon name="arrow-left" size="sm" />
            <span>{{ __('Back to companies') }}</span>
        </a>
    </div>

    <div class="mb-6 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#EA580C]">{{ __('Companies') }}</p>
            <h1 class="mt-1 font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold tracking-tight text-slate-900">{{ $company->name }}</h1>
            <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">{{ __('Workspace profile and subscription. Customer-facing quotes and invoices are managed inside this company account.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-sm text-slate-700">
                {{ $company->subdomain }}.{{ ltrim((string) config('flowdesk.tenant_base_domain', 'clicflow.fr'), '.') }}
            </span>
            <a
                href="{{ flowdesk_tenant_url($company, '/billing') }}"
                target="_blank"
                rel="noopener"
                class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]"
            >
                <x-cf-icon name="arrow-up-right-from-square" size="sm" />
                <span>{{ __('Open company billing') }}</span>
            </a>
        </div>
    </div>

    @if (session('welcome_mail_warning'))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            <p class="font-semibold">{{ __('admin_welcome_mail_warning_title') }}</p>
            <p class="mt-1">{{ session('welcome_mail_warning') }}</p>
        </div>
    @endif

    @if (session('welcome_email') || session('welcome_url') || session('welcome_password'))
        <div class="mb-6 rounded-2xl border border-orange-200 bg-[#FFF7ED] p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-[#EA580C]">{{ __('admin_welcome_credentials_title') }}</p>
            <p class="mt-1 text-sm text-orange-950">{{ __('admin_welcome_credentials_help') }}</p>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @if (session('welcome_email'))
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800/70">{{ __('admin_owner_email') }}</dt>
                        <dd class="mt-0.5 font-medium text-slate-900">{{ session('welcome_email') }}</dd>
                    </div>
                @endif
                @if (session('welcome_url'))
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800/70">{{ __('admin_welcome_login_url') }}</dt>
                        <dd class="mt-0.5 break-all font-mono text-slate-900"><a href="{{ session('welcome_url') }}" class="text-[#EA580C] hover:underline" target="_blank" rel="noopener">{{ session('welcome_url') }}</a></dd>
                    </div>
                @endif
                @if (session('welcome_password'))
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-semibold uppercase tracking-wide text-orange-800/70">{{ __('Temporary password') }}</dt>
                        <dd class="mt-0.5 inline-block rounded-lg border border-orange-200 bg-white px-2.5 py-1 font-mono text-base font-bold tracking-wide text-[#9A3412]">{{ session('welcome_password') }}</dd>
                        <p class="mt-1 text-xs text-orange-800/80">{{ __('admin_welcome_password_shown_once') }}</p>
                    </div>
                @endif
            </dl>
        </div>
    @endif

    <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm shadow-slate-900/5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
            <x-cf-icon name="envelope" variant="tile" tone="orange" size="md" />
            <div>
                <h3 class="font-semibold text-slate-900">{{ __('admin_welcome_mail_card_title') }}</h3>
                <p class="text-sm text-slate-500">{{ __('admin_welcome_mail_card_help') }}</p>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.companies.welcome', $company) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-orange-200 bg-[#FFF7ED] px-4 py-2.5 text-sm font-semibold text-[#C2410C] transition hover:bg-[#EA580C] hover:text-white">
                <x-cf-icon name="paper-plane" size="sm" />
                <span>{{ __('admin_welcome_mail_resend') }}</span>
            </button>
        </form>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="flow-panel p-6">
            <h3 class="font-semibold text-slate-900">{{ __('Workspace status') }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ __('Enable or disable this company workspace.') }}</p>

            <form method="POST" action="{{ route('admin.companies.status', $company) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div>
                        <div class="text-sm font-semibold text-slate-900">{{ __('Status') }}</div>
                        @if ($company->is_enabled)
                            <div class="mt-1 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                <x-cf-icon name="fa-circle-check" size="sm" class="" />
                                <span>{{ __('Enabled') }}</span>
                            </div>
                        @else
                            <div class="mt-1 inline-flex items-center gap-2 text-sm font-semibold text-rose-700">
                                <x-cf-icon name="fa-circle-xmark" size="sm" class="" />
                                <span>{{ __('Disabled') }}</span>
                            </div>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <input type="hidden" name="is_enabled" value="0" />
                            <input type="checkbox" name="is_enabled" value="1" class="rounded border-slate-300 text-[var(--flow-primary)] focus:ring-[var(--flow-primary)]" @checked($company->is_enabled) />
                            <span>{{ __('Enabled') }}</span>
                        </label>
                        <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">
                            <x-cf-icon name="fa-floppy-disk" size="sm" class="" />
                            <span>{{ __('Save') }}</span>
                        </button>
                    </div>
                </div>

                <div>
                    <x-input-label for="disabled_reason" :value="__('Disabled reason (optional)')" />
                    <x-text-input id="disabled_reason" name="disabled_reason" class="mt-1 block w-full" :value="old('disabled_reason', $company->disabled_reason)" placeholder="Non-payment, abuse, test account..." />
                    <x-input-error :messages="$errors->get('disabled_reason')" class="mt-2" />
                </div>
            </form>
        </div>

        <div class="flow-panel p-6">
            @php
                $adminNova = \App\Services\NovaVoiceNavigationService::adminVoiceEnabled($company);
                $companyNova = \App\Services\NovaVoiceNavigationService::companyPreferenceEnabled($company);
            @endphp
            <h3 class="flex items-center gap-2 font-semibold text-slate-900"><x-cf-icon name="ai" tone="sky" size="sm" /> {{ __('admin_nova_voice_title') }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ __('admin_nova_voice_help') }}</p>
            <form method="POST" action="{{ route('admin.companies.nova-voice', $company) }}" class="mt-4">
                @csrf
                @method('PUT')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white p-4">
                    <div class="text-sm">
                        <div class="font-semibold {{ $adminNova ? 'text-emerald-700' : 'text-rose-700' }}">{{ $adminNova ? __('admin_nova_voice_on') : __('admin_nova_voice_off') }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ __('admin_nova_voice_company_pref', ['state' => $companyNova ? __('Enabled') : __('Disabled')]) }}</div>
                    </div>
                    <input type="hidden" name="admin_nova_voice_enabled" value="{{ $adminNova ? 0 : 1 }}" />
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold text-white transition {{ $adminNova ? 'bg-rose-600 hover:bg-rose-700' : 'bg-emerald-600 hover:bg-emerald-700' }}">
                        {{ $adminNova ? __('admin_nova_voice_disable') : __('admin_nova_voice_enable') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="flow-panel p-6">
            <h3 class="font-semibold text-slate-900">{{ __('Subscription plan') }}</h3>
            <p class="mt-1 text-sm text-slate-600">{{ __('Change the plan and optionally start a new billing period (activates subscription).') }}</p>

            @if (session('status'))
                <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.companies.plan', $company) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="plan_id" :value="__('Plan')" />
                    <select id="plan_id" name="plan_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm">
                        <option value="">{{ __('No plan') }}</option>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) old('plan_id', $company->plan_id) === (string) $plan->id)>
                                {{ $plan->name }} ({{ $plan->slug }})
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('plan_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="period_months" :value="__('Billing period')" />
                    <select id="period_months" name="period_months" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm" @if ($activeSubscription?->isEnded()) required @endif>
                        @unless ($activeSubscription?->isEnded())
                            <option value="">{{ __('Do not change period') }}</option>
                        @endunless
                        @foreach ($periodOptions as $months)
                            <option value="{{ $months }}" @selected((string) old('period_months', $activeSubscription?->isEnded() ? '12' : '') === (string) $months)>
                                {{ $months }} {{ $months === 1 ? __('month') : __('months') }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">{{ __('Selecting a period activates/renews the subscription from today and clears trial state.') }}</p>
                    @if ($activeSubscription?->isEnded())
                        <p class="mt-1 text-xs font-semibold text-amber-700">{{ __('Period ended — choose a new billing period to renew.') }}</p>
                    @endif
                    <x-input-error :messages="$errors->get('period_months')" class="mt-2" />
                </div>

                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="hidden" name="plan_locked" value="0" />
                    <input type="checkbox" name="plan_locked" value="1" class="rounded border-slate-300 text-[var(--flow-primary)] focus:ring-[var(--flow-primary)]" @checked(old('plan_locked', $company->plan_locked) ? true : false) />
                    <span>{{ __('Lock plan (disable subscription auto-sync)') }}</span>
                </label>

                <div>
                    <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">
                        <x-cf-icon name="fa-floppy-disk" size="sm" class="" />
                        <span>{{ __('Save plan') }}</span>
                    </button>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-600">
                    <div class="font-semibold text-slate-700">{{ __('Current plan') }}</div>
                    <div class="mt-1">
                        <span class="font-semibold text-slate-900">{{ $company->plan?->name ?? '—' }}</span>
                        @if ($company->plan)
                            <span class="ms-2 font-mono text-slate-500">{{ $company->plan->slug }}</span>
                        @endif
                        @if ($company->plan_locked)
                            <span class="ms-2 inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700 ring-1 ring-inset ring-amber-200">{{ __('Locked') }}</span>
                        @endif
                    </div>
                    @if ($activeSubscription)
                        <div class="mt-3 grid gap-1 sm:grid-cols-2">
                            <div>
                                <span class="text-slate-500">{{ __('Billing period') }}:</span>
                                <span class="ms-1 font-semibold text-slate-900">
                                    @if ($currentPeriodMonths)
                                        {{ $currentPeriodMonths }} {{ $currentPeriodMonths === 1 ? __('month') : __('months') }}
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                            <div>
                                <span class="text-slate-500">{{ __('Period ends') }}:</span>
                                <span class="ms-1 font-semibold text-slate-900">
                                    {{ $activeSubscription->current_period_end?->format('Y-m-d') ?? '—' }}
                                </span>
                            </div>
                        </div>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="mt-6 flow-panel p-6">
        <h3 class="font-semibold text-slate-900">{{ __('Contact company') }}</h3>
        <p class="mt-1 text-sm text-slate-600">{{ __('Send a message to the company activity feed and optionally push it to ClicFlow mobile devices via FCM.') }}</p>

        <form method="POST" action="{{ route('admin.companies.notice', $company) }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <x-input-label for="notice_title" :value="__('Title')" />
                <x-text-input id="notice_title" name="title" class="mt-1 block w-full" :value="old('title')" placeholder="{{ __('ClicFlow notice') }}" />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="notice_message" :value="__('Message')" />
                <textarea id="notice_message" name="message" rows="4" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm" placeholder="Maintenance notice, billing reminder, onboarding info...">{{ old('message') }}</textarea>
                <x-input-error :messages="$errors->get('message')" class="mt-2" />
            </div>
            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="hidden" name="send_push" value="0" />
                <input type="checkbox" name="send_push" value="1" class="rounded border-slate-300 text-[var(--flow-primary)] focus:ring-[var(--flow-primary)]" @checked(old('send_push', true)) />
                <span>{{ __('Also send push notification (FCM / Expo)') }}</span>
            </label>
            <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">
                <x-cf-icon name="fa-paper-plane" size="sm" class="" />
                <span>{{ __('Send notice') }}</span>
            </button>
        </form>
    </div>

    <div class="mt-6 flow-panel p-6">
        <h3 class="font-semibold text-slate-900">{{ __('Remove company') }}</h3>
        <p class="mt-1 text-sm text-slate-600">
            {{ __('This permanently deletes the workspace and everything in it: all users, projects, tasks, files, clients, proposals, invoices, payments and subscriptions. This cannot be undone. To confirm, type the subdomain:') }}
            <span class="font-mono font-semibold text-slate-900">{{ $company->subdomain }}</span>
        </p>

        <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="mt-4 space-y-4">
            @csrf
            @method('DELETE')

            <div>
                <x-input-label for="confirm" :value="__('Confirm subdomain')" />
                <x-text-input id="confirm" name="confirm" class="mt-1 block w-full max-w-md" :value="old('confirm')" placeholder="{{ $company->subdomain }}" required />
                <x-input-error :messages="$errors->get('confirm')" class="mt-2" />
            </div>

            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                <input type="hidden" name="send_email" value="0" />
                <input type="checkbox" name="send_email" value="1" class="rounded border-slate-300 text-[var(--flow-primary)] focus:ring-[var(--flow-primary)]" @checked(old('send_email', true)) />
                <span>{{ __('Send email notification to company') }}</span>
            </label>

            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700"
                onclick="return confirm(@js(__('Permanently delete this company, all its users, projects and data? This cannot be undone.')))"
            >
                <x-cf-icon name="fa-trash-can" size="sm" class="" />
                <span>{{ __('Remove company') }}</span>
            </button>
        </form>
    </div>

    <div class="grid gap-6 mt-6 lg:grid-cols-2">
        <div class="flow-panel p-6 space-y-3 text-sm">
            <h3 class="font-semibold text-slate-900 dark:text-white">{{ __('Company details') }}</h3>
            <dl class="grid gap-2">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Subdomain') }}</dt><dd class="font-mono">{{ $company->subdomain }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Language') }}</dt><dd class="font-mono">{{ strtoupper($company->default_locale ?? '—') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Default currency') }}</dt><dd>{{ $company->default_currency }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Country') }}</dt><dd>{{ $company->country ?? '—' }}</dd></div>
                @if ($company->contact_email)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Company email') }}</dt><dd>{{ $company->contact_email }}</dd></div>
                @endif
                @if ($company->phone)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Phone') }}</dt><dd>{{ $company->phone }}</dd></div>
                @endif
                @if ($company->website)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Website') }}</dt><dd>{{ $company->website }}</dd></div>
                @endif
                @if ($company->tax_id)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('VAT / TVA') }}</dt><dd>{{ $company->tax_id }}</dd></div>
                @endif
                @if ($company->address_line1 || $company->city)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Address') }}</dt><dd class="text-end">{{ collect([$company->address_line1, $company->city, $company->postal_code])->filter()->join(', ') ?: '—' }}</dd></div>
                @endif
                @if ($company->industry)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Industry') }}</dt><dd>{{ $company->industry }}</dd></div>
                @endif
            </dl>
            @if (config('flowdesk.tenant_base_domain'))
                <p class="pt-4 text-xs text-slate-500">
                    {{ __('Tenant URL') }}:
                    <span class="font-mono">{{ flowdesk_tenant_url($company, '/dashboard') }}</span>
                </p>
            @endif
        </div>
        <div class="flow-panel p-6 space-y-3 text-sm">
            <h3 class="font-semibold text-slate-900 dark:text-white">{{ __('Usage counts') }}</h3>
            <dl class="grid gap-2">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Users') }}</dt><dd>{{ number_format($company->users_count) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Projects') }}</dt><dd>{{ number_format($company->projects_count) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Clients') }}</dt><dd>{{ number_format($company->clients_count) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Proposals (quotes)') }}</dt><dd>{{ number_format($company->proposals_count) }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">{{ __('Invoices') }}</dt><dd>{{ number_format($company->invoices_count) }}</dd></div>
            </dl>
        </div>
    </div>

    <div class="mt-8 flow-panel p-6">
        <h3 class="font-semibold text-slate-900 dark:text-white">{{ __('Subscriptions') }}</h3>
        <p class="mt-1 text-sm text-slate-600">{{ __('Includes active, trial, and ended periods.') }}</p>
        @if ($subscriptions->isEmpty())
            <p class="mt-2 text-sm text-slate-500">{{ __('No subscription rows.') }}</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-[#FFF7ED] text-start text-xs font-semibold uppercase tracking-wide text-orange-900/70">
                        <tr>
                            <th class="px-3 py-2 text-start">{{ __('Plan') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('Status') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('Trial ends') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('Period end') }}</th>
                            <th class="px-3 py-2 text-start">{{ __('Updated') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80">
                        @foreach ($subscriptions as $sub)
                            @php $ended = $sub->isEnded(); @endphp
                            <tr @class(['bg-rose-50/40' => $ended])>
                                <td class="px-3 py-3 font-medium">{{ $sub->plan?->name ?? '—' }}</td>
                                <td class="px-3 py-3">
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                        'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-200' => $ended,
                                        'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200' => ! $ended,
                                    ])>
                                        {{ $sub->lifecycleLabel() }}
                                    </span>
                                    <span class="ms-2 font-mono text-xs text-slate-400">{{ $sub->status }}</span>
                                </td>
                                <td class="px-3 py-3 tabular-nums text-slate-600">{{ $sub->trial_ends_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="px-3 py-3 tabular-nums">
                                    <span @class(['font-semibold text-rose-700' => $ended && $sub->current_period_end?->isPast()])>
                                        {{ $sub->current_period_end?->format('Y-m-d H:i') ?? '—' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 tabular-nums text-slate-500">{{ $sub->updated_at?->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-admin-layout>
