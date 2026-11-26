<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit prospect') }}</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ $prospect->name }}</p>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-2xl w-full sm:px-6 lg:px-8">
            <div class="flow-panel p-6 sm:p-8">
                <form method="POST" action="{{ route('prospects.update', $prospect) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    @include('prospects._form', ['prospect' => $prospect])
                    <div class="flex flex-wrap gap-3 pt-2">
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                        <a href="{{ route('prospects.show', $prospect) }}" class="inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200">{{ __('Cancel') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
