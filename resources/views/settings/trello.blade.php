<x-app-layout>
    <x-slot name="header">
        <h2 class="flex items-center gap-3 font-semibold text-xl leading-tight text-slate-800 dark:text-slate-100"><x-integration-logo name="trello" size="sm" /> {{ __('trello_settings_title') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto w-full max-w-3xl space-y-6 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if ($errors->has('trello'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first('trello') }}</div>
            @endif

            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('trello_settings_intro') }}</p>

            <form method="POST" action="{{ route('settings.trello.update') }}" class="flow-panel space-y-5 p-6">
                @csrf
                @method('PUT')
                <div>
                    <x-input-label for="api_key" :value="__('trello_api_key')" />
                    <x-text-input id="api_key" name="api_key" type="password" class="mt-1 w-full font-mono text-sm" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['api_key'] ?? null) ? '********' : '' }}" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('trello_api_key_help') }}</p>
                    <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="api_token" :value="__('trello_api_token')" />
                    <x-text-input id="api_token" name="api_token" type="password" class="mt-1 w-full font-mono text-sm" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['api_token'] ?? null) ? '********' : '' }}" />
                    <p class="mt-1 text-xs text-slate-500">{{ __('trello_api_token_help') }}</p>
                    <x-input-error :messages="$errors->get('api_token')" class="mt-2" />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="sync_enabled" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('sync_enabled', $integration?->settings['sync_enabled'] ?? true))>
                    {{ __('trello_activate_project_sync') }}
                </label>
                <x-primary-button>{{ __('Save') }}</x-primary-button>
            </form>

            @if ($integration)
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('settings.trello.test') }}">@csrf<x-secondary-button>{{ __('trello_test_connection') }}</x-secondary-button></form>
                    <form method="POST" action="{{ route('settings.trello.sync') }}">@csrf<x-secondary-button>{{ __('trello_sync_now') }}</x-secondary-button></form>
                    <form method="POST" action="{{ route('settings.trello.disconnect') }}" onsubmit="return confirm(@js(__('trello_disconnect_confirm')))">@csrf<x-secondary-button>{{ __('trello_disconnect') }}</x-secondary-button></form>
                </div>
            @endif

            <div class="flow-panel p-6">
                <h2 class="mb-3 font-semibold">{{ __('Recent sync logs') }}</h2>
                <ul class="space-y-2 text-sm">
                    @forelse ($logs as $log)
                        <li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>
                    @empty
                        <li class="text-slate-500">{{ __('No logs yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
