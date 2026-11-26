<x-app-layout>
    <div class="py-10">
        <div class="max-w-4xl w-full sm:px-6 lg:px-8">
            <x-flow.page-header
                icon="wand-magic-sparkles"
                icon-tone="orange"
                :title="__('mcp_settings_title')"
                :description="__('mcp_settings_intro')"
            />
            <div class="mt-8">
                @include('settings.partials.mcp-panel', [
                    'storeRoute' => route('settings.mcp.store'),
                    'revokeRoute' => fn ($tok) => route('settings.mcp.destroy', $tok),
                    'toolsAudience' => 'company',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
