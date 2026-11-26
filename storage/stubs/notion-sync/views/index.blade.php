<div class="flow-panel p-6 sm:p-8">
    <h3 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('Notion Sync') }}</h3>
    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ __('notion_sync_overview') }}</p>
    <a href="{{ route('modules.show', ['slug' => $module->slug, 'page' => 'settings']) }}" class="mt-4 inline-flex text-sm font-medium text-indigo-600 hover:underline dark:text-indigo-400">
        {{ __('Open settings') }}
    </a>
</div>
