<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Settings\Concerns\AuthorizesWorkspaceManagers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkspaceLocaleController extends Controller
{
    use AuthorizesWorkspaceManagers;

    public function edit(Request $request): View
    {
        $user = $this->authorizeWorkspaceManagers($request);
        $company = $user->company;

        return view('settings.workspace-locale', [
            'company' => $company,
            'locales' => config('flowdesk.locales', ['fr', 'en', 'es', 'ar']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->authorizeWorkspaceManagers($request);
        $company = $user->company;

        $data = $request->validate([
            'default_locale' => ['required', 'string', Rule::in(config('flowdesk.locales', ['fr', 'en', 'es', 'ar']))],
        ]);

        $company->update([
            'default_locale' => $data['default_locale'],
        ]);

        return redirect()
            ->route('settings.workspace-locale')
            ->with('status', __('settings_workspace_locale_saved'));
    }
}
