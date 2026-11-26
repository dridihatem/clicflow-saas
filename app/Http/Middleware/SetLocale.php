<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\LocaleDetectionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function __construct(private LocaleDetectionService $locales) {}

    public function handle(Request $request, Closure $next): Response
    {
        $supported = $this->locales->supportedLocales();

        // Session wins (manual switcher or previous IP auto-detect).
        if (session()->has('locale') && in_array(session('locale'), $supported, true)) {
            app()->setLocale(session('locale'));

            return $next($request);
        }

        if ($request->user()?->locale && in_array($request->user()->locale, $supported, true)) {
            app()->setLocale($request->user()->locale);

            return $next($request);
        }

        if ($request->user('admin')?->locale && in_array($request->user('admin')->locale, $supported, true)) {
            app()->setLocale($request->user('admin')->locale);

            return $next($request);
        }

        /** @var Company|null $company */
        $company = app()->bound('currentCompany') ? app()->make('currentCompany') : null;
        if ($company instanceof Company && $company->default_locale && in_array($company->default_locale, $supported, true)) {
            app()->setLocale($company->default_locale);

            return $next($request);
        }

        // First visit: platform default (French). Visitors can still switch language manually.
        $default = flowdesk_default_locale();
        if (in_array($default, $supported, true)) {
            app()->setLocale($default);
        }

        return $next($request);
    }
}
