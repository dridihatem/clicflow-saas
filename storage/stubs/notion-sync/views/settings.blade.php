@php
    $settings = $module->settings ?? [];
    $token = (string) ($settings['notion_api_token'] ?? '');
    $databaseId = (string) ($settings['notion_database_id'] ?? '');
    $syncEnabled = (bool) ($settings['sync_enabled'] ?? false);
    $isAdmin = auth()->user()->hasRole('company_admin');
@endphp

<div class="space-y-8">
    @include('modules.partials.flash')

    <div class="flow-panel p-6 sm:p-8">
        <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Notion Sync settings') }}</h3>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('notion_sync_settings_intro') }}</p>

        @if (! $isAdmin)
            <p class="mt-4 text-sm text-amber-800 dark:text-amber-200">{{ __('module_settings_admin_only') }}</p>
        @endif

        <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="module_action" value="save_notion_settings">
            <input type="hidden" name="return_page" value="settings">

            <div>
                <x-input-label for="notion_api_token" :value="__('Notion API token')" />
                <x-text-input id="notion_api_token" name="notion_api_token" type="password" class="mt-1 w-full font-mono text-sm" :value="$token" autocomplete="off" @disabled(! $isAdmin) />
            </div>
            <div>
                <x-input-label for="notion_database_id" :value="__('Notion database ID')" />
                <x-text-input id="notion_database_id" name="notion_database_id" class="mt-1 w-full font-mono text-sm" :value="$databaseId" @disabled(! $isAdmin) />
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                <input type="checkbox" name="sync_enabled" value="1" @checked($syncEnabled) @disabled(! $isAdmin) class="rounded border-slate-300 text-indigo-600 dark:border-slate-600" />
                {{ __('Enable project sync') }}
            </label>

            @if ($isAdmin)
                <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
            @endif
        </form>

        @if ($isAdmin)
            <form method="post" action="{{ route('modules.actions', $module->slug) }}" class="mt-6">
                @csrf
                <input type="hidden" name="module_action" value="test_notion_connection">
                <input type="hidden" name="return_page" value="settings">
                <x-secondary-button type="submit">{{ __('Test Notion connection') }}</x-secondary-button>
            </form>
        @endif
    </div>
</div>
