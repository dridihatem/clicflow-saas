<x-admin-layout :title="__('Profile')">
    <x-flow.page-header
        :title="__('Profile')"
        :description="__('admin_profile_intro')"
    />

    <div class="mt-8 max-w-2xl space-y-6">
        <div class="flow-panel p-6">
            @include('admin.profile.partials.update-profile-information-form')
        </div>

        <div class="flow-panel p-6">
            @include('admin.profile.partials.update-password-form')
        </div>

        <div class="flow-panel border-rose-200 bg-rose-50/70 p-6 dark:border-rose-500/25 dark:bg-rose-950/20">
            @include('admin.profile.partials.delete-user-form')
        </div>
    </div>
</x-admin-layout>
