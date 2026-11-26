@php
    $f = $file ?? null;
    $selClient = old('client_id', $f?->client_id);
    $selProject = old('project_id', $f?->project_id);
    $selUsers = collect(old('user_ids', $f ? $f->sharedUsers->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all();
    $chk = fn (string $k, bool $default) => (bool) old($k, $f ? $f->{$k} : $default);
    $prefix = $f ? 'edit_' : 'up_';
@endphp
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div>
        <x-input-label :for="$prefix.'client'" :value="__('Client')" class="!text-xs" />
        <select id="{{ $prefix }}client" name="client_id" class="flow-input mt-1 block w-full">
            <option value="">{{ __('vault_none') }}</option>
            @foreach ($clients as $c)
                <option value="{{ $c->id }}" @selected((string) $selClient === (string) $c->id)>{{ $c->company_name ?: $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <x-input-label :for="$prefix.'project'" :value="__('Project')" class="!text-xs" />
        <select id="{{ $prefix }}project" name="project_id" class="flow-input mt-1 block w-full">
            <option value="">{{ __('vault_none') }}</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}" @selected((string) $selProject === (string) $p->id)>{{ $p->title }}</option>
            @endforeach
            @if ($f && $f->project_id && ! $projects->contains('id', $f->project_id) && $f->project)
                <option value="{{ $f->project_id }}" selected>{{ $f->project->title }}</option>
            @endif
        </select>
    </div>
</div>

<fieldset class="mt-4">
    <legend class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('vault_share_legend') }}</legend>
    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('vault_share_help') }}</p>
    <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
        @foreach ([
            ['share_client', 'vault_share_client', 'vault_share_client_help', 'user', 'orange', false],
            ['share_project', 'vault_share_project', 'vault_share_project_help', 'projects', 'blue', true],
            ['share_team', 'vault_share_team', 'vault_share_team_help', 'users', 'emerald', false],
            ['share_providers', 'vault_share_providers', 'vault_share_providers_help', 'handshake', 'violet', false],
        ] as [$key, $label, $help, $icon, $tone, $default])
            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 hover:border-orange-300 dark:border-slate-700 dark:bg-slate-900">
                <input type="hidden" name="{{ $key }}" value="0">
                <input type="checkbox" name="{{ $key }}" value="1" class="mt-1 rounded border-slate-300 text-orange-600 focus:ring-orange-500" @checked($chk($key, $default))>
                <x-cf-icon :name="$icon" variant="tile" :tone="$tone" size="sm" />
                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __($label) }}</span>
                    <span class="block text-[11px] leading-snug text-slate-500 dark:text-slate-400">{{ __($help) }}</span>
                </span>
            </label>
        @endforeach
    </div>
</fieldset>

@if ($members->isNotEmpty())
    <details class="mt-3 rounded-xl border border-slate-200 px-3 py-2 dark:border-slate-700" @if ($selUsers) open @endif>
        <summary class="cursor-pointer text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('vault_share_members') }} @if ($selUsers)<span class="text-orange-600">({{ count($selUsers) }})</span>@endif</summary>
        <div class="mt-2 grid max-h-40 grid-cols-1 gap-1 overflow-y-auto sm:grid-cols-2">
            @foreach ($members as $m)
                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                    <input type="checkbox" name="user_ids[]" value="{{ $m->id }}" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" @checked(in_array((int) $m->id, $selUsers, true))>
                    <span class="truncate">{{ $m->name }}</span>
                </label>
            @endforeach
        </div>
    </details>
@endif

<label class="mt-3 flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
    <input type="hidden" name="notify_client" value="0">
    <input type="checkbox" name="notify_client" value="1" class="rounded border-slate-300 text-orange-600 focus:ring-orange-500" @checked(old('notify_client'))>
    {{ __('vault_notify_client') }}
</label>
