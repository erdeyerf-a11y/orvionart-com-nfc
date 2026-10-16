<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        // Preserve stricter route-specific policies (e.g. private demo media and
        // canonical redirects) instead of overwriting them with the global default.
        if (!$response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Keep route-specific policies intact. The admin media preview uses a stricter
        // "default-src 'none'; sandbox" policy; replacing it would weaken isolation.
        if (!$response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; style-src 'self' 'unsafe-inline'; upgrade-insecure-requests");
        }
        if (!app()->environment('production')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        }
        if ($request->is('admin/*') || $request->is('admin')) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }
        return $response;
    }
}
