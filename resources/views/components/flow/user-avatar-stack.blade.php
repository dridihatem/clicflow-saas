@props([
    'users' => [],
    'size' => 'md',
    'limit' => 6,
])

@php
    $collection = collect($users)->filter()->values();
    $shown = $collection->take($limit);
    $extra = max(0, $collection->count() - $shown->count());
    $sizeClass = match ($size) {
        'sm' => 'h-7 w-7 text-[10px]',
        'lg' => 'h-10 w-10 text-sm',
        default => 'h-8 w-8 text-[11px]',
    };
    $tones = [
        'bg-sky-500/20 text-sky-800 ring-sky-500/30 dark:text-sky-200',
        'bg-teal-500/20 text-teal-800 ring-teal-500/30 dark:text-teal-200',
        'bg-violet-500/20 text-violet-800 ring-violet-500/30 dark:text-violet-200',
        'bg-amber-500/20 text-amber-900 ring-amber-500/30 dark:text-amber-200',
        'bg-rose-500/20 text-rose-800 ring-rose-500/30 dark:text-rose-200',
        'bg-indigo-500/20 text-indigo-800 ring-indigo-500/30 dark:text-indigo-200',
    ];
@endphp

@if ($shown->isNotEmpty())
    <div {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <div class="flex -space-x-2">
            @foreach ($shown as $index => $user)
                @php
                    $roleRaw = $user->pivot->role ?? null;
                    $roleLabel = $roleRaw instanceof \App\Enums\TaskAssigneeRole
                        ? $roleRaw->label()
                        : (\App\Enums\TaskAssigneeRole::tryFrom((string) $roleRaw)?->label());
                    $title = $user->name.($roleLabel ? ' · '.$roleLabel : '');
                    $tone = $tones[$index % count($tones)];
                @endphp
                <span
                    class="relative inline-flex {{ $sizeClass }} items-center justify-center rounded-full font-semibold ring-2 ring-white dark:ring-slate-900 {{ $tone }}"
                    title="{{ $title }}"
                >{{ $user->initials() }}</span>
            @endforeach
            @if ($extra > 0)
                <span class="relative inline-flex {{ $sizeClass }} items-center justify-center rounded-full bg-slate-200 text-slate-700 ring-2 ring-white dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-900 font-semibold" title="+{{ $extra }}">+{{ $extra }}</span>
            @endif
        </div>
    </div>
@endif
