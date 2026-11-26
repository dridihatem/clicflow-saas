<x-admin-layout :title="__('Create company')">
    @php
        $baseDomain = ltrim((string) config('flowdesk.tenant_base_domain', 'clicflow.fr'), '.');
    @endphp

    <div class="mb-6">
        <a
            href="{{ route('admin.companies.index') }}"
            class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-orange-200 hover:text-[#C2410C]"
        >
            <x-cf-icon name="arrow-left" size="sm" />
            <span>{{ __('Back to companies') }}</span>
        </a>
    </div>

    <div class="mb-8">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#EA580C]">{{ __('Platform administration') }}</p>
        <h1 class="mt-1 font-[Space_Grotesk,ui-sans-serif,system-ui] text-3xl font-bold tracking-tight text-slate-900">{{ __('Create company') }}</h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">{{ __('admin_company_create_intro') }}</p>
    </div>

    <form
        method="POST"
        action="{{ route('admin.companies.store') }}"
        class="space-y-6"
        x-data="{
            generate: {{ old('generate_password', '1') === '1' || old('generate_password', '1') === 'on' ? 'true' : 'false' }},
            includePassword: {{ old('include_password') ? 'true' : 'false' }},
            subdomainTouched: {{ old('subdomain') ? 'true' : 'false' }},
            subdomain: @js(old('subdomain', $suggestedSubdomain ?? '')),
            generated: @js($generatedPassword ?? ''),
            slugify(name) {
                return String(name || '')
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 48);
            },
            onName(e) {
                if (this.subdomainTouched) return;
                const s = this.slugify(e.target.value);
                this.subdomain = s || @js($suggestedSubdomain ?? 'nouvelle-societe');
            }
        }"
    >
        @csrf

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Company --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
                <div class="flex items-center gap-3">
                    <x-cf-icon name="building" variant="tile" tone="orange" size="md" />
                    <div>
                        <h3 class="font-[Space_Grotesk,ui-sans-serif,system-ui] text-lg font-semibold text-slate-900">{{ __('Company') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('admin_company_create_company_help') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <x-input-label for="company_name" :value="__('Company name')" />
                        <x-text-input id="company_name" name="company_name" class="mt-1 block w-full" :value="old('company_name')" required @input="onName($event)" />
                        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="subdomain" :value="__('Subdomain')" />
                        <x-text-input
                            id="subdomain"
                            name="subdomain"
                            class="mt-1 block w-full font-mono text-sm"
                            x-model="subdomain"
                            @input="subdomainTouched = true"
                            required
                            maxlength="48"
                            pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                        />
                        <p class="mt-1.5 text-xs text-slate-500">
                            {{ __('admin_company_subdomain_help') }}
                            <span class="font-mono font-semibold text-slate-800" x-text="(subdomain || '…') + '.{{ $baseDomain }}'"></span>
                        </p>
                        <x-input-error :messages="$errors->get('subdomain')" class="mt-2" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="contact_email" :value="__('Company contact email (optional)')" />
                            <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email')" />
                            <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="phone" :value="__('Phone')" />
                            <x-text-input id="phone" name="phone" class="mt-1 block w-full" :value="old('phone')" />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="country" :value="__('Country')" />
                            <select id="country" name="country" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-[#EA580C] focus:ring-[#EA580C]">
                                @foreach (($countries ?? []) as $code => $label)
                                    <option value="{{ $code }}" @selected(old('country', 'FR') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('country')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="tax_id" :value="__('VAT / TVA number (optional)')" />
                            <x-text-input id="tax_id" name="tax_id" class="mt-1 block w-full" :value="old('tax_id')" />
                            <x-input-error :messages="$errors->get('tax_id')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="default_locale" :value="__('Default language')" />
                            <select id="default_locale" name="default_locale" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-[#EA580C] focus:ring-[#EA580C]" required>
                                @foreach (($locales ?? ['fr', 'en', 'es', 'ar']) as $loc)
                                    <option value="{{ $loc }}" @selected(old('default_locale', flowdesk_default_locale()) === $loc)>{{ flowdesk_locale_name($loc) }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('default_locale')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="default_currency" :value="__('Default currency')" />
                            <select id="default_currency" name="default_currency" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-[#EA580C] focus:ring-[#EA580C]" required>
                                @foreach (($currencies ?? ['EUR']) as $code)
                                    @php($label = ($currencyLabels[$code] ?? $code))
                                    <option value="{{ $code }}" @selected(old('default_currency', flowdesk_default_currency()) === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('default_currency')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="plan_id" :value="__('Initial plan (optional)')" />
                        <select id="plan_id" name="plan_id" class="mt-1 block w-full rounded-lg border-slate-300 shadow-sm focus:border-[#EA580C] focus:ring-[#EA580C]">
                            <option value="">{{ __('Use default') }}</option>
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected((string) old('plan_id') === (string) $plan->id)>
                                    {{ $plan->name }} ({{ $plan->slug }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('plan_id')" class="mt-2" />
                    </div>

                    <div>
                        <p class="text-sm font-medium text-slate-700">{{ __('admin_company_subscription_mode') }}</p>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 has-[:checked]:border-[#EA580C] has-[:checked]:bg-[#FFF7ED]">
                                <input type="radio" name="subscription_mode" value="trial" class="mt-0.5 border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" @checked(old('subscription_mode', 'trial') === 'trial') />
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">{{ __('admin_company_mode_trial') }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin_company_mode_trial_help') }}</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3 has-[:checked]:border-[#EA580C] has-[:checked]:bg-[#FFF7ED]">
                                <input type="radio" name="subscription_mode" value="active" class="mt-0.5 border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" @checked(old('subscription_mode') === 'active') />
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">{{ __('admin_company_mode_active') }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin_company_mode_active_help') }}</span>
                                </span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('subscription_mode')" class="mt-2" />
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="hidden" name="plan_locked" value="0" />
                        <input type="checkbox" name="plan_locked" value="1" class="rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" @checked(old('plan_locked')) />
                        <span>{{ __('Lock plan') }}</span>
                    </label>
                </div>
            </div>

            {{-- Owner --}}
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-sm shadow-slate-900/5">
                <div class="flex items-center gap-3">
                    <x-cf-icon name="user" variant="tile" tone="sky" size="md" />
                    <div>
                        <h3 class="font-[Space_Grotesk,ui-sans-serif,system-ui] text-lg font-semibold text-slate-900">{{ __('admin_company_owner_heading') }}</h3>
                        <p class="text-xs text-slate-500">{{ __('admin_company_owner_help') }}</p>
                    </div>
                </div>

                <div class="mt-5 space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label for="owner_first_name" :value="__('admin_owner_first_name')" />
                            <x-text-input id="owner_first_name" name="owner_first_name" class="mt-1 block w-full" :value="old('owner_first_name')" required />
                            <x-input-error :messages="$errors->get('owner_first_name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="owner_last_name" :value="__('admin_owner_last_name')" />
                            <x-text-input id="owner_last_name" name="owner_last_name" class="mt-1 block w-full" :value="old('owner_last_name')" required />
                            <x-input-error :messages="$errors->get('owner_last_name')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="admin_email" :value="__('admin_owner_email')" />
                        <x-text-input id="admin_email" name="admin_email" type="email" class="mt-1 block w-full" :value="old('admin_email')" required />
                        <x-input-error :messages="$errors->get('admin_email')" class="mt-2" />
                    </div>

                    <input type="hidden" name="generate_password" value="0" />
                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                        <input type="checkbox" name="generate_password" value="1" class="mt-0.5 rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" x-model="generate" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ __('admin_generate_password') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin_generate_password_help') }}</span>
                        </span>
                    </label>

                    <div x-show="!generate" x-cloak>
                        <x-input-label for="admin_password" :value="__('Temporary password')" />
                        <x-text-input id="admin_password" name="admin_password" type="text" class="mt-1 block w-full" :value="old('admin_password')" x-bind:required="!generate" autocomplete="new-password" />
                        <x-input-error :messages="$errors->get('admin_password')" class="mt-2" />
                    </div>

                    <div x-show="generate" x-cloak class="rounded-xl border border-orange-200 bg-[#FFF7ED] px-3 py-2 text-xs text-orange-900">
                        {{ __('admin_generated_password_note') }}
                    </div>

                    <input type="hidden" name="include_password" value="0" />
                    <label class="inline-flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="include_password" value="1" class="mt-0.5 rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" x-model="includePassword" />
                        <span>
                            <span class="font-semibold">{{ __('admin_include_password') }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ __('admin_include_password_help') }}</span>
                        </span>
                    </label>

                    <p class="text-xs leading-relaxed text-slate-500">{{ __('admin_welcome_mail_will_be_sent') }}</p>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.companies.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                <x-cf-icon name="xmark" size="sm" />
                <span>{{ __('Cancel') }}</span>
            </a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#EA580C] px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-orange-600/25 transition hover:bg-[#C2410C]">
                <x-cf-icon name="plus" size="sm" />
                <span>{{ __('Create company') }}</span>
            </button>
        </div>
    </form>
</x-admin-layout>
