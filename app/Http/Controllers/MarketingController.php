<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingController extends Controller
{
    public function home(): View
    {
        return view('marketing.home');
    }

    public function modules(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user?->company && $user->can('workspace.manage_settings')) {
            return redirect()->route('settings.modules');
        }

        return redirect()->route('login');
    }

    public function sitemap(): \Illuminate\Http\Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        $xml .= '  <url><loc>'.e(route('login')).'</loc><changefreq>weekly</changefreq></url>'."\n";
        $xml .= '  <url><loc>'.e(route('register')).'</loc><changefreq>weekly</changefreq></url>'."\n";
        $xml .= '</urlset>';

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
