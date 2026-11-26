<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmbedAssetController extends Controller
{
    public function widget(): BinaryFileResponse|Response
    {
        return $this->serveViteEntry('resources/js/widget.js');
    }

    public function marketingTracker(): BinaryFileResponse|Response
    {
        return $this->serveViteEntry('resources/js/marketing-tracker.js');
    }

    private function serveViteEntry(string $entry): BinaryFileResponse|Response
    {
        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            abort(404, 'Embed assets are not built yet.');
        }

        $manifest = json_decode((string) File::get($manifestPath), true);
        if (! is_array($manifest) || ! isset($manifest[$entry]['file'])) {
            abort(404, 'Embed asset not found in build manifest.');
        }

        $relative = (string) $manifest[$entry]['file'];
        $absolute = public_path('build/'.$relative);
        if (! is_file($absolute)) {
            abort(404, 'Embed asset file missing.');
        }

        return response()->file($absolute, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'public, max-age=120, must-revalidate',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
