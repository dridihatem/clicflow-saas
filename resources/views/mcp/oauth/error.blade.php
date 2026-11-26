<x-guest-layout hero-variant="default">
    <div class="mx-auto w-full max-w-md text-center" data-mcp-oauth-error>
        <span class="mx-auto inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-[#D81854]">
            <x-cf-icon name="warning" size="md" tone="rose" />
        </span>
        <h1 class="mt-4 font-[Space_Grotesk,ui-sans-serif,system-ui] text-xl font-bold text-slate-900">{{ __('mcp_oauth_error_title') }}</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $message }}</p>
        @if ($offerSwitch)
            <form method="POST" action="{{ route('logout') }}" class="mt-6">
                @csrf
                <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-[#EA580C] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#c2410c]">{{ __('mcp_oauth_switch') }}</button>
            </form>
        @endif
    </div>
</x-guest-layout>
