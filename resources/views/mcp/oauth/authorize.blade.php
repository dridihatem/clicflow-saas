<x-guest-layout hero-variant="default">
    <div class="mx-auto w-full max-w-md" data-mcp-oauth-consent>
        <div class="mb-6 flex items-center gap-3">
            <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-[#EA580C] to-[#E4A824] text-white shadow-lg shadow-orange-500/25">
                <x-cf-icon name="wand-magic-sparkles" size="md" class="text-white" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#EA580C]">{{ __('mcp_oauth_kicker') }}</p>
                <h1 class="font-[Space_Grotesk,ui-sans-serif,system-ui] text-2xl font-bold leading-tight text-slate-900">
                    {{ __('mcp_oauth_title', ['app' => $client->name]) }}
                </h1>
            </div>
        </div>

        <p class="text-sm leading-relaxed text-slate-600">
            {!! __('mcp_oauth_intro', ['app' => '<strong class="text-slate-900">'.e($client->name).'</strong>', 'company' => '<strong class="text-slate-900">'.e($company->name).'</strong>']) !!}
        </p>

        <dl class="mt-5 divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-slate-50/70 text-sm">
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-slate-500">{{ __('mcp_oauth_workspace') }}</dt>
                <dd class="truncate font-semibold text-slate-900" data-oauth-company>{{ $company->name }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-slate-500">{{ __('mcp_oauth_account') }}</dt>
                <dd class="truncate font-medium text-slate-800">{{ $user->email }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-slate-500">{{ __('mcp_oauth_redirect') }}</dt>
                <dd class="truncate font-mono text-xs text-slate-700">{{ $redirectHost }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <dt class="text-slate-500">{{ __('mcp_oauth_server') }}</dt>
                <dd class="min-w-0 text-right">
                    <span class="block truncate font-semibold text-slate-900" data-oauth-server-name>{{ $serverName ?? 'ClicFlow' }}</span>
                    <span class="block truncate font-mono text-[11px] text-slate-500">{{ $endpoint }}</span>
                </dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('mcp.oauth.approve') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="rid" value="{{ $rid }}" />

            <fieldset class="space-y-3">
                <legend class="mb-2 text-sm font-semibold text-slate-900">{{ __('mcp_oauth_permissions') }}</legend>
                @if (in_array('read', $requested, true))
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-[#30C0F0]">
                        <input type="checkbox" name="scopes[]" value="read" checked class="mt-0.5 rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ __('mcp_oauth_scope_read') }} <span class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 font-mono text-[10px] text-sky-700">mcp:read</span></span>
                            <span class="block text-xs text-slate-500">{{ __('mcp_oauth_scope_read_help') }}</span>
                        </span>
                    </label>
                @endif
                @if (in_array('write', $requested, true))
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 hover:border-[#24B478]">
                        <input type="checkbox" name="scopes[]" value="write" checked class="mt-0.5 rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" />
                        <span>
                            <span class="block text-sm font-semibold text-slate-900">{{ __('mcp_oauth_scope_write') }} <span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 font-mono text-[10px] text-emerald-700">mcp:write</span></span>
                            <span class="block text-xs text-slate-500">{{ __('mcp_oauth_scope_write_help') }}</span>
                        </span>
                    </label>
                @endif
            </fieldset>

            <p class="flex items-start gap-2 rounded-xl border border-amber-200/80 bg-amber-50/80 px-3 py-2.5 text-xs leading-relaxed text-amber-900">
                <x-cf-icon name="info" size="sm" tone="amber" class="mt-0.5 shrink-0" />
                <span>{{ __('mcp_oauth_revoke_hint') }}</span>
            </p>

            <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                <button type="submit" name="decision" value="deny" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    {{ __('mcp_oauth_deny') }}
                </button>
                <button type="submit" name="decision" value="approve" data-oauth-approve class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#EA580C] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-orange-500/25 hover:bg-[#c2410c]">
                    <x-cf-icon name="circle-check" size="sm" class="text-white" />
                    {{ __('mcp_oauth_approve') }}
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center text-xs text-slate-500">
            @csrf
            {{ __('mcp_oauth_not_you') }}
            <button type="submit" class="font-semibold text-[#EA580C] hover:underline">{{ __('mcp_oauth_switch') }}</button>
        </form>
    </div>
</x-guest-layout>
