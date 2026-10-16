<?php

namespace Tests\Feature;

use App\Models\DemoEvent;
use App\Models\DemoSession;
use App\Models\DemoSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DemoRetentionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_eligible_data_without_deleting_it(): void
    {
        DemoSetting::current()->update(['retention_days' => 30]);
        $oldSession = DemoSession::create([
            'device_token_hash' => hash('sha256', 'old-device-token'),
            'started_at' => Carbon::now()->subDays(70),
            'ends_at' => Carbon::now()->subDays(60),
            'last_seen_at' => Carbon::now()->subDays(60),
        ]);
        $oldEvent = DemoEvent::create([
            'demo_session_id' => $oldSession->id,
            'event_type' => 'content_view',
            'created_at' => Carbon::now()->subDays(60),
        ]);

        $this->artisan('orvionart:prune-demo-data', ['--dry-run' => true])->assertExitCode(0);

        $this->assertDatabaseHas('demo_sessions', ['id' => $oldSession->id]);
        $this->assertDatabaseHas('demo_events', ['id' => $oldEvent->id]);
    }

    public function test_prune_deletes_old_events_and_old_ended_sessions_but_keeps_recent_data(): void
    {
        DemoSetting::current()->update(['retention_days' => 30]);
        $oldSession = DemoSession::create([
            'device_token_hash' => hash('sha256', 'old-device-token'),
            'started_at' => Carbon::now()->subDays(70),
            'ends_at' => Carbon::now()->subDays(60),
            'last_seen_at' => Carbon::now()->subDays(60),
        ]);
        $recentSession = DemoSession::create([
            'device_token_hash' => hash('sha256', 'recent-device-token'),
            'started_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->addDays(5),
            'last_seen_at' => Carbon::now(),
        ]);
        $oldEvent = DemoEvent::create([
            'demo_session_id' => $oldSession->id,
            'event_type' => 'content_view',
            'created_at' => Carbon::now()->subDays(60),
        ]);
        $recentEvent = DemoEvent::create([
            'demo_session_id' => $recentSession->id,
            'event_type' => 'content_view',
            'created_at' => Carbon::now(),
        ]);

        $this->artisan('orvionart:prune-demo-data')->assertExitCode(0);

        $this->assertDatabaseMissing('demo_sessions', ['id' => $oldSession->id]);
        $this->assertDatabaseHas('demo_sessions', ['id' => $recentSession->id]);
        $this->assertDatabaseMissing('demo_events', ['id' => $oldEvent->id]);
        $this->assertDatabaseHas('demo_events', ['id' => $recentEvent->id, 'demo_session_id' => null]);
    }

    public function test_recently_expired_session_is_retained_until_its_retention_period_passes(): void
    {
        DemoSetting::current()->update(['retention_days' => 30]);
        $session = DemoSession::create([
            'device_token_hash' => hash('sha256', 'recently-expired-token'),
            'started_at' => Carbon::now()->subDays(40),
            'ends_at' => Carbon::now()->subDays(35),
            'last_seen_at' => Carbon::now()->subDays(35),
            'expired_at' => Carbon::now()->subDays(5),
        ]);

        $this->artisan('orvionart:prune-demo-data')->assertExitCode(0);

        $this->assertDatabaseHas('demo_sessions', ['id' => $session->id]);
    }
}
