<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCompanyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = app()->bound('currentCompany') ? app('currentCompany') : null;

        $token = $request->bearerToken();

        // Central / unknown host: resolve workspace from the embed API token.
        if (! $company instanceof Company && is_string($token) && $token !== '') {
            $company = Company::findByApiToken($token);
            if ($company instanceof Company) {
                app()->instance('currentCompany', $company);
            }
        }

        if (! $company instanceof Company) {
            abort(404, __('Tenant not found.'));
        }

        if ($token === null || $token === '' || ! $company->apiTokenMatches($token)) {
            abort(401, __('Invalid or missing API token.'));
        }

        return $next($request);
    }
}
