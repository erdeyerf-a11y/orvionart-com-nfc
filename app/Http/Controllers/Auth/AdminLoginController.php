<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('admin.dashboard') : view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'password' => ['required', 'string', 'max:1024'],
        ]);

        $credentials['email'] = mb_strtolower(trim($credentials['email']));
        $key = 'admin-login:'.$credentials['email'].'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Çok fazla giriş denemesi yapıldı. Lütfen daha sonra tekrar deneyin.']);
        }

        if (!Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => true, 'role' => 'super_admin'], false)) {
            RateLimiter::hit($key, 60);
            $this->audit($request, 'auth.login_failed', null, ['email' => mb_strtolower($credentials['email'])]);
            throw ValidationException::withMessages(['email' => 'Giriş bilgileri doğrulanamadı.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit($request, 'auth.login_succeeded', $user->id);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = $request->user()?->id;
        $this->audit($request, 'auth.logout', $userId);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }

    private function audit(Request $request, string $action, ?int $actorId, array $metadata = []): void
    {
        try {
            AuditLog::create([
                'actor_id' => $actorId,
                'action' => $action,
                'subject_type' => 'user',
                'subject_id' => $actorId,
                'ip_address' => mb_substr((string) $request->ip(), 0, 45),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'metadata' => $metadata ?: null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
