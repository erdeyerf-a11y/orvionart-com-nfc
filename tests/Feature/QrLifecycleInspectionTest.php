<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\QrLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class QrLifecycleInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_qr_lifecycle_is_reported_without_changing_records(): void
    {
        $experience = Experience::create([
            'title' => 'Yayındaki QR deneyimi',
            'slug' => 'yayindaki-qr-lifecycle',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Temiz QR', 'is_active' => true]);

        $this->artisan('orvionart:inspect-qr-lifecycle')
            ->expectsOutputToContain('salt okunur')
            ->assertExitCode(0);

        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'token' => $link->token, 'scan_count' => 0]);
    }

    public function test_active_qr_to_draft_experience_is_reported_without_exposing_token(): void
    {
        $experience = Experience::create(['title' => 'Taslak deneyim', 'slug' => 'taslak-qr-lifecycle', 'status' => 'draft']);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Etkin ama kapalı hedef', 'is_active' => true]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-qr-lifecycle', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('active_target_unavailable', $output);
        $this->assertStringNotContainsString($link->token, $output);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => true]);
    }

    public function test_malformed_token_is_reported_without_printing_token_value(): void
    {
        $experience = Experience::create(['title' => 'Taslak deneyim token', 'slug' => 'taslak-qr-token', 'status' => 'draft']);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Hatalı token', 'token' => 'short-token', 'is_active' => false]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-qr-lifecycle', ['--json' => true]));
        $output = Artisan::output();
        $this->assertStringContainsString('invalid_token_format', $output);
        $this->assertStringNotContainsString('short-token', $output);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'token' => 'short-token']);
    }

    public function test_scan_count_without_timestamp_is_reported(): void
    {
        $experience = Experience::create(['title' => 'Taslak deneyim sayaç', 'slug' => 'taslak-qr-sayac', 'status' => 'draft']);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Sayaç tutarsız', 'is_active' => false, 'scan_count' => 4, 'last_scanned_at' => null]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-qr-lifecycle', ['--json' => true]));
        $this->assertStringContainsString('scan_count_without_timestamp', Artisan::output());
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'scan_count' => 4, 'last_scanned_at' => null]);
    }

    public function test_future_last_scan_timestamp_is_reported(): void
    {
        $experience = Experience::create(['title' => 'Taslak deneyim gelecek', 'slug' => 'taslak-qr-gelecek', 'status' => 'draft']);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Gelecek tarama', 'is_active' => false, 'scan_count' => 1, 'last_scanned_at' => now()->addDay()]);

        $this->assertSame(1, Artisan::call('orvionart:inspect-qr-lifecycle', ['--json' => true]));
        $this->assertStringContainsString('future_scan_timestamp', Artisan::output());
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'scan_count' => 1]);
    }
}
