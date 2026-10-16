<?php

namespace App\Http\Middleware;

use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep operator access, liveness checks and robots policy available while the public
        // experience is paused. All other public destinations (QR/NFC, media, demo, pages,
        // experience player and sitemap) use the same maintenance response.
        if ($request->is('admin', 'admin/*', 'health', 'up', 'robots.txt')) {
            return $next($request);
        }

        $settings = SiteSetting::getValue('general', []);
        if (!($settings['maintenance_mode'] ?? false)) {
            return $next($request);
        }

        $message = trim((string) ($settings['maintenance_message'] ?? 'Sitemiz kısa süreli bakımda. Lütfen daha sonra tekrar deneyin.'));
        if ($message === '') {
            $message = 'Sitemiz kısa süreli bakımda. Lütfen daha sonra tekrar deneyin.';
        }

        return response()->view('public.maintenance', ['message' => $message], 503, [
            'Retry-After' => '3600',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]);
    }
}
