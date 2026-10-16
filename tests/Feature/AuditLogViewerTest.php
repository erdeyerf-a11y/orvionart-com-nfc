<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_open_audit_log_viewer(): void
    {
        $this->get('/admin/audit-logs')->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_open_audit_log_viewer(): void
    {
        $user = User::create([
            'name' => 'Regular Audit User',
            'email' => 'regular-audit@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_super_admin_can_filter_and_view_audit_events(): void
    {
        $admin = $this->admin();
        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'usage_right.created',
            'subject_type' => 'App\\Models\\UsageRight',
            'subject_id' => '41',
            'ip_address' => '192.0.2.10',
            'metadata' => ['right_type' => 'custom', 'api_token' => 'never-show-this'],
            'created_at' => now()->subDay(),
        ]);
        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'experience.updated',
            'subject_type' => 'App\\Models\\Experience',
            'subject_id' => '9',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/audit-logs?action=usage_right.created')
            ->assertOk()
            ->assertSee('usage_right.created')
            ->assertSee('192.0.2.10')
            ->assertSee('41')
            ->assertDontSee('App\\Models\\Experience')
            ->assertDontSee('never-show-this')
            ->assertSee('[GİZLENDİ]');
    }

    public function test_invalid_date_range_is_rejected(): void
    {
        $this->actingAs($this->admin())->get('/admin/audit-logs?from=2026-10-20&to=2026-10-01')
            ->assertSessionHasErrors('to');
    }
}
