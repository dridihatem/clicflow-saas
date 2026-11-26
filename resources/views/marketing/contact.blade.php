@extends('layouts.marketing')

@section('title', __('Contact').' — '.config('app.name'))
@section('meta_description', __('Marketing contact meta'))

@section('content')
    @include('marketing.partials.hero', [
        'eyebrow' => __('Contact'),
        'title' => __('Get in'),
        'titleAccent' => __('touch'),
        'lead' => __('Marketing contact lead'),
        'maxWidth' => 'max-w-7xl',
        'centered' => true,
    ])

    <section class="mkt-band border-b border-[rgba(15,23,42,0.06)]">
        <div class="mx-auto max-w-7xl px-6 pb-20 pt-10 sm:px-8">
            @if (session('status'))
                <div class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" data-mkt-reveal>
                    {{ session('status') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2 lg:items-start">
                <div class="space-y-5">
                    <div class="mkt-feature-card" data-mkt-reveal data-mkt-hover>
                        <h2 class="mkt-display text-lg font-semibold text-slate-900">{{ __('Marketing contact info title') }}</h2>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing contact info body') }}</p>

                        <dl class="mt-8 space-y-5">
                            @if (is_string($contactEmail) && $contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
                                <div>
                                    <dt class="mkt-trust-label">{{ __('Email') }}</dt>
                                    <dd class="mt-1">
                                        <a href="mailto:{{ $contactEmail }}" class="text-sm font-medium text-slate-900 transition hover:text-[#5b45d6]">{{ $contactEmail }}</a>
                                    </dd>
                                </div>
                            @endif
                            <div>
                                <dt class="mkt-trust-label">{{ __('Marketing contact response label') }}</dt>
                                <dd class="mt-1 text-sm text-slate-700">{{ __('Marketing contact response time') }}</dd>
                            </div>
                            <div>
                                <dt class="mkt-trust-label">{{ __('Sales') }}</dt>
                                <dd class="mt-1 text-sm text-slate-700">{{ __('Marketing contact sales note') }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div class="mkt-cta-box" data-mkt-reveal data-mkt-delay="0.08">
                        <h3 class="mkt-display text-base font-semibold text-slate-900">{{ __('Marketing contact message title') }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-slate-600">{{ __('Marketing contact message body') }}</p>
                        <ul class="mt-6 space-y-3 text-sm text-slate-600">
                            <li class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#EA580C]" aria-hidden="true"></span>
                                {{ __('Marketing contact message point1') }}
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#1FB8A8]" aria-hidden="true"></span>
                                {{ __('Marketing contact message point2') }}
                            </li>
                            <li class="flex items-start gap-3">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#EA580C]" aria-hidden="true"></span>
                                {{ __('Marketing contact message point3') }}
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="mkt-glass rounded-[20px] p-6 sm:p-8" data-mkt-reveal data-mkt-delay="0.05">
                    <span class="mkt-eyebrow">{{ __('Message') }}</span>
                    <h2 class="mkt-display mt-3 text-lg font-semibold text-slate-900">{{ __('Marketing contact form title') }}</h2>
                    <p class="mt-2 text-sm text-slate-600">{{ __('Marketing contact form lead') }}</p>

                    <form method="POST" action="{{ route('marketing.contact.store') }}" class="mt-8 space-y-5">
                        @csrf
                        <div>
                            <x-input-label for="contact_name" :value="__('Name')" />
                            <x-text-input id="contact_name" name="name" type="text" class="mt-2 block w-full" :value="old('name')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>
                        <div>
                            <x-input-label for="contact_email" :value="__('Email')" />
                            <x-text-input id="contact_email" name="email" type="email" class="mt-2 block w-full" :value="old('email')" required />
                            <x-input-error class="mt-2" :messages="$errors->get('email')" />
                        </div>
                        <div>
                            <x-input-label for="contact_company" :value="__('Company (optional)')" />
                            <x-text-input id="contact_company" name="company" type="text" class="mt-2 block w-full" :value="old('company')" />
                            <x-input-error class="mt-2" :messages="$errors->get('company')" />
                        </div>
                        <div>
                            <x-input-label for="contact_message" :value="__('Message')" />
                            <textarea
                                id="contact_message"
                                name="message"
                                rows="5"
                                required
                                class="flow-input mt-2 block w-full"
                            >{{ old('message') }}</textarea>
                            <x-input-error class="mt-2" :messages="$errors->get('message')" />
                        </div>

                        <div class="rounded-xl border border-[rgba(120,80,50,0.14)] bg-[rgb(11,16,32)] p-4">
                            <x-input-label for="contact_captcha" :value="__('Math CAPTCHA')" />
                            <p class="mt-1 text-sm font-medium text-slate-800">{{ $captcha['question'] }}</p>
                            <input type="hidden" name="_captcha_token" value="{{ $captcha['token'] }}" />
                            <x-text-input
                                id="contact_captcha"
                                name="_captcha_answer"
                                type="number"
                                inputmode="numeric"
                                class="mt-3 block w-full max-w-[10rem]"
                                :value="old('_captcha_answer')"
                                required
                                autocomplete="off"
                            />
                            <p class="mt-2 text-xs text-slate-500">{{ __('Marketing contact captcha hint') }}</p>
                            <x-input-error class="mt-2" :messages="$errors->get('_captcha_answer')" />
                        </div>

                        <button type="submit" class="mkt-btn mkt-btn-primary mkt-btn-lg w-full justify-center sm:w-auto">{{ __('Send message') }}</button>
                    </form>

                    <p class="mt-6 text-xs text-slate-500">{{ __('Marketing contact privacy note') }}</p>
                </div>
            </div>
        </div>
    </section>
@endsection
