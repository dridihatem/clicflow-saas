@php
    $contactEmail = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
@endphp

<footer class="mkt-footer relative z-10 border-t border-[rgba(124,92,252,0.22)] bg-[rgba(7,11,26,0.92)] py-14 backdrop-blur-md">
    <div class="mx-auto max-w-7xl px-6 sm:px-8">
        <div class="flex flex-col items-start justify-between gap-8 sm:flex-row sm:items-center">
            <div>
                <a href="{{ route('home') }}" class="inline-flex rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-[#ea580c]">
                    <x-application-logo :tagline="true" :themed="true" />
                </a>
                <p class="mt-4 max-w-sm text-sm leading-relaxed text-slate-400">{{ __('Welcome footer tagline') }}</p>
            </div>

            @if (is_string($contactEmail) && $contactEmail !== '' && filter_var($contactEmail, FILTER_VALIDATE_EMAIL))
                <a href="mailto:{{ $contactEmail }}" class="inline-flex items-center gap-2 text-sm font-medium text-slate-300 transition hover:text-[#fb923c]">
                    <i class="fa-regular fa-envelope text-xs" aria-hidden="true"></i>
                    {{ $contactEmail }}
                </a>
            @endif
        </div>

        <div class="mt-12 flex flex-col gap-2 border-t border-[rgba(124,92,252,0.18)] pt-8 text-center text-xs text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:text-start">
            <p>© {{ date('Y') }} {{ config('app.name') }} — {{ __('All rights reserved') }}</p>
            <p class="text-slate-400">
                {{ __('footer_developed_by') }}
                <span class="font-semibold text-slate-200">Hatem Dridi</span>
                <span class="text-slate-500"> · {{ __('footer_web_developer') }}</span>
            </p>
        </div>
    </div>
</footer>
