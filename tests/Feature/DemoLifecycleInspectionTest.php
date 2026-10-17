<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoLifecycleInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_lifecycle_inspection_passes_for_consistent_records_and_is_read_only(): void
    {
        $sessionId = DB::table('demo_sessions')->insertGetId([
            'device_token_hash' => hash('sha256', 'test-device-clean'),
            'started_at' => Carbon::parse('2026-10-10 10:00:00'),
            'ends_at' => Carbon::parse('2026-10-17 10:00:00'),
            'last_seen_at' => Carbon::parse('2026-10-10 10:05:00'),
            'expired_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contentId = DB::table('demo_contents')->insertGetId([
            'title' => 'Test içerik',
            'summary' => null,
            'body' => null,
            'sort_order' => 1,
            'is_active' => true,
            'media_disk' => null,
            'media_path' => null,
            'media_mime_type' => null,
            'media_size' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('demo_daily_assignments')->insert([
            'demo_session_id' => $sessionId,
            'demo_content_id' => $contentId,
            'assigned_for' => '2026-10-12',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('demo_events')->insert([
            'demo_session_id' => $sessionId,
            'event_type' => 'content_viewed',
            'demo_content_id' => $contentId,
            'created_at' => Carbon::parse('2026-10-12 12:00:00'),
        ]);

        $this->artisan('orvionart:inspect-demo-lifecycle --json')
            ->expectsOutputToContain('"read_only": true')
            ->assertExitCode(0);

        $this->assertDatabaseCount('demo_sessions', 1);
        $this->assertDatabaseCount('demo_daily_assignments', 1);
        $this->assertDatabaseCount('demo_events', 1);
    }

    public function test_demo_lifecycle_inspection_reports_invalid_session_timestamps_without_mutating_data(): void
    {
        $sessionId = DB::table('demo_sessions')->insertGetId([
            'device_token_hash' => hash('sha256', 'test-device-invalid'),
            'started_at' => Carbon::parse('2026-10-15 12:00:00'),
            'ends_at' => Carbon::parse('2026-10-14 12:00:00'),
            'last_seen_at' => Carbon::parse('2026-10-15 11:00:00'),
            'expired_at' => Carbon::parse('2026-10-15 10:00:00'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('orvionart:inspect-demo-lifecycle --json')
            ->expectsOutputToContain('session_end_before_start')
            ->assertExitCode(1);

        $this->assertDatabaseHas('demo_sessions', [
            'id' => $sessionId,
            'ends_at' => '2026-10-14 12:00:00',
        ]);
        $this->assertDatabaseCount('demo_sessions', 1);
    }

    public function test_demo_lifecycle_inspection_reports_assignments_outside_session_window(): void
    {
        $sessionId = DB::table('demo_sessions')->insertGetId([
            'device_token_hash' => hash('sha256', 'test-device-assignment'),
            'started_at' => Carbon::parse('2026-10-10 10:00:00'),
            'ends_at' => Carbon::parse('2026-10-12 10:00:00'),
            'last_seen_at' => null,
            'expired_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contentId = DB::table('demo_contents')->insertGetId([
            'title' => 'Test içerik',
            'summary' => null,
            'body' => null,
            'sort_order' => 1,
            'is_active' => true,
            'media_disk' => null,
            'media_path' => null,
            'media_mime_type' => null,
            'media_size' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('demo_daily_assignments')->insert([
            'demo_session_id' => $sessionId,
            'demo_content_id' => $contentId,
            'assigned_for' => '2026-10-13',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('orvionart:inspect-demo-lifecycle --json')
            ->expectsOutputToContain('assignment_outside_session_window')
            ->assertExitCode(1);

        $this->assertDatabaseCount('demo_daily_assignments', 1);
    }
}
