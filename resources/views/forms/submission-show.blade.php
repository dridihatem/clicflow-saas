<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Submission') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">
                    {{ $form->name }}
                    · {{ $submission->created_at?->format('Y-m-d H:i') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('forms.submissions.index', $form) }}">
                    <x-secondary-button type="button" class="!normal-case">{{ __('Back to submissions') }}</x-secondary-button>
                </a>
                @if ($existingProject)
                    <a href="{{ route('projects.show', $existingProject) }}">
                        <x-primary-button type="button" class="!normal-case">{{ __('View project') }}</x-primary-button>
                    </a>
                @else
                    <form method="POST" action="{{ route('form-submissions.convert-project', $submission) }}">
                        @csrf
                        <input type="hidden" name="with_ai" value="1">
                        <x-primary-button type="submit" class="!normal-case">{{ __('Create project with AI') }}</x-primary-button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-3xl w-full sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/50 dark:text-emerald-100">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-xl border border-rose-200/80 bg-rose-50/90 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/50 dark:text-rose-100">{{ session('error') }}</div>
            @endif

            <div class="flow-panel p-6 sm:p-8 space-y-5">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Contact') }}</h3>
                <dl class="grid gap-4 sm:grid-cols-3 text-sm">
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Name') }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $contact['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Email') }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white">{{ $contact['email'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Phone') }}</dt>
                        <dd class="mt-1 font-medium text-slate-900 dark:text-white flowdesk-ltr-num">{{ $contact['phone'] ?? '—' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flow-panel p-6 sm:p-8 space-y-4">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Form submission') }}</h3>
                @if ($data === [])
                    <p class="text-sm text-slate-500">{{ __('No fields submitted.') }}</p>
                @else
                    <dl class="divide-y divide-slate-200/80 dark:divide-slate-700/80">
                        @foreach ($data as $key => $value)
                            <div class="grid gap-1 py-3 sm:grid-cols-[12rem_1fr] sm:gap-4">
                                <dt class="text-sm font-medium text-slate-500 dark:text-slate-400">
                                    {{ is_string($key) ? \Illuminate\Support\Str::headline(str_replace(['_', '-'], ' ', $key)) : __('Field') }}
                                </dt>
                                <dd class="text-sm text-slate-900 dark:text-white whitespace-pre-wrap break-words">
                                    @if (is_bool($value))
                                        {{ $value ? __('Yes') : __('No') }}
                                    @elseif (is_array($value))
                                        {{ implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $value)) }}
                                    @elseif ($value === null || $value === '')
                                        —
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            @unless ($existingProject)
                <div class="flow-panel p-6 sm:p-8 space-y-3">
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ __('Create project') }}</h3>
                    <p class="text-sm text-slate-600 dark:text-slate-300">
                        {{ __('Creates a client with name and phone when available, then uses AI to draft tasks, final price, and negotiated price.') }}
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('form-submissions.convert-project', $submission) }}">
                            @csrf
                            <input type="hidden" name="with_ai" value="1">
                            <x-primary-button type="submit" class="!normal-case">{{ __('Create project with AI') }}</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('form-submissions.convert-project', $submission) }}">
                            @csrf
                            <input type="hidden" name="with_ai" value="0">
                            <x-secondary-button type="submit" class="!normal-case">{{ __('Create project without AI') }}</x-secondary-button>
                        </form>
                    </div>
                </div>
            @endunless
        </div>
    </div>
</x-app-layout>
