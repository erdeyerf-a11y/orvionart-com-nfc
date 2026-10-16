<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\UsageRight;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageRightRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Test Admin',
            'email' => 'usage-admin@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_open_usage_right_registry(): void
    {
        $this->get('/admin/usage-rights')->assertRedirect('/admin/login');
    }

    public function test_regular_user_cannot_open_usage_right_registry(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'usage-user@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/usage-rights')->assertForbidden();
    }

    public function test_super_admin_can_create_manual_registry_entry_and_audit_is_written(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/usage-rights', [
            'right_type' => 'standard_free_30_days',
            'label' => 'Pilot kullanım hakkı kaydı',
            'subject_type' => 'unresolved',
            'subject_reference' => 'pilot-reference-01',
            'grant_source' => 'manual_admin',
            'starts_at' => '2026-10-15T10:00',
            'ends_at' => '2026-11-14T10:00',
            'notes' => 'Kayıt defteri testi; erişim otomatik bağlanmamalı.',
        ])->assertRedirect('/admin/usage-rights');

        $this->assertDatabaseHas('usage_rights', [
            'label' => 'Pilot kullanım hakkı kaydı',
            'subject_type' => 'unresolved',
            'subject_reference' => 'pilot-reference-01',
            'status' => 'active',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'usage_right.created',
        ]);
        $this->actingAs($admin)->get('/admin/usage-rights')->assertOk()
            ->assertSee('Bu sürüm kayıt defteri ve denetim altyapısıdır')
            ->assertSee('Pilot kullanım hakkı kaydı');
    }

    public function test_regular_user_cannot_export_usage_right_registry(): void
    {
        $user = User::create([
            'name' => 'Regular Export User',
            'email' => 'usage-export-user@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/usage-rights/export')->assertForbidden();
    }

    public function test_super_admin_can_export_filtered_registry_and_formula_cells_are_neutralized(): void
    {
        $admin = $this->admin();
        UsageRight::create([
            'right_type' => 'custom',
            'label' => '=HYPERLINK("https://bad.example","open")',
            'subject_type' => 'unresolved',
            'subject_reference' => '@unsafe-reference',
            'grant_source' => 'manual_admin',
            'starts_at' => now(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get('/admin/usage-rights/export?right_type=custom');
        $response->assertOk()->assertDownload();
        $content = $response->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'@unsafe-reference", $content);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'usage_right.export_prepared',
        ]);
    }

    public function test_end_time_cannot_be_before_start_time(): void
    {
        $this->actingAs($this->admin())->from('/admin/usage-rights')->post('/admin/usage-rights', [
            'right_type' => 'custom',
            'label' => 'Geçersiz tarih',
            'subject_type' => 'unresolved',
            'grant_source' => 'manual_admin',
            'starts_at' => '2026-11-15T10:00',
            'ends_at' => '2026-10-15T10:00',
        ])->assertSessionHasErrors('ends_at');

        $this->assertDatabaseCount('usage_rights', 0);
    }

    public function test_revoke_requires_reason_and_records_actor_and_audit(): void
    {
        $admin = $this->admin();
        $right = UsageRight::create([
            'right_type' => 'custom',
            'label' => 'İptal edilecek kayıt',
            'subject_type' => 'unresolved',
            'grant_source' => 'manual_admin',
            'starts_at' => now()->subHour(),
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch("/admin/usage-rights/{$right->id}/revoke", [
            'revocation_reason' => 'Pilot kaydı kapatıldı',
        ])->assertRedirect('/admin/usage-rights');

        $this->assertDatabaseHas('usage_rights', [
            'id' => $right->id,
            'status' => 'revoked',
            'revoked_by' => $admin->id,
            'revocation_reason' => 'Pilot kaydı kapatıldı',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'usage_right.revoked',
            'subject_id' => (string) $right->id,
        ]);
    }

    public function test_expired_status_is_display_only_and_does_not_mutate_registry(): void
    {
        $right = UsageRight::create([
            'right_type' => 'standard_free_30_days',
            'label' => 'Bitiş tarihi geçmiş kayıt',
            'subject_type' => 'unresolved',
            'grant_source' => 'manual_admin',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
            'status' => 'active',
        ]);

        $this->assertSame('Süresi doldu', $right->fresh()->displayStatus());
        $this->assertSame('active', $right->fresh()->status);
    }
}
