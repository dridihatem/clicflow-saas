@php
    $pvService = app(\App\Services\PortalVisibilityService::class);
    $pvSections = $pvService->sections();
    $pvCompanyHidden = $pvService->companyHidden(auth()->user()?->company);
    $pvOverrides = $pvService->clientOverrides($client);
    $pvEffective = $pvService->mapForClient($client);
    $pvHiddenCount = count(array_filter($pvEffective, fn ($v) => ! $v));
@endphp
@if (auth()->user()?->hasAnyRole(['company_admin', 'team_member']))
<details class="flow-panel group overflow-hidden p-0" id="portail-visibilite" @if (count($pvOverrides) || request()->is('*portal/visibility*')) open @endif>
    <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-3 px-6 py-4">
        <span class="flex items-center gap-3">
            <x-cf-icon name="eye" variant="tile" tone="orange" size="md" />
            <span>
                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ __('portal_vis_client_heading') }}</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">{{ __('portal_vis_client_intro') }}</span>
            </span>
        </span>
        <span class="flex items-center gap-2 text-xs">
            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ __('portal_vis_client_summary', ['hidden' => $pvHiddenCount, 'overrides' => count($pvOverrides)]) }}</span>
            <x-cf-icon name="chevron-down" size="sm" class="transition group-open:rotate-180" />
        </span>
    </summary>
    <form method="POST" action="{{ route('clients.portal.visibility', $client) }}" class="border-t border-slate-100 px-6 py-5 dark:border-slate-800">
        @csrf
        @method('PUT')
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($pvSections as $key => $section)
                @php
                    $current = $pvOverrides[$key] ?? 'inherit';
                    $inheritLabel = in_array($key, $pvCompanyHidden, true) ? __('portal_vis_inherit_hidden') : __('portal_vis_inherit_visible');
                @endphp
                <div class="flex items-center gap-3 rounded-xl border border-slate-200/80 px-3 py-2 dark:border-slate-700/80">
                    <x-cf-icon :name="$section['icon']" :tone="$section['tone']" size="sm" />
                    <span class="min-w-0 flex-1 truncate text-sm text-slate-800 dark:text-slate-100">{{ $section['label'] }}</span>
                    <select name="override[{{ $key }}]" class="rounded-lg border-slate-200 py-1 ps-2 pe-7 text-xs dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
                        <option value="inherit" @selected($current === 'inherit')>{{ $inheritLabel }}</option>
                        <option value="show" @selected($current === 'show')>{{ __('portal_vis_force_show') }}</option>
                        <option value="hide" @selected($current === 'hide')>{{ __('portal_vis_force_hide') }}</option>
                    </select>
                </div>
            @endforeach
        </div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('settings.portal-visibility') }}" class="text-xs font-medium text-orange-700 hover:underline dark:text-orange-300">{{ __('portal_vis_company_link') }}</a>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-600">
                <x-cf-icon name="floppy-disk" size="sm" />
                {{ __('Save') }}
            </button>
        </div>
    </form>
</details>
@endif
