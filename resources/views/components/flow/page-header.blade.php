@props([
    'title',
    'description' => null,
    'icon' => null,
    'iconTone' => 'orange',
])
<div {{ $attributes->merge(['class' => 'mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between']) }}>
    <div class="flex min-w-0 items-start gap-3.5">
        @if ($icon)
            <x-cf-icon :name="$icon" variant="tile" :tone="$iconTone" size="xl" class="shrink-0" />
        @endif
        <div class="min-w-0">
            <h1 class="flow-font-display text-2xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-3xl">
                {{ $title }}
            </h1>
            @isset($description)
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">{{ $description }}</p>
            @endisset
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
