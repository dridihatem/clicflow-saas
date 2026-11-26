@include('contracts.partials.summernote', [
    'textareaId' => 'template_body_html',
    'hintsId' => 'contract-merge-hints',
    'typeSelectId' => 'template_type_key',
    'defaultType' => $template->type_key ?? 'service',
    'height' => 480,
])

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-lg font-semibold tracking-tight text-slate-900 dark:text-white">{{ __('Edit template') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-5 rounded-xl border border-emerald-200/80 bg-emerald-50/90 px-4 py-3 text-sm text-emerald-900">{{ session('status') }}</div>
            @endif
            <form method="POST" action="{{ route('contract-templates.update', $template) }}" class="space-y-5">
                @csrf
                @method('PUT')
                @include('contract-templates._form', ['template' => $template, 'mergeHints' => $mergeHints])
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <button type="submit" form="delete-template-form" class="text-sm font-medium text-rose-700 hover:underline" onclick="return confirm(@js(__('Are you sure?')))">{{ __('Delete') }}</button>
                    <div class="flex gap-2">
                        <a href="{{ route('contract-templates.index') }}"><x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button></a>
                        <x-primary-button type="submit">{{ __('Save') }}</x-primary-button>
                    </div>
                </div>
            </form>
            <form id="delete-template-form" method="POST" action="{{ route('contract-templates.destroy', $template) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
</x-app-layout>
