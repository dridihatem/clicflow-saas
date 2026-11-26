@php
    $brand = (string) config('flowdesk.brand_name', config('app.name'));
    $supportEmail = config('flowdesk.contact_inbox_email') ?: 'contact@clicflow.fr';
@endphp

<footer class="flow-app-footer relative z-10 mt-auto border-t border-slate-200/80 bg-white/90 px-4 py-4 backdrop-blur-sm dark:border-slate-700/80 dark:bg-slate-950/80 sm:px-6 lg:px-8">
    <div class="mx-auto flex max-w-12xl flex-col items-center justify-between gap-2 text-center text-xs text-slate-500 dark:text-slate-400 sm:flex-row sm:text-start">
        <p>
            {{ __('footer_developed_by') }}
            <span class="font-medium text-slate-700 dark:text-slate-200">Hatem Dridi</span>
            <span class="text-slate-400 dark:text-slate-600" aria-hidden="true">·</span>
            {{ __('footer_web_developer') }}
        </p>
        <p class="flex flex-wrap items-center justify-center gap-x-1.5 gap-y-1 sm:justify-end">
            <a
                href="mailto:{{ $supportEmail }}"
                class="font-medium text-teal-700 transition hover:text-teal-600 dark:text-teal-300 dark:hover:text-teal-200"
            >
                {{ __('app_footer_support_link', ['brand' => $brand]) }}
            </a>
            <span class="text-slate-400 dark:text-slate-600" aria-hidden="true">·</span>
            <span>{{ __('app_footer_support_hint') }}</span>
        </p>
    </div>
</footer>
