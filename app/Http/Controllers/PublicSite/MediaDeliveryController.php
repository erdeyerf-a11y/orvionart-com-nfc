<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaDeliveryController extends Controller
{
    public function show(Request $request, MediaAsset $asset): BinaryFileResponse
    {
        $isExperienceMedia = $asset->experience_id !== null && $asset->experience && $asset->experience->isPubliclyAvailable();
        $isSiteMedia = $asset->site_page_id !== null && $asset->sitePage && $asset->sitePage->isPubliclyAvailable()
            && ((int) $asset->sitePage->published_social_image_asset_id === (int) $asset->id || collect($asset->sitePage->published_sections ?? [])->contains(function ($section) use ($asset) {
                if ((int)($section['image_asset_id'] ?? 0) === (int)$asset->id) return true;
                return collect($section['items'] ?? [])->contains(fn ($item) => (int)($item['image_asset_id'] ?? 0) === (int)$asset->id);
            }));
        abort_unless($isExperienceMedia || $isSiteMedia, 404);

        // Public delivery must never expose files from arbitrary disks. The current
        // upload pipeline stores managed media on the private local disk only.
        abort_unless($asset->kind !== 'video' || $asset->processing_status === 'ready', 404);

        $deliveryPath = $asset->kind === 'video' && $asset->processed_path
            ? (string) $asset->processed_path
            : (string) $asset->path;
        $deliveryMime = $asset->kind === 'video' && $asset->processed_path
            ? 'video/mp4'
            : (string) $asset->mime_type;

        abort_unless($asset->disk === 'local' && $this->isSafeManagedMediaPath($deliveryPath), 404);
        abort_unless(in_array($deliveryMime, [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ], true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($deliveryPath), 404);

        // Resolve symlinks before streaming. A managed-looking path must not be
        // allowed to point outside the private disk root.
        $rootPath = realpath($disk->path(''));
        $path = realpath($disk->path($deliveryPath));
        abort_unless(
            $rootPath !== false
            && $path !== false
            && str_starts_with($path, rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            && is_file($path),
            404
        );

        $filename = 'orvionart-media-' . $asset->id;

        return response()->file($path, [
            'Content-Type' => $deliveryMime,
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    /** Public thumbnail access follows the same published-content reference rules as video delivery. */
    public function thumbnail(MediaAsset $asset): BinaryFileResponse
    {
        $isExperienceMedia = $asset->experience_id !== null && $asset->experience && $asset->experience->isPubliclyAvailable();
        $isSiteMedia = $asset->site_page_id !== null && $asset->sitePage && $asset->sitePage->isPubliclyAvailable()
            && ((int) ($asset->sitePage->published_social_image_asset_id ?? 0) === (int) $asset->id || collect($asset->sitePage->published_sections ?? [])->contains(function ($section) use ($asset) {
                if ((int) ($section['image_asset_id'] ?? 0) === (int) $asset->id) return true;
                return collect($section['items'] ?? [])->contains(fn ($item) => (int) ($item['image_asset_id'] ?? 0) === (int) $asset->id);
            }));
        abort_unless($isExperienceMedia || $isSiteMedia, 404);
        abort_unless($asset->kind === 'video' && $asset->processing_status === 'ready'
            && is_string($asset->thumbnail_path) && $asset->thumbnail_path !== '', 404);
        abort_unless($asset->disk === 'local' && $this->isSafeManagedMediaPath($asset->thumbnail_path), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($asset->thumbnail_path), 404);
        $rootPath = realpath($disk->path(''));
        $path = realpath($disk->path($asset->thumbnail_path));
        abort_unless($rootPath !== false && $path !== false
            && str_starts_with($path, rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            && is_file($path), 404);
        abort_unless((new \finfo(FILEINFO_MIME_TYPE))->file($path) === 'image/jpeg', 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'inline; filename="orvionart-public-video-thumbnail-' . $asset->id . '.jpg"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ]);
    }

    private function isSafeManagedMediaPath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            return false;
        }

        return str_starts_with($path, 'orvionart-media/')
            || str_starts_with($path, 'orvionart-site-media/');
    }
}
