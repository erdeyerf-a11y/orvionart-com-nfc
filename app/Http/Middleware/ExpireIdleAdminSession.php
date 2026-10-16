<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Enforces a server-side idle timeout for authenticated admin routes. */
class ExpireIdleAdminSession
{
    private const ACTIVITY_KEY = 'orvionart_admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || !$request->user()->isSuperAdmin()) {
            return $next($request);
        }

        $timeoutMinutes = max(5, min(240, (int) config('security.admin_idle_timeout_minutes', 30)));
        $lastActivity = $request->session()->get(self::ACTIVITY_KEY);
        $now = now()->timestamp;

        if (is_numeric($lastActivity) && ($now - (int) $lastActivity) >= ($timeoutMinutes * 60)) {
            try {
                AuditLog::create([
                    'actor_id' => $user->id,
                    'action' => 'auth.session_expired_idle',
                    'subject_type' => 'user',
                    'subject_id' => $user->id,
                    'ip_address' => mb_substr((string) $request->ip(), 0, 45),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                    'metadata' => ['timeout_minutes' => $timeoutMinutes],
                    'created_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => 'Güvenlik nedeniyle yönetici oturumunuz hareketsizlik nedeniyle sona erdi. Lütfen yeniden giriş yapın.',
            ]);
        }

        $request->session()->put(self::ACTIVITY_KEY, $now);

        return $next($request);
    }
}
