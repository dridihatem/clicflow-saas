<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold leading-tight text-slate-800 dark:text-slate-100">{{ __('Team & roles') }}</h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-5xl w-full sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif

            <x-flow.page-header
                class="mb-8"
                :title="__('People in this workspace')"
                :description="__('team_page_intro')"
            />

            <div class="mb-6 rounded-xl border border-slate-200/80 bg-slate-50/80 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-800/40 dark:text-slate-300">
                {{ __('team_permissions_hint') }}
            </div>

            <div class="space-y-4">
                @foreach ($users as $u)
                    @php
                        $currentRole = $u->roles->first()?->name ?? 'team_member';
                        $directPerms = $u->getPermissionNames()->all();
                        $memberSkills = $u->skills();
                    @endphp
                    <div class="flow-panel p-5">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $u->name }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $u->email }}</p>
                                @if ($memberSkills !== [])
                                    <div class="mt-2 flex flex-wrap gap-1.5">
                                        @foreach ($memberSkills as $skill)
                                            <span class="rounded-full bg-cyan-500/15 px-2 py-0.5 text-[11px] font-medium text-cyan-900 dark:text-cyan-100">{{ $skill }}</span>
                                        @endforeach
                                    </div>
                                @endif
                                @if ($currentRole === 'team_member')
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('team_member_projects_hint') }}</p>
                                @endif
                            </div>
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('settings.team.destroy', $u) }}" onsubmit="return confirm({{ json_encode(__('Remove this user from the workspace?')) }});">
                                    @csrf
                                    @method('DELETE')
                                    <x-danger-button type="submit">{{ __('Remove') }}</x-danger-button>
                                </form>
                            @endif
                        </div>

                        @if ($u->id === auth()->id())
                            <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">{{ $roleOptions[$currentRole] ?? $currentRole }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ __('Use profile or ask another admin to change your role.') }}</p>
                            <form method="POST" action="{{ route('settings.team.update', $u) }}" class="mt-4 space-y-4">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="role" value="{{ $currentRole }}" />
                                <div>
                                    <x-input-label :for="'skills-'.$u->id" :value="__('team_skills_label')" />
                                    <x-text-input
                                        :id="'skills-'.$u->id"
                                        name="skills"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :value="old('skills', $u->skillsLabel())"
                                        :placeholder="__('team_skills_placeholder')"
                                    />
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('team_skills_help') }}</p>
                                    <x-input-error :messages="$errors->get('skills')" class="mt-2" />
                                </div>
                                <x-secondary-button type="submit">{{ __('Save') }}</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('settings.team.update', $u) }}" class="mt-4 space-y-4" x-data="{ role: @js($currentRole) }">
                                @csrf
                                @method('PUT')
                                <div class="max-w-sm">
                                    <x-input-label :for="'role-'.$u->id" :value="__('Role')" />
                                    <select id="role-{{ $u->id }}" name="role" x-model="role" class="flow-input-select mt-1 block w-full text-sm">
                                        @foreach ($roleOptions as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <x-input-label :for="'skills-'.$u->id" :value="__('team_skills_label')" />
                                    <x-text-input
                                        :id="'skills-'.$u->id"
                                        name="skills"
                                        type="text"
                                        class="mt-1 block w-full"
                                        :value="old('skills', $u->skillsLabel())"
                                        :placeholder="__('team_skills_placeholder')"
                                    />
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('team_skills_help') }}</p>
                                    <x-input-error :messages="$errors->get('skills')" class="mt-2" />
                                </div>

                                <div x-show="role === 'team_member'" x-cloak class="rounded-xl border border-slate-200/80 bg-white/70 p-4 dark:border-slate-700 dark:bg-slate-900/40">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('team_optional_permissions') }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('team_optional_permissions_help') }}</p>
                                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                        @foreach ($optionalPermissions as $perm => $labelKey)
                                            <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                                <input
                                                    type="checkbox"
                                                    name="permissions[]"
                                                    value="{{ $perm }}"
                                                    class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800"
                                                    @checked(in_array($perm, $directPerms, true))
                                                />
                                                {{ __($labelKey) }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>

                                <x-secondary-button type="submit">{{ __('Save') }}</x-secondary-button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-10 flow-panel p-8" x-data="{ role: @js(old('role', 'team_member')) }">
                <h3 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ __('Invite a teammate') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ __('They can sign in immediately with this email and password.') }}</p>

                <form method="POST" action="{{ route('settings.team.store') }}" class="mt-6 space-y-5">
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
                        </div>
                    </div>

                    <div class="max-w-md">
                        <x-input-label for="role" :value="__('Role')" />
                        <select id="role" name="role" x-model="role" class="flow-input-select mt-1 block w-full">
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="skills" :value="__('team_skills_label')" />
                        <x-text-input
                            id="skills"
                            name="skills"
                            type="text"
                            class="mt-1 block w-full"
                            :value="old('skills')"
                            :placeholder="__('team_skills_placeholder')"
                        />
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('team_skills_help') }}</p>
                        <x-input-error :messages="$errors->get('skills')" class="mt-2" />
                    </div>

                    <div x-show="role === 'team_member'" x-cloak class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 dark:border-slate-700 dark:bg-slate-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('team_optional_permissions') }}</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            @foreach ($optionalPermissions as $perm => $labelKey)
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $perm }}"
                                        class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500 dark:border-slate-600 dark:bg-slate-800"
                                        @checked(in_array($perm, old('permissions', []), true))
                                    />
                                    {{ __($labelKey) }}
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <x-primary-button>{{ __('Add user') }}</x-primary-button>
                </form>
            </div>

            <x-input-error :messages="$errors->get('delete')" class="mt-6" />
            <x-input-error :messages="$errors->get('role')" class="mt-6" />
        </div>
    </div>
</x-app-layout>
