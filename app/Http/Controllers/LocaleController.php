<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locales = config('flowdesk.locales', ['fr', 'en', 'es', 'ar']);

        $validated = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $locales)],
        ]);

        session([
            'locale' => $validated['locale'],
            'locale_user_chosen' => true,
            'locale_auto_pinned' => true,
        ]);

        if ($request->user()) {
            $request->user()->forceFill(['locale' => $validated['locale']])->save();
        }

        app()->setLocale($validated['locale']);

        return back();
    }
}
