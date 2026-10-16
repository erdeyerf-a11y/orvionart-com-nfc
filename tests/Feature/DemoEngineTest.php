<?php

namespace Tests\Feature;

use App\Models\DemoContent;
use App\Models\DemoDailyAssignment;
use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_is_paused_by_default(): void
    {
        $response = $this->get(route('demo.show'));
        $response->assertStatus(503)->assertSee('Demo şu anda kullanılamıyor');
    }

    public function test_active_demo_starts_a_device_journey_and_sets_http_only_cookie(): void
    {
        DemoSetting::current()->update(['is_active' => true]);
        DemoContent::create(['title' => 'Örnek demo içeriği', 'is_active' => true, 'sort_order' => 0]);
        $response = $this->get(route('demo.show'));
        $response->assertRedirect(route('demo.show'));
        $response->assertCookie('orvionart_demo_device');
    }


    public function test_demo_media_requires_a_valid_device_journey_and_today_assignment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-demo/test.jpg', 'demo-image-bytes');
        DemoSetting::current()->update(['is_active' => true]);
        $content = DemoContent::create([
            'title' => 'Atanmış demo içeriği',
            'is_active' => true,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/test.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => strlen('demo-image-bytes'),
        ]);

        $this->get(route('demo.media', ['content' => $content->id]))->assertNotFound();

        $rawToken = str_repeat('a', 64);
        $session = DemoSession::create([
            'device_token_hash' => hash('sha256', $rawToken),
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'last_seen_at' => now(),
        ]);
        DemoDailyAssignment::create([
            'demo_session_id' => $session->id,
            'demo_content_id' => $content->id,
            'assigned_for' => now()->toDateString(),
        ]);

        $response = $this->withCookie('orvionart_demo_device', $rawToken)
            ->get(route('demo.media', ['content' => $content->id]));
        $response->assertOk();
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $response->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_demo_media_rejects_legacy_disks_unsafe_paths_and_unapproved_mime_types(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-demo/unsafe-check.jpg', 'demo-image-bytes');
        Storage::fake('public');
        Storage::disk('public')->put('orvionart-demo/legacy.jpg', 'legacy-bytes');
        DemoSetting::current()->update(['is_active' => true]);

        $cases = [
            ['media_disk' => 'public', 'media_path' => 'orvionart-demo/legacy.jpg', 'media_mime_type' => 'image/jpeg'],
            ['media_disk' => 'local', 'media_path' => 'orvionart-demo/../.env', 'media_mime_type' => 'image/jpeg'],
            ['media_disk' => 'local', 'media_path' => 'orvionart-demo/unsafe-check.jpg', 'media_mime_type' => 'text/html'],
        ];

        foreach ($cases as $index => $attributes) {
            $content = DemoContent::create(array_merge([
                'title' => 'Güvenlik testi ' . $index,
                'is_active' => true,
                'media_size' => 16,
            ], $attributes));
            $rawToken = str_repeat(chr(97 + $index), 64);
            $session = DemoSession::create([
                'device_token_hash' => hash('sha256', $rawToken),
                'started_at' => now()->subDay(),
                'ends_at' => now()->addDays(5),
                'last_seen_at' => now(),
            ]);
            DemoDailyAssignment::create([
                'demo_session_id' => $session->id,
                'demo_content_id' => $content->id,
                'assigned_for' => now()->toDateString(),
            ]);

            $this->withCookie('orvionart_demo_device', $rawToken)
                ->get(route('demo.media', ['content' => $content->id]))
                ->assertNotFound();
        }
    }

    public function test_demo_media_cannot_be_fetched_for_content_not_assigned_to_the_device_today(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-demo/other.jpg', 'other-image-bytes');
        DemoSetting::current()->update(['is_active' => true]);
        $content = DemoContent::create([
            'title' => 'Başka demo içeriği',
            'is_active' => true,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/other.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => strlen('other-image-bytes'),
        ]);
        $rawToken = str_repeat('b', 64);
        DemoSession::create([
            'device_token_hash' => hash('sha256', $rawToken),
            'started_at' => now(),
            'ends_at' => now()->addDays(5),
            'last_seen_at' => now(),
        ]);

        $this->withCookie('orvionart_demo_device', $rawToken)
            ->get(route('demo.media', ['content' => $content->id]))
            ->assertNotFound();
    }

    public function test_restarting_demo_expires_the_previous_device_journey(): void
    {
        DemoSetting::current()->update(['is_active' => true, 'journey_days' => 7]);
        $rawToken = str_repeat('c', 64);
        $previous = DemoSession::create([
            'device_token_hash' => hash('sha256', $rawToken),
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(6),
            'last_seen_at' => now(),
        ]);

        $response = $this->withCookie('orvionart_demo_device', $rawToken)->post(route('demo.restart'));
        $response->assertRedirect(route('demo.show'));
        $this->assertDatabaseHas('demo_sessions', ['id' => $previous->id]);
        $this->assertNotNull($previous->fresh()->expired_at);
        $this->assertDatabaseHas('demo_events', [
            'demo_session_id' => $previous->id,
            'event_type' => 'journey_restarted',
        ]);
        $this->assertDatabaseCount('demo_sessions', 2);
    }

    public function test_demo_setting_is_independent_of_payment_configuration(): void
    {
        DemoSetting::current()->update(['is_active' => true]);
        $this->assertDatabaseHas('demo_settings', ['is_active' => 1]);
        $this->assertDatabaseCount('demo_sessions', 0);
    }


    public function test_demo_settings_change_records_audit_for_the_committed_state(): void
    {
        $admin = $this->superAdmin();
        DemoSetting::current();

        $this->actingAs($admin)->put(route('admin.demo.settings.update'), [
            'is_active' => '1',
            'journey_days' => 14,
            'retention_days' => 90,
            'title' => 'ORVIONART Demo Test',
            'paused_message' => 'Demo kısa süreliğine kapalı.',
        ])->assertSessionHasNoErrors();

        $settings = DemoSetting::current();
        $this->assertTrue($settings->is_active);
        $this->assertSame(14, $settings->journey_days);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'demo.engine.activated',
            'subject_type' => DemoSetting::class,
            'subject_id' => (string) $settings->id,
        ]);
    }

    public function test_demo_content_toggle_uses_locked_state_and_records_matching_audit_event(): void
    {
        $admin = $this->superAdmin();
        $content = DemoContent::create([
            'created_by' => $admin->id,
            'title' => 'Durum denetimi içeriği',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.demo.contents.toggle', $content))
            ->assertSessionHasNoErrors();

        $this->assertFalse($content->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'demo.content.deactivated',
            'subject_type' => DemoContent::class,
            'subject_id' => (string) $content->id,
        ]);
    }

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Demo Test Admin',
            'email' => 'demo-admin@example.test',
            'password' => 'test-password-long-enough',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_demo_content_with_assignment_history_cannot_be_deleted_and_media_is_preserved(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-demo/test.jpg', 'demo-bytes');
        $admin = $this->superAdmin();
        $content = DemoContent::create([
            'created_by' => $admin->id,
            'title' => 'Atama geçmişi olan içerik',
            'is_active' => false,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/test.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 10,
        ]);
        $session = DemoSession::create([
            'device_token_hash' => hash('sha256', str_repeat('d', 64)),
            'started_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'last_seen_at' => now(),
        ]);
        DemoDailyAssignment::create([
            'demo_session_id' => $session->id,
            'demo_content_id' => $content->id,
            'assigned_for' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.demo.contents.destroy', $content))
            ->assertStatus(409);

        $this->assertDatabaseHas('demo_contents', ['id' => $content->id]);
        Storage::disk('local')->assertExists('orvionart-demo/test.jpg');
    }

    public function test_demo_content_deletion_removes_only_safe_private_demo_media_and_audits(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-demo/remove.jpg', 'demo-bytes');
        $admin = $this->superAdmin();
        $content = DemoContent::create([
            'created_by' => $admin->id,
            'title' => 'Silinebilir demo içeriği',
            'is_active' => false,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/remove.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 10,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.demo.contents.destroy', $content))
            ->assertRedirect();

        $this->assertDatabaseMissing('demo_contents', ['id' => $content->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'demo.content.deleted',
            'subject_id' => (string) $content->id,
        ]);
        Storage::disk('local')->assertMissing('orvionart-demo/remove.jpg');
    }

    public function test_demo_content_with_non_private_media_disk_is_not_deleted(): void
    {
        $admin = $this->superAdmin();
        $content = DemoContent::create([
            'created_by' => $admin->id,
            'title' => 'Eski public disk içeriği',
            'is_active' => false,
            'media_disk' => 'public',
            'media_path' => 'orvionart-demo/legacy.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 10,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.demo.contents.destroy', $content))
            ->assertStatus(409);

        $this->assertDatabaseHas('demo_contents', ['id' => $content->id]);
    }

    public function test_admin_demo_routes_require_super_admin(): void
    {
        $this->get(route('admin.demo.index'))->assertRedirect(route('admin.login'));
    }
}
