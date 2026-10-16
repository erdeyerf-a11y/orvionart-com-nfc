<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Experience;
use App\Models\QrLink;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class QrRedirectController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse|Response
    {
        $result = DB::transaction(function () use ($token): array {
            // Always lock parent first, then link: publication and scans use the same order.
            $experienceId = QrLink::query()->where('token', $token)->value('experience_id');
            abort_if($experienceId === null, 404);

            $experience = Experience::query()
                ->whereKey($experienceId)
                ->lockForUpdate()
                ->first();
            abort_if(!$experience, 404);

            $link = QrLink::query()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();
            abort_if(!$link || (int) $link->experience_id !== (int) $experience->id, 404);

            // A known QR should give a helpful visitor page when deliberately disabled.
            // Do not reveal experience title/content and never count an unsuccessful visit.
            if (!$link->is_active) {
                return ['state' => 'unavailable', 'url' => null];
            }

            if ($experience->ends_at !== null && $experience->ends_at->lte(now())) {
                return ['state' => 'expired', 'url' => null];
            }

            if (!$experience->isPubliclyAvailable()) {
                return ['state' => 'unavailable', 'url' => null];
            }

            // Legacy/corrupt rows may have a public status but no usable public ID.
            $url = $experience->publicUrl();
            abort_if(!is_string($url) || trim($url) === '', 404);

            $link->scan_count = max(0, (int) $link->scan_count) + 1;
            $link->last_scanned_at = now();
            $link->save();

            return ['state' => 'redirect', 'url' => $url];
        });

        if ($result['state'] !== 'redirect') {
            $status = $result['state'] === 'expired' ? 410 : 404;

            return response()->view('public.qr-unavailable', [
                'expired' => $result['state'] === 'expired',
            ], $status)
                ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
                ->header('X-Content-Type-Options', 'nosniff');
        }

        return redirect()->to($result['url'], 302)
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
