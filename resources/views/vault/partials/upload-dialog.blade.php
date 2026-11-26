<div x-data="{ open: @js((bool) $openUpload || ($errors->any() && ! $editFile && ! $linksFile)) }"
     @vault-upload-open.window="open = true"
     @keydown.escape.window="open = false"
     x-show="open"
     @if (! $openUpload && ! ($errors->any() && ! $editFile && ! $linksFile)) style="display: none" @endif
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/50 px-4 py-10 backdrop-blur-sm"
     role="dialog" aria-modal="true" aria-labelledby="vault-upload-title">
    <div class="relative w-full max-w-2xl rounded-2xl bg-white shadow-2xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700" @click.outside="open = false">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-slate-700">
            <div class="flex items-center gap-3">
                <x-cf-icon name="upload" variant="tile" tone="orange" size="sm" />
                <div>
                    <h3 id="vault-upload-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ __('vault_upload_title') }}</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('vault_upload_limits', ['max' => (int) round($maxKb / 1024)]) }}</p>
                </div>
            </div>
            <a href="{{ route($rp.'index') }}" @click.prevent="open = false" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800" aria-label="{{ __('Close') }}"><x-cf-icon name="xmark" size="xs" /></a>
        </div>
        <form method="POST" action="{{ route($rp.'store') }}" enctype="multipart/form-data" class="space-y-4 px-6 py-5">
            @csrf
            <label class="flex flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-orange-200 bg-orange-50/50 px-4 py-6 text-center hover:border-orange-400 dark:border-orange-900/50 dark:bg-orange-950/10"
                   x-data="{ names: '' }">
                <x-cf-icon name="vault" variant="tile" tone="orange" size="md" />
                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('vault_drop_label') }}</span>
                <span class="text-xs text-slate-500 dark:text-slate-400" x-text="names || @js(__('vault_drop_help'))">{{ __('vault_drop_help') }}</span>
                <input type="file" name="files[]" multiple required class="sr-only"
                       accept="{{ collect($allowed)->map(fn ($e) => '.'.$e)->join(',') }}"
                       @change="names = Array.from($event.target.files).map(f => f.name).join(', ')">
            </label>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <x-input-label for="up_folder" :value="__('vault_field_folder')" class="!text-xs" />
                    <x-text-input id="up_folder" name="folder" type="text" list="vault-folders" :value="old('folder')" class="mt-1 block w-full" placeholder="{{ __('vault_folder_placeholder') }}" />
                    <datalist id="vault-folders">@foreach ($folders as $fo)<option value="{{ $fo }}">@endforeach</datalist>
                </div>
                @if ($area !== 'company')
                    <div>
                        <x-input-label for="up_project" :value="__('Project')" class="!text-xs" />
                        <select id="up_project" name="project_id" class="flow-input mt-1 block w-full">
                            <option value="">{{ $area === 'portal' ? __('vault_portal_no_project') : __('vault_none') }}</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" @selected(old('project_id') === (string) $p->id)>{{ $p->title }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="{{ $area === 'company' ? '' : 'sm:col-span-2' }}">
                    <x-input-label for="up_description" :value="__('vault_field_description')" class="!text-xs" />
                    <x-text-input id="up_description" name="description" type="text" :value="old('description')" maxlength="1000" class="mt-1 block w-full" placeholder="{{ __('vault_description_placeholder') }}" />
                </div>
            </div>

            @if ($area === 'company')
                @include('vault.partials.share-fields', ['file' => null])
            @else
                <div class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2.5 text-xs text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
                    <x-cf-icon name="info" size="xs" />
                    <p>{{ $area === 'portal' ? __('vault_portal_upload_help') : __('vault_provider_upload_help') }}</p>
                </div>
            @endif

            <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-700">
                <a href="{{ route($rp.'index') }}" @click.prevent="open = false" class="text-sm font-medium text-slate-500 hover:text-slate-800 dark:text-slate-400">{{ __('Cancel') }}</a>
                <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-orange-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-orange-500">
                    <x-cf-icon name="upload" size="xs" />
                    {{ __('vault_upload_submit') }}
                </button>
            </div>
        </form>
    </div>
</div>
