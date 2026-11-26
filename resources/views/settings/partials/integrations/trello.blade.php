@php
    $integration = $integration ?? null;
    $logs = $logs ?? collect();
@endphp

<div class="space-y-5">
    @if ($errors->has('trello'))
        <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ $errors->first('trello') }}</div>
    @endif

    <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('trello_settings_intro') }}</p>

    <form method="POST" action="{{ route('settings.trello.update') }}" class="space-y-4">
        @csrf
        @method('PUT')
        <div>
            <x-input-label for="trello_api_key" :value="__('trello_api_key')" />
            <x-text-input id="trello_api_key" name="api_key" type="password" class="mt-1 w-full font-mono text-sm" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['api_key'] ?? null) ? '********' : '' }}" />
            <p class="mt-1 text-xs text-slate-500">{{ __('trello_api_key_help') }}</p>
            <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="trello_api_token" :value="__('trello_api_token')" />
            <x-text-input id="trello_api_token" name="api_token" type="password" class="mt-1 w-full font-mono text-sm" value="" autocomplete="new-password" placeholder="{{ filled($integration?->credentials['api_token'] ?? null) ? '********' : '' }}" />
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

    <div>
        <h2 class="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Recent sync logs') }}</h2>
        <ul class="space-y-2 text-sm">
            @forelse ($logs as $log)
                <li>{{ $log->created_at?->format('Y-m-d H:i') }} · {{ $log->direction }} · {{ $log->status }} — {{ $log->summary }}</li>
            @empty
                <li class="text-slate-500">{{ __('No logs yet.') }}</li>
            @endforelse
        </ul>
    </div>
</div>
