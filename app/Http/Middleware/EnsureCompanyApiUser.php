<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || (
                ! $user->hasAnyRole(['company_admin', 'team_member'], 'web')
                && ! $user->roles()->whereIn('name', ['company_admin', 'team_member'])->exists()
            )
            || ! ($user->company instanceof Company)
        ) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        app()->instance('currentCompany', $user->company);

        return $next($request);
    }
}
