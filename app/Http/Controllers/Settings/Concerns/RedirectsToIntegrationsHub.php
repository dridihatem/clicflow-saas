<?php

namespace App\Http\Controllers\Settings\Concerns;

use Illuminate\Http\RedirectResponse;

trait RedirectsToIntegrationsHub
{
    protected function integrationsHub(string $key, ?string $status = null): RedirectResponse
    {
        $redirect = redirect()->route('settings.integrations', ['configure' => $key]);

        return $status ? $redirect->with('status', $status) : $redirect;
    }
}
