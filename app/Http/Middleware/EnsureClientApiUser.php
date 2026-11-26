<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientApiUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('client') || ! $user->clientProfile) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->company instanceof Company) {
            app()->instance('currentCompany', $user->company);
        }

        return $next($request);
    }
}
