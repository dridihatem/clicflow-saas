<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('New quote') }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-12xl w-full px-4 sm:px-6 lg:px-8">
            @include('proposals.partials.quote-editor-form')
        </div>
    </div>
</x-app-layout>
