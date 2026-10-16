<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExperiencePlayerController extends Controller
{
    public function show(Request $request, string $publicId, ?string $slug = null): View|RedirectResponse
    {
        $experience = Experience::query()
            ->publiclyAvailable()
            ->where('public_id', $publicId)
            ->with('mediaAssets')
            ->first();

        // Use the same non-revealing response for unknown, draft, archived and unavailable items.
        abort_if(!$experience, 404);

        // public_id is canonical; the readable slug can change without breaking a printed QR.
        if ($slug !== $experience->slug) {
            return redirect()->route('experiences.public', [
                'publicId' => $experience->public_id,
                'slug' => $experience->slug,
            ], 302)->header('Cache-Control', 'no-store')
                ->header('Referrer-Policy', 'no-referrer');
        }

        return response()->view('public.experience', [
            'experience' => $experience,
            'canonicalUrl' => route('experiences.public', ['publicId' => $experience->public_id, 'slug' => $experience->slug]),
        ])->header('Cache-Control', 'private, no-cache, no-store, must-revalidate')
          ->header('X-Robots-Tag', app()->environment('production') ? 'index, follow' : 'noindex, nofollow, noarchive');
    }
}
