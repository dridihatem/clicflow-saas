@include('contracts.partials.summernote', [
    'textareaId' => 'template_body_html',
    'hintsId' => 'contract-merge-hints',
    'typeSelectId' => 'template_type_key',
    'defaultType' => 'service',
    'height' => 480,
])

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('New template') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('contract-templates.store') }}" class="space-y-5">
                @csrf
                @include('contract-templates._form', ['template' => null, 'mergeHints' => $mergeHints])
                <div class="flex justify-end gap-2">
                    <a href="{{ route('contract-templates.index') }}"><x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button></a>
                    <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
