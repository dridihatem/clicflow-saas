@props(['prospect' => null])

@php
    $socials = is_array(old('socials'))
        ? old('socials')
        : ($prospect?->socials ?? []);
@endphp

<div>
    <x-input-label for="prospect_name" :value="__('Name')" />
    <x-text-input id="prospect_name" name="name" type="text" class="mt-2 block w-full" :value="old('name', $prospect?->name)" required />
    <x-input-error :messages="$errors->get('name')" class="mt-2" />
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="prospect_email" :value="__('Email')" />
        <x-text-input id="prospect_email" name="email" type="email" class="mt-2 block w-full" :value="old('email', $prospect?->email)" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="prospect_phone" :value="__('Phone')" />
        <x-text-input id="prospect_phone" name="phone" type="text" class="mt-2 block w-full" :value="old('phone', $prospect?->phone)" />
        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="prospect_whatsapp" :value="__('prospect_whatsapp')" />
        <x-text-input id="prospect_whatsapp" name="whatsapp" type="text" class="mt-2 block w-full" :value="old('whatsapp', $prospect?->whatsapp)" />
        <x-input-error :messages="$errors->get('whatsapp')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="prospect_company_name" :value="__('prospect_company_name')" />
        <x-text-input id="prospect_company_name" name="company_name" type="text" class="mt-2 block w-full" :value="old('company_name', $prospect?->company_name)" />
        <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
    </div>
</div>

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="prospect_job_title" :value="__('prospect_job_title')" />
        <x-text-input id="prospect_job_title" name="job_title" type="text" class="mt-2 block w-full" :value="old('job_title', $prospect?->job_title)" />
        <x-input-error :messages="$errors->get('job_title')" class="mt-2" />
    </div>
    <div>
        <x-input-label for="prospect_source" :value="__('prospect_source_label')" />
        <select id="prospect_source" name="source" class="flow-input mt-2 block w-full">
            @foreach (\App\Enums\ProspectSource::cases() as $sourceCase)
                <option value="{{ $sourceCase->value }}" @selected(old('source', $prospect?->source?->value ?? \App\Enums\ProspectSource::Manual->value) === $sourceCase->value)>{{ $sourceCase->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('source')" class="mt-2" />
    </div>
</div>

@if ($prospect)
    <div>
        <x-input-label for="prospect_status" :value="__('prospect_status_label')" />
        <select id="prospect_status" name="status" class="flow-input mt-2 block w-full">
            @foreach (\App\Enums\ProspectStatus::cases() as $statusCase)
                <option value="{{ $statusCase->value }}" @selected(old('status', $prospect->status?->value) === $statusCase->value)>{{ $statusCase->label() }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('status')" class="mt-2" />
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <x-input-label for="prospect_linkedin" :value="__('prospect_social_linkedin')" />
        <x-text-input id="prospect_linkedin" name="linkedin" type="text" class="mt-2 block w-full" :value="old('linkedin', $socials['linkedin'] ?? null)" />
    </div>
    <div>
        <x-input-label for="prospect_website" :value="__('prospect_social_website')" />
        <x-text-input id="prospect_website" name="website" type="text" class="mt-2 block w-full" :value="old('website', $socials['website'] ?? null)" />
    </div>
    <div>
        <x-input-label for="prospect_twitter" :value="__('prospect_social_twitter')" />
        <x-text-input id="prospect_twitter" name="twitter" type="text" class="mt-2 block w-full" :value="old('twitter', $socials['twitter'] ?? null)" />
    </div>
    <div>
        <x-input-label for="prospect_facebook" :value="__('prospect_social_facebook')" />
        <x-text-input id="prospect_facebook" name="facebook" type="text" class="mt-2 block w-full" :value="old('facebook', $socials['facebook'] ?? null)" />
    </div>
    <div>
        <x-input-label for="prospect_instagram" :value="__('prospect_social_instagram')" />
        <x-text-input id="prospect_instagram" name="instagram" type="text" class="mt-2 block w-full" :value="old('instagram', $socials['instagram'] ?? null)" />
    </div>
</div>

<div>
    <x-input-label for="prospect_notes" :value="__('Notes')" />
    <textarea id="prospect_notes" name="notes" rows="3" class="mt-2 block w-full rounded-lg border-slate-300 shadow-sm dark:border-slate-600 dark:bg-slate-900 dark:text-white">{{ old('notes', $prospect?->notes) }}</textarea>
    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
</div>
