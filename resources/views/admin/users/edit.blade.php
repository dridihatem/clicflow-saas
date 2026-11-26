<x-admin-layout :title="$user->name">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a
            href="{{ route('admin.users.index') }}"
            class="flow-secondary-button inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold shadow-sm transition hover:text-slate-900"
        >
            <i class="fa-solid fa-arrow-left text-xs" aria-hidden="true"></i>
            <span>{{ __('Back to users') }}</span>
        </a>
    </div>

    <x-flow.page-header
        :title="$user->name"
        :description="$user->email"
    />

    <div class="grid gap-6 lg:grid-cols-12">
        <div class="flow-panel lg:col-span-5 p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Profile') }}</h3>

            <dl class="mt-4 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">{{ __('Email verified') }}</dt>
                    <dd class="font-semibold text-slate-900">{{ $user->email_verified_at ? __('Yes') : __('No') }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">{{ __('Company') }}</dt>
                    <dd class="font-semibold text-slate-900">{{ $user->company?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">{{ __('Two-factor') }}</dt>
                    <dd class="font-semibold text-slate-900">{{ $user->hasTwoFactorEnabled() ? __('Enabled') : __('Not enabled') }}</dd>
                </div>
            </dl>
        </div>

        <div class="flow-panel lg:col-span-7 p-6">
            <h3 class="text-sm font-semibold text-slate-900">{{ __('Roles') }}</h3>
            <p class="mt-1 text-xs text-slate-600">{{ __('Select roles to control where the user can sign in and what they can access.') }}</p>

            <form method="POST" action="{{ route('admin.users.update', $user) }}" class="mt-5 space-y-4">
                @csrf
                @method('PUT')

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($roles as $r)
                        <label class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-800 shadow-sm">
                            <span class="inline-flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-slate-50 text-slate-600 ring-1 ring-slate-200">
                                    <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                                </span>
                                <span class="font-mono">{{ $r }}</span>
                            </span>
                            <input
                                type="checkbox"
                                name="roles[]"
                                value="{{ $r }}"
                                class="h-4 w-4 rounded border-slate-300 text-[var(--flow-primary)] focus:ring-[var(--flow-primary)]"
                                @checked($user->hasRole($r))
                            />
                        </label>
                    @endforeach
                </div>

                <div class="pt-2">
                    <button type="submit" class="flow-primary-button inline-flex items-center gap-2 rounded-lg border border-transparent bg-[var(--flow-primary)] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[var(--flow-primary-hover)]">
                        <i class="fa-regular fa-floppy-disk" aria-hidden="true"></i>
                        <span>{{ __('Save roles') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="mt-6 flow-panel p-6">
        <h3 class="text-sm font-semibold text-slate-900">{{ __('Delete user') }}</h3>
        <p class="mt-1 text-xs text-slate-600">{{ __('Permanently deletes this user, their roles, API tokens and sessions. Records they created stay in the workspace.') }}</p>
        @if ($errors->has('delete'))
            <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{{ $errors->first('delete') }}</div>
        @endif
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-4">
            @csrf
            @method('DELETE')
            <button
                type="submit"
                class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700"
                onclick="return confirm(@js(__('Delete user :name (:email)? This cannot be undone.', ['name' => $user->name, 'email' => $user->email])))"
            >
                <i class="fa-regular fa-trash-can" aria-hidden="true"></i>
                <span>{{ __('Delete user') }}</span>
            </button>
        </form>
    </div>
</x-admin-layout>

