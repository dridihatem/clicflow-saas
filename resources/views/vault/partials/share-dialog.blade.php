<div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-10 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="vault-share-title">
    <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
            <div class="flex min-w-0 items-center gap-3">
                <x-cf-icon name="user-group" variant="tile" tone="blue" size="sm" />
                <div class="min-w-0">
                    <h3 id="vault-share-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ __('vault_share_title') }}</h3>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $file->original_name }} · {{ $file->humanSize() }}</p>
                </div>
            </div>
            <a href="{{ route($rp.'index', request()->except('edit')) }}" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" aria-label="{{ __('Close') }}"><x-cf-icon name="xmark" size="xs" /></a>
        </div>
        <form method="POST" action="{{ route($rp.'update', $file->id) }}" class="space-y-4 px-6 py-5">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label for="edit_folder" :value="__('vault_field_folder')" class="!text-xs" />
                    <x-text-input id="edit_folder" name="folder" type="text" list="vault-folders-edit" :value="old('folder', $file->folder)" class="mt-1 block w-full" />
                    <datalist id="vault-folders-edit">@foreach ($folders as $fo)<option value="{{ $fo }}">@endforeach</datalist>
                </div>
                <div>
                    <x-input-label for="edit_description" :value="__('vault_field_description')" class="!text-xs" />
                    <x-text-input id="edit_description" name="description" type="text" :value="old('description', $file->description)" maxlength="1000" class="mt-1 block w-full" />
                </div>
            </div>
            @include('vault.partials.share-fields', ['file' => $file])
            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <a href="{{ route($rp.'index', request()->except('edit')) }}" class="text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400">{{ __('Cancel') }}</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-500">{{ __('vault_share_save') }}</button>
            </div>
        </form>
    </div>
</div>
