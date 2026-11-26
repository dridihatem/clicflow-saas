@php
    $idPrefix = $idPrefix ?? '';
@endphp
<section class="flow-panel space-y-5 p-6 sm:p-8">
    <div class="border-b border-slate-200/80 pb-4 dark:border-slate-700/80">
        <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ __('project_study_section_identity') }}</h3>
    </div>

    <div>
        <x-input-label :for="$idPrefix.'title'" :value="__('Title')" />
        <x-text-input :id="$idPrefix.'title'" name="title" class="mt-1 block w-full" :value="old('title', $study->title)" required maxlength="255" />
        <x-input-error class="mt-2" :messages="$errors->get('title')" />
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <x-input-label :for="$idPrefix.'client_id'" :value="__('Client')" />
            <x-project-client-quick-add
                :select-id="$idPrefix.'client_id'"
                :intro="__('Saves immediately and adds to the list for this project study.')"
            >
                <select id="{{ $idPrefix }}client_id" name="client_id" class="flow-input mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(old('client_id', $study->client_id) === (string) $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </x-project-client-quick-add>
        </div>
        <div>
            <x-input-label :for="$idPrefix.'project_id'" :value="__('Project')" />
            <select id="{{ $idPrefix }}project_id" name="project_id" class="flow-input mt-1 block w-full">
                <option value="">—</option>
                @foreach ($projects as $project)
                    <option value="{{ $project->id }}" @selected(old('project_id', $study->project_id) === (string) $project->id)>{{ $project->title }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <x-input-label :for="$idPrefix.'status'" :value="__('Status')" />
            <select id="{{ $idPrefix }}status" name="status" class="flow-input mt-1 block w-full">
                @foreach (['draft', 'generated', 'final'] as $statusKey)
                    <option value="{{ $statusKey }}" @selected(old('status', $study->status) === $statusKey)>{{ __('project_study_status_'.$statusKey) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <x-input-label :for="$idPrefix.'budget'" :value="__('Budget')" />
            <x-text-input :id="$idPrefix.'budget'" name="budget" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('budget', $budgetDisplay)" />
        </div>
        <div>
            <x-input-label :for="$idPrefix.'currency'" :value="__('Currency')" />
            <x-text-input :id="$idPrefix.'currency'" name="currency" class="mt-1 block w-full" :value="old('currency', $study->currency)" maxlength="3" />
        </div>
    </div>
</section>
