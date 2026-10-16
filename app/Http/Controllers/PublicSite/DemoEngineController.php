<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\DemoContent;
use App\Models\DemoDailyAssignment;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\View\View;

class DemoEngineController extends Controller
{
    private const DEVICE_COOKIE = 'orvionart_demo_device';

    public function show(Request $request)
    {
        $settings = DemoSetting::current();
        if (!$settings->is_active) {
            return response()->view('public.demo-paused', ['settings' => $settings], 503, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
        }

        $activeContents = DemoContent::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get();
        if ($activeContents->isEmpty()) {
            return response()->view('public.demo-paused', ['settings' => $settings, 'empty' => true], 503, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
        }

        $session = $this->sessionFromRequest($request);
        if (!$session) {
            return $this->startNewJourney($request, $settings);
        }
        if ($session->isExpired()) {
            if (!$session->expired_at) {
                $session->forceFill(['expired_at' => now()])->save();
            }
            return response()->view('public.demo-expired', ['settings' => $settings, 'endsAt' => $session->ends_at], 200, [
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'X-Robots-Tag' => 'noindex, nofollow, noarchive',
                'Referrer-Policy' => 'no-referrer',
            ]);
        }

        $today = now()->toDateString();
        // Serialize daily assignment decisions per device session. Locking the
        // selected content rows coordinates assignment creation with admin deletion.
        $assignment = DB::transaction(function () use ($session, $today): ?DemoDailyAssignment {
            $lockedSession = DemoSession::query()->whereKey($session->id)->lockForUpdate()->first();
            if (!$lockedSession || $lockedSession->isExpired()) {
                return null;
            }
            $lockedSession->forceFill(['last_seen_at' => now()])->save();

            $assignment = DemoDailyAssignment::query()
                ->where('demo_session_id', $lockedSession->id)
                ->whereDate('assigned_for', $today)
                ->lockForUpdate()
                ->first();

            if ($assignment) {
                $assignedContent = DemoContent::query()->whereKey($assignment->demo_content_id)->lockForUpdate()->first();
                if ($assignedContent && $assignedContent->is_active) {
                    $assignment->setRelation('content', $assignedContent);
                    DemoEvent::create([
                        'demo_session_id' => $lockedSession->id,
                        'event_type' => 'content_view',
                        'demo_content_id' => $assignedContent->id,
                        'created_at' => now(),
                    ]);
                    return $assignment;
                }
            }

            $activeContents = DemoContent::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($activeContents->isEmpty()) {
                return null;
            }

            $elapsedDays = max(0, $lockedSession->started_at->startOfDay()->diffInDays(now()->startOfDay(), false));
            $content = $activeContents[$elapsedDays % $activeContents->count()];
            if (!$assignment) {
                $assignment = new DemoDailyAssignment([
                    'demo_session_id' => $lockedSession->id,
                    'assigned_for' => $today,
                ]);
            }
            $assignment->demo_content_id = $content->id;
            $assignment->save();
            $assignment->setRelation('content', $content);

            DemoEvent::create([
                'demo_session_id' => $lockedSession->id,
                'event_type' => 'content_view',
                'demo_content_id' => $content->id,
                'created_at' => now(),
            ]);

            return $assignment;
        });

        if (!$assignment || !$assignment->content) {
            return response()->view('public.demo-paused', ['settings' => $settings, 'empty' => true], 503, ['Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
        }

        return response()->view('public.demo', ['settings' => $settings, 'session' => $session, 'content' => $assignment->content, 'dayNumber' => min($settings->journey_days, max(1, $session->started_at->startOfDay()->diffInDays(now()->startOfDay()) + 1))], [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function restart(Request $request)
    {
        $settings = DemoSetting::current();
        abort_unless($settings->is_active && DemoContent::query()->where('is_active', true)->exists(), 503);

        $rawToken = (string) $request->cookie(self::DEVICE_COOKIE, '');
        $token = Str::random(64);
        DB::transaction(function () use ($rawToken, $token, $settings): void {
            // A restart replaces the device's current journey instead of leaving an
            // additional live session behind for the same browser cookie.
            if (preg_match('/^[A-Za-z0-9]{64}$/', $rawToken)) {
                $previous = DemoSession::query()
                    ->where('device_token_hash', hash('sha256', $rawToken))
                    ->lockForUpdate()
                    ->first();
                if ($previous) {
                    if (!$previous->expired_at) {
                        $previous->forceFill(['expired_at' => now(), 'last_seen_at' => now()])->save();
                    }
                    DemoEvent::create([
                        'demo_session_id' => $previous->id,
                        'event_type' => 'journey_restarted',
                        'demo_content_id' => null,
                        'created_at' => now(),
                    ]);
                }
            }

            $session = DemoSession::create([
                'device_token_hash' => hash('sha256', $token),
                'started_at' => now(),
                'ends_at' => now()->addDays($settings->journey_days),
                'last_seen_at' => now(),
            ]);
            DemoEvent::create([
                'demo_session_id' => $session->id,
                'event_type' => 'journey_started',
                'demo_content_id' => null,
                'created_at' => now(),
            ]);
        });

        $cookie = Cookie::create(self::DEVICE_COOKIE, $token, now()->addDays(365), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
        return redirect()->route('demo.show')->withCookie($cookie)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function media(Request $request, DemoContent $content): StreamedResponse
    {
        abort_unless(DemoSetting::current()->is_active && $content->is_active && $content->media_disk && $content->media_path, 404);

        // Demo media is not a public asset URL: the visitor must have a valid,
        // unexpired device journey and this content must be today's assignment.
        $session = $this->sessionFromRequest($request);
        abort_unless($session && !$session->isExpired(), 404);
        $assignedToday = DemoDailyAssignment::query()
            ->where('demo_session_id', $session->id)
            ->whereDate('assigned_for', now()->toDateString())
            ->where('demo_content_id', $content->id)
            ->exists();
        abort_unless($assignedToday, 404);

        // Database rows can outlive old upload rules or be manually changed.
        // Public delivery therefore re-validates the disk, managed path, MIME,
        // resolved filesystem path, and regular-file status before opening bytes.
        abort_unless($content->media_disk === 'local' && $this->isSafeDemoMediaPath((string) $content->media_path), 404);
        abort_unless(in_array($content->media_mime_type, [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'video/mp4', 'video/webm', 'video/ogg',
        ], true), 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($content->media_path), 404);
        $rootPath = realpath($disk->path(''));
        $path = realpath($disk->path($content->media_path));
        abort_unless(
            $rootPath !== false
            && $path !== false
            && str_starts_with($path, rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            && is_file($path),
            404
        );

        $stream = @fopen($path, 'rb');
        abort_if($stream === false, 404);
        $headers = [
            'Content-Type' => $content->media_mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, no-cache, must-revalidate',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Referrer-Policy' => 'no-referrer',
            'Content-Disposition' => 'inline; filename="demo-media-' . $content->id . '"',
        ];
        $actualSize = filesize($path);
        if ($actualSize !== false) {
            $headers['Content-Length'] = (string) $actualSize;
        }

        return response()->stream(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                if (is_resource($stream)) fclose($stream);
            }
        }, 200, $headers);
    }

    private function isSafeDemoMediaPath(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\') || str_starts_with($path, '/')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:/', $path) === 1) {
            return false;
        }

        $segments = explode('/', $path);
        if (in_array('..', $segments, true) || in_array('.', $segments, true) || in_array('', $segments, true)) {
            return false;
        }

        return str_starts_with($path, 'orvionart-demo/');
    }

    private function sessionFromRequest(Request $request): ?DemoSession
    {
        $rawToken = (string) $request->cookie(self::DEVICE_COOKIE, '');
        if (!preg_match('/^[A-Za-z0-9]{64}$/', $rawToken)) {
            return null;
        }

        return DemoSession::query()
            ->where('device_token_hash', hash('sha256', $rawToken))
            ->first();
    }

    private function startNewJourney(Request $request, DemoSetting $settings)
    {
        $token = Str::random(64);
        DB::transaction(function () use ($token, $settings): void {
            $session = DemoSession::create([
                'device_token_hash' => hash('sha256', $token),
                'started_at' => now(),
                'ends_at' => now()->addDays($settings->journey_days),
                'last_seen_at' => now(),
            ]);
            DemoEvent::create([
                'demo_session_id' => $session->id,
                'event_type' => 'journey_started',
                'demo_content_id' => null,
                'created_at' => now(),
            ]);
        });
        $cookie = Cookie::create(self::DEVICE_COOKIE, $token, now()->addDays(365), '/', null, $request->isSecure(), true, false, Cookie::SAMESITE_LAX);
        return redirect()->route('demo.show')->withCookie($cookie)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
