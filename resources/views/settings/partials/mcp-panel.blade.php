@php
    $colors = flowdesk_brand_colors();
    $toolsAudience = $toolsAudience ?? ($audience ?? 'company');
    $companyTools = [
        ['name' => 'list_clients', 'icon' => 'clients', 'tone' => 'sky', 'label' => __('mcp_tool_list_clients')],
        ['name' => 'search_clients', 'icon' => 'search', 'tone' => 'sky', 'label' => __('mcp_tool_search_clients')],
        ['name' => 'create_client', 'icon' => 'circle-plus', 'tone' => 'sky', 'label' => __('mcp_tool_create_client')],
        ['name' => 'list_prospects', 'icon' => 'prospects', 'tone' => 'amber', 'label' => __('mcp_tool_list_prospects')],
        ['name' => 'search_prospects', 'icon' => 'magnifying-glass', 'tone' => 'amber', 'label' => __('mcp_tool_search_prospects')],
        ['name' => 'create_prospect', 'icon' => 'user-tag', 'tone' => 'amber', 'label' => __('mcp_tool_create_prospect')],
        ['name' => 'list_projects', 'icon' => 'projects', 'tone' => 'violet', 'label' => __('mcp_tool_list_projects')],
        ['name' => 'create_project', 'icon' => 'folder-open', 'tone' => 'violet', 'label' => __('mcp_tool_create_project')],
        ['name' => 'list_tasks', 'icon' => 'table-list', 'tone' => 'teal', 'label' => __('mcp_tool_list_tasks')],
        ['name' => 'create_task', 'icon' => 'circle-check', 'tone' => 'teal', 'label' => __('mcp_tool_create_task')],
        ['name' => 'update_task_status', 'icon' => 'rotate', 'tone' => 'teal', 'label' => __('mcp_tool_update_task_status')],
        ['name' => 'list_quotes', 'icon' => 'proposals', 'tone' => 'orange', 'label' => __('mcp_tool_list_quotes')],
        ['name' => 'create_quote', 'icon' => 'file-signature', 'tone' => 'orange', 'label' => __('mcp_tool_create_quote')],
        ['name' => 'list_invoices', 'icon' => 'invoices', 'tone' => 'rose', 'label' => __('mcp_tool_list_invoices')],
        ['name' => 'create_invoice', 'icon' => 'file-invoice-dollar', 'tone' => 'rose', 'label' => __('mcp_tool_create_invoice')],
        ['name' => 'list_tickets', 'icon' => 'ticket', 'tone' => 'slate', 'label' => __('mcp_tool_list_tickets')],
        ['name' => 'create_ticket', 'icon' => 'headset', 'tone' => 'slate', 'label' => __('mcp_tool_create_ticket')],
        ['name' => 'list_appointments', 'icon' => 'calendar-days', 'tone' => 'emerald', 'label' => __('mcp_tool_list_appointments')],
        ['name' => 'create_appointment', 'icon' => 'calendar-check', 'tone' => 'emerald', 'label' => __('mcp_tool_create_appointment')],
        ['name' => 'campaigns_stats', 'icon' => 'chart-bar', 'tone' => 'blue', 'label' => __('mcp_tool_campaigns_stats')],
    ];
    $adminTools = [
        ['name' => 'list_companies', 'icon' => 'building', 'tone' => 'orange', 'label' => __('mcp_tool_list_companies')],
        ['name' => 'list_users', 'icon' => 'users', 'tone' => 'sky', 'label' => __('mcp_tool_list_users')],
        ['name' => 'list_subscriptions', 'icon' => 'credit-card', 'tone' => 'violet', 'label' => __('mcp_tool_list_subscriptions')],
        ['name' => 'platform_stats', 'icon' => 'chart-pie', 'tone' => 'emerald', 'label' => __('mcp_tool_platform_stats')],
    ];
    $toolItems = $toolsAudience === 'admin' ? $adminTools : $companyTools;
    $claudeConfig = [
        'mcpServers' => [
            'clicflow' => [
                'url' => $endpoint,
                'headers' => [
                    'Authorization' => 'Bearer '.($revealedToken ?: 'VOTRE_CLE_MCP'),
                ],
            ],
        ],
    ];
    $cursorConfig = $claudeConfig;
    $claudeJson = json_encode($claudeConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $cursorJson = json_encode($cursorConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

@if (session('status'))
    <div class="mb-6 flex items-start gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">
        <x-cf-icon name="circle-check" size="md" tone="emerald" class="mt-0.5 shrink-0" />
        <span>{{ session('status') }}</span>
    </div>
@endif

@if ($revealedToken)
    <div class="mb-6 rounded-2xl border border-orange-200 bg-orange-50/80 p-5" x-data="{ copied: false }">
        <div class="flex items-start gap-3">
            <x-cf-icon name="key" variant="tile" tone="orange" size="lg" class="shrink-0" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-[#9a3412]">{{ __('mcp_token_created') }}</p>
                <p class="mt-1 text-xs text-[#9a3412]">{{ __('mcp_token_shown_once') }}</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <code class="block max-w-full truncate rounded-lg border border-orange-200 bg-white px-3 py-2 text-sm font-semibold text-slate-900" x-ref="tok">{{ $revealedToken }}</code>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-[#EA580C] px-3 py-2 text-xs font-semibold text-white"
                        @click="navigator.clipboard.writeText($refs.tok.textContent); copied = true; setTimeout(() => copied = false, 2000)"
                    >
                        <x-cf-icon name="file-lines" size="sm" class="text-white" />
                        <span x-show="!copied">{{ __('mcp_copy') }}</span>
                        <span x-show="copied" x-cloak>{{ __('mcp_copied') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="flow-panel overflow-hidden p-0">
    <div class="cf-brand-accent-bar" style="height:5px;background:{{ $colors['bar'] }};"></div>
    <div class="p-6 sm:p-8 space-y-10">
        {{-- Endpoint --}}
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <x-cf-icon name="link" variant="tile" tone="sky" size="md" />
                {{ __('mcp_endpoint_label') }}
            </h2>
            <code class="mt-3 block break-all rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm text-slate-800">{{ $endpoint }}</code>
        </div>

        @if ($toolsAudience !== 'admin')
            @include('settings.partials.mcp-guide', ['endpoint' => $endpoint, 'displayName' => $displayName ?? 'ClicFlow'])
        @endif

        {{-- Create token --}}
        <form method="POST" action="{{ $storeRoute }}" class="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
            @csrf
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <x-cf-icon name="key" variant="tile" tone="orange" size="md" />
                {{ __('mcp_create_token') }}
            </h2>
            <div>
                <x-input-label for="mcp_name" :value="__('mcp_token_name')" />
                <x-text-input id="mcp_name" name="name" class="mt-1 block w-full" :placeholder="__('mcp_token_name_placeholder')" required />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="flex flex-wrap gap-4 text-sm">
                <label class="inline-flex items-center gap-2">
                    <x-cf-icon name="eye" size="sm" tone="sky" class="text-sky-600" />
                    <input type="checkbox" name="scopes[]" value="read" checked class="rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" />
                    {{ __('mcp_scope_read') }}
                </label>
                <label class="inline-flex items-center gap-2">
                    <x-cf-icon name="pen-to-square" size="sm" tone="orange" class="text-orange-600" />
                    <input type="checkbox" name="scopes[]" value="write" checked class="rounded border-slate-300 text-[#EA580C] focus:ring-[#EA580C]" />
                    {{ __('mcp_scope_write') }}
                </label>
            </div>
            <x-input-error :messages="$errors->get('scopes')" class="mt-1" />
            <x-primary-button type="submit" class="inline-flex items-center gap-2">
                <x-cf-icon name="plus" size="sm" class="text-white" />
                {{ __('mcp_create_token') }}
            </x-primary-button>
        </form>

        {{-- Tools catalogue --}}
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <x-cf-icon name="plug-circle-bolt" variant="tile" tone="violet" size="md" />
                {{ __('mcp_tools_heading') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('mcp_tools_intro') }}</p>
            <ul class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ($toolItems as $tool)
                    <li class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5">
                        <x-cf-icon :name="$tool['icon']" variant="tile" :tone="$tool['tone']" size="md" class="shrink-0" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $tool['label'] }}</p>
                            <p class="truncate font-mono text-[11px] text-slate-500">{{ $tool['name'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- Config snippets --}}
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <x-cf-icon name="code" variant="tile" tone="slate" size="md" />
                {{ __('mcp_how_to') }}
            </h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('mcp_how_to_body') }}</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div>
                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <x-cf-icon name="ai" size="sm" tone="violet" />
                        {{ __('mcp_claude_config') }}
                    </p>
                    <pre class="mt-2 overflow-auto rounded-xl border border-slate-200 bg-slate-900 p-3 text-xs text-slate-100">{{ $claudeJson }}</pre>
                </div>
                <div>
                    <p class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500">
                        <x-cf-icon name="code" size="sm" tone="sky" />
                        {{ __('mcp_cursor_config') }}
                    </p>
                    <pre class="mt-2 overflow-auto rounded-xl border border-slate-200 bg-slate-900 p-3 text-xs text-slate-100">{{ $cursorJson }}</pre>
                </div>
            </div>
        </div>

        {{-- Keys list --}}
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <x-cf-icon name="shield-halved" variant="tile" tone="emerald" size="md" />
                {{ __('mcp_keys_heading') }}
            </h2>
            @if ($tokens->isEmpty())
                <p class="mt-3 flex items-center gap-2 text-sm text-slate-500">
                    <x-cf-icon name="info" size="sm" class="text-slate-400" />
                    {{ __('mcp_no_tokens') }}
                </p>
            @else
                <ul class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
                    @foreach ($tokens as $tok)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <x-cf-icon name="key" variant="tile" tone="orange" size="md" class="shrink-0" />
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $tok->name }}@if ($tok->oauth_client_id ?? null)<span class="ml-2 rounded-full bg-sky-50 px-2 py-0.5 align-middle text-[10px] font-bold uppercase tracking-wide text-sky-700" data-mcp-oauth-badge>{{ __('mcp_oauth_badge') }}</span>@endif</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $tok->token_prefix }}… ·
                                        {{ implode(', ', $tok->scopes ?? []) }} ·
                                        {{ $tok->last_used_at ? __('mcp_last_used').' '.$tok->last_used_at->diffForHumans() : __('mcp_never_used') }}
                                    </p>
                                </div>
                            </div>
                            <form method="POST" action="{{ $revokeRoute($tok) }}" onsubmit="return confirm(@json(__('mcp_revoke_confirm')))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                    <x-cf-icon name="trash" size="sm" class="text-rose-600" />
                                    {{ __('mcp_revoke') }}
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
