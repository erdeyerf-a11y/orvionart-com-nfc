<?php

namespace Tests\Feature;

use App\Models\DemoContent;
use App\Models\DemoSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoInspectionCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_inspection_is_read_only_and_reports_a_clean_inactive_engine(): void
    {
        DemoSetting::create([
            'setting_key' => 'default',
            'is_active' => false,
            'journey_days' => 7,
            'retention_days' => 30,
            'title' => 'ORVIONART Demo',
            'paused_message' => 'Demo şu anda kullanılamıyor.',
        ]);

        $this->artisan('orvionart:inspect-demo')
            ->expectsOutputToContain('salt okunur')
            ->assertExitCode(0);

        $this->assertDatabaseCount('demo_settings', 1);
        $this->assertDatabaseCount('demo_contents', 0);
    }

    public function test_demo_inspection_warns_when_media_is_missing(): void
    {
        Storage::fake('local');
        DemoSetting::create([
            'setting_key' => 'default',
            'is_active' => false,
            'journey_days' => 7,
            'retention_days' => 30,
            'title' => 'ORVIONART Demo',
            'paused_message' => 'Demo şu anda kullanılamıyor.',
        ]);
        DemoContent::create([
            'title' => 'Medya dosyası olmayan kayıt',
            'is_active' => false,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/missing.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 100,
        ]);

        $this->artisan('orvionart:inspect-demo --json')
            ->expectsOutputToContain('demo_media_missing')
            ->assertExitCode(1);

        $this->assertDatabaseCount('demo_contents', 1);
    }
}
