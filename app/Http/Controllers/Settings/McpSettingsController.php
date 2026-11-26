<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class McpSettingsController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('workspace.manage_settings') && ! $user?->hasRole('company_admin'), 403);
        $company = $user->company;
        abort_if(! $company, 403);

        $tokens = McpToken::query()
            ->where('tokenable_type', McpToken::AUDIENCE_COMPANY)
            ->where('tokenable_id', $company->id)
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('refresh_expires_at')->orWhere('refresh_expires_at', '>', now()))
            ->where(fn ($q) => $q->whereNotNull('refresh_token_hash')->orWhereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get();

        $endpoint = rtrim(flowdesk_tenant_url($company, '/mcp'), '/');
        $revealed = $request->session()->pull('cf_mcp_token_plain');

        return view('settings.mcp', [
            'tokens' => $tokens,
            'endpoint' => $endpoint,
            'revealedToken' => is_string($revealed) ? $revealed : null,
            'audience' => 'company',
            'displayName' => \App\Services\Mcp\McpOAuthMetadata::displayName($company),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->hasRole('company_admin'), 403);
        $company = $user->company;
        abort_if(! $company, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:read,write'],
        ], [
            'name.required' => __('mcp_token_name_required'),
            'scopes.required' => __('mcp_scope_required'),
        ]);

        $issued = McpToken::issue(McpToken::AUDIENCE_COMPANY, $company->id, $data['name'], $data['scopes']);
        $request->session()->flash('cf_mcp_token_plain', $issued['plain']);

        return redirect()->route('settings.mcp')->with('status', __('mcp_token_created'));
    }

    public function destroy(Request $request, McpToken $mcpToken): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->hasRole('company_admin'), 403);
        $company = $user->company;
        abort_if(! $company, 403);
        abort_if($mcpToken->tokenable_type !== McpToken::AUDIENCE_COMPANY || (string) $mcpToken->tokenable_id !== (string) $company->id, 404);

        $mcpToken->revoke();

        return redirect()->route('settings.mcp')->with('status', __('mcp_token_revoked'));
    }
}
