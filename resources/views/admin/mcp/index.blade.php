<x-admin-layout :title="__('mcp_admin_title')">
    <div class="py-10">
        <div class="max-w-4xl w-full sm:px-6 lg:px-8">
            <x-flow.page-header
                icon="wand-magic-sparkles"
                icon-tone="violet"
                :title="__('mcp_admin_title')"
                :description="__('mcp_admin_intro')"
            />
            <div class="mt-8">
                @include('settings.partials.mcp-panel', [
                    'storeRoute' => route('admin.mcp-settings.store'),
                    'revokeRoute' => fn ($tok) => route('admin.mcp-settings.destroy', $tok),
                    'toolsAudience' => 'admin',
                ])
            </div>
        </div>
    </div>
</x-admin-layout>
