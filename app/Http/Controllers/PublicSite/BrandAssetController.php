<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class BrandAssetController extends Controller
{
    public function show(string $type): Response|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $assetTypes = ['logo' => ['key' => 'logo_path', 'filename' => 'logo_path'], 'favicon' => ['key' => 'favicon_path', 'filename' => 'favicon_path'], 'social-image' => ['key' => 'social_image_path', 'filename' => 'social_image_path']];
        abort_unless(isset($assetTypes[$type]), 404);
        $key = $assetTypes[$type]['key'];
        $filenamePrefix = $assetTypes[$type]['filename'];
        $settings = SiteSetting::getValue('general');
        $path = $settings[$key] ?? null;

        // The database value is treated as untrusted. Only generated filenames inside
        // the dedicated private branding directory may be delivered publicly.
        abort_unless(is_string($path)
            && preg_match('~^orvionart-brand/'.$filenamePrefix.'-[A-Za-z0-9]{40}\.(?:png|jpg|webp)$~', $path) === 1
            && Storage::disk('local')->exists($path), 404);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {'png' => 'image/png', 'jpg' => 'image/jpeg', 'webp' => 'image/webp', default => 'application/octet-stream'};
        return response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'no-store, private',
            'Content-Disposition' => 'inline',
        ]);
    }
}
