<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\McpToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminMcpSettingsController extends Controller
{
    public function show(Request $request): View
    {
        $admin = $request->user('admin');
        abort_if(! $admin, 403);

        $tokens = McpToken::query()
            ->where('tokenable_type', McpToken::AUDIENCE_ADMIN)
            ->where('tokenable_id', $admin->id)
            ->whereNull('revoked_at')
            ->latest()
            ->get();

        $endpoint = rtrim((string) config('flowdesk.email_asset_base', 'https://app.clicflow.fr'), '/').'/admin/mcp';
        $revealed = $request->session()->pull('cf_mcp_token_plain');

        return view('admin.mcp.index', [
            'tokens' => $tokens,
            'endpoint' => $endpoint,
            'revealedToken' => is_string($revealed) ? $revealed : null,
            'audience' => 'admin',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_if(! $admin, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['in:read,write'],
        ], [
            'name.required' => __('mcp_token_name_required'),
            'scopes.required' => __('mcp_scope_required'),
        ]);

        $issued = McpToken::issue(McpToken::AUDIENCE_ADMIN, $admin->id, $data['name'], $data['scopes']);
        $request->session()->flash('cf_mcp_token_plain', $issued['plain']);

        return redirect()->route('admin.mcp-settings.show')->with('status', __('mcp_token_created'));
    }

    public function destroy(Request $request, McpToken $mcpToken): RedirectResponse
    {
        $admin = $request->user('admin');
        abort_if(! $admin, 403);
        abort_if($mcpToken->tokenable_type !== McpToken::AUDIENCE_ADMIN || (string) $mcpToken->tokenable_id !== (string) $admin->id, 404);

        $mcpToken->revoke();

        return redirect()->route('admin.mcp-settings.show')->with('status', __('mcp_token_revoked'));
    }
}
