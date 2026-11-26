<?php

namespace App\Http\Middleware;

use App\Services\PortalVisibilityService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks direct access to client-portal sections the company has hidden.
 * Only applies to pure client users; staff are never affected.
 */
class EnforcePortalSectionVisibility
{
    public function __construct(private PortalVisibilityService $visibility) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $this->visibility->isPureClient($user)) {
            return $next($request);
        }

        $section = $this->visibility->sectionForRoute($request->route()?->getName());
        if ($section === null || $this->visibility->visible($user, $section)) {
            return $next($request);
        }

        $message = __('portal_vis_blocked');
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->route('portal.dashboard')->with('error', $message);
    }
}
