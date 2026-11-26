@php
    $selectedIds = collect($selectedIds ?? [])->map(fn ($id) => (string) $id)->all();
    $selectedRoles = $selectedRoles ?? [];
@endphp
<div class="{{ $class ?? 'sm:col-span-2 lg:col-span-3' }}">
    <x-input-label :value="__('task_assignees_label')" />
    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ __('task_assignees_help') }}</p>
    <div class="mt-2 max-h-56 space-y-2 overflow-y-auto rounded-xl border border-slate-200/80 bg-white/70 p-3 dark:border-slate-600/50 dark:bg-slate-900/40">
        @forelse ($teamUsers ?? [] as $member)
            @php
                $checked = in_array((string) $member->id, $selectedIds, true);
                $role = old('assignee_roles.'.$member->id, $selectedRoles[$member->id] ?? \App\Enums\TaskAssigneeRole::Follower->value);
            @endphp
            <div class="flex flex-wrap items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-50 dark:hover:bg-slate-800/60">
                <label class="inline-flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-sm text-slate-800 dark:text-slate-100">
                    <input
                        type="checkbox"
                        name="assignee_ids[]"
                        value="{{ $member->id }}"
                        class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600"
                        @checked($checked)
                    />
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-sky-500/20 text-[10px] font-semibold text-sky-800 dark:text-sky-200">{{ $member->initials() }}</span>
                    <span class="min-w-0 truncate">{{ $member->assigneeOptionLabel() }}</span>
                </label>
                <select name="assignee_roles[{{ $member->id }}]" class="flow-input-select w-36 text-xs">
                    @foreach (\App\Enums\TaskAssigneeRole::cases() as $roleCase)
                        <option value="{{ $roleCase->value }}" @selected((string) $role === $roleCase->value)>{{ $roleCase->label() }}</option>
                    @endforeach
                </select>
            </div>
        @empty
            <p class="text-sm text-slate-500">{{ __('task_assignees_empty') }}</p>
        @endforelse
    </div>
</div>
