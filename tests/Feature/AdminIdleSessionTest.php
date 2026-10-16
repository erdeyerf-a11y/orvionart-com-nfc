<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminIdleSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Idle Test Admin',
            'email' => 'idle-admin@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_session_activity_timestamp_is_refreshed_on_admin_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $firstActivity = session('orvionart_admin_last_activity');
        $this->assertSame(now()->timestamp, $firstActivity);

        Carbon::setTestNow(Carbon::parse('2026-10-15 10:05:00'));
        $this->get('/admin')->assertOk();
        $this->assertSame(now()->timestamp, session('orvionart_admin_last_activity'));
        $this->assertGreaterThan($firstActivity, session('orvionart_admin_last_activity'));
        Carbon::setTestNow();
    }

    public function test_idle_admin_session_is_invalidated_and_audited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 10:00:00'));
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession([
            'orvionart_admin_last_activity' => now()->subMinutes(31)->timestamp,
        ])->get('/admin')->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'auth.session_expired_idle',
            'subject_type' => 'user',
            'subject_id' => $admin->id,
        ]);
        Carbon::setTestNow();
    }

    public function test_timeout_configuration_is_clamped_to_safe_bounds(): void
    {
        config(['security.admin_idle_timeout_minutes' => 1]);
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession([
            'orvionart_admin_last_activity' => now()->subMinutes(5)->timestamp,
        ])->get('/admin')->assertRedirect('/admin/login');
    }
}
