<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LocaleDetectionService
{
    /** @return list<string> */
    public function supportedLocales(): array
    {
        return config('flowdesk.locales', ['fr', 'en', 'es', 'ar']);
    }

    public function isSupported(string $locale): bool
    {
        return in_array($locale, $this->supportedLocales(), true);
    }

    public function localeForCountry(?string $country): ?string
    {
        if ($country === null || $country === '') {
            return null;
        }

        $locale = config('flowdesk.country_locale.'.strtoupper($country));

        return is_string($locale) && $this->isSupported($locale) ? $locale : null;
    }

    public function detectFromRequest(Request $request): ?string
    {
        $fromCountry = $this->localeForCountry($this->resolveCountryCode($request));
        if ($fromCountry !== null) {
            return $fromCountry;
        }

        return $this->fromAcceptLanguage($request);
    }

    public function resolveCountryCode(Request $request): ?string
    {
        foreach (['CF-IPCountry', 'X-AppEngine-Country', 'X-Country-Code', 'CloudFront-Viewer-Country'] as $header) {
            $value = strtoupper(trim((string) $request->header($header)));
            if ($value !== '' && $value !== 'XX' && $value !== 'T1' && strlen($value) === 2) {
                return $value;
            }
        }

        if (! config('flowdesk.ip_locale_lookup', true)) {
            return null;
        }

        $ip = (string) $request->ip();
        if ($ip === '' || $this->isPrivateIp($ip)) {
            return null;
        }

        return Cache::remember('flowdesk.geoip.country.'.$ip, now()->addDay(), function () use ($ip): ?string {
            return $this->lookupCountryFromIp($ip);
        });
    }

    public function fromAcceptLanguage(Request $request): ?string
    {
        $header = (string) $request->header('Accept-Language', '');
        if ($header === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $tag = strtolower(trim(explode(';', $part)[0]));
            if ($tag === '') {
                continue;
            }

            $base = explode('-', str_replace('_', '-', $tag))[0];
            if ($this->isSupported($base)) {
                return $base;
            }
        }

        return null;
    }

    public function defaultLocaleForRegistration(Request $request, ?string $country = null): string
    {
        // New workspaces default to French. Users can change language in settings.
        return function_exists('flowdesk_default_locale')
            ? flowdesk_default_locale()
            : (string) config('flowdesk.default_locale', 'fr');
    }

    private function lookupCountryFromIp(string $ip): ?string
    {
        try {
            $response = Http::timeout(2)
                ->get('http://ip-api.com/json/'.urlencode($ip), ['fields' => 'countryCode,status']);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    $code = strtoupper(trim((string) ($data['countryCode'] ?? '')));
                    if (strlen($code) === 2) {
                        return $code;
                    }
                }
            }
        } catch (\Throwable) {
            // try fallback
        }

        try {
            $response = Http::timeout(2)
                ->withHeaders(['User-Agent' => 'ClicFlowLocale/1.0'])
                ->get('https://ipapi.co/'.urlencode($ip).'/country_code/');

            if ($response->successful()) {
                $code = strtoupper(trim($response->body()));
                if (strlen($code) === 2 && ctype_alpha($code)) {
                    return $code;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function isPrivateIp(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'], true)) {
            return true;
        }

        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
