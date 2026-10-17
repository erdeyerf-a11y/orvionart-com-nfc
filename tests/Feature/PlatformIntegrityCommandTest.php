<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\QrLink;
use App\Models\SitePage;
use App\Models\SitePageRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PlatformIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_integrity_command_is_read_only_when_no_inconsistencies_exist(): void
    {
        $this->artisan('orvionart:inspect-platform')
            ->expectsOutputToContain('salt okunur')
            ->assertExitCode(0);

        $this->assertDatabaseCount('experiences', 0);
        $this->assertDatabaseCount('qr_links', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_platform_integrity_reports_active_qr_link_to_unavailable_experience_without_exposing_token(): void
    {
        $experience = Experience::create([
            'title' => 'Taslak deneyim',
            'slug' => 'taslak-deneyim-platform-check',
            'status' => 'draft',
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Taslak QR',
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('orvionart:inspect-platform', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('active_qr_target_unavailable', $output);
        $this->assertStringNotContainsString($link->token, $output);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'token' => $link->token, 'scan_count' => 0]);
    }

    public function test_platform_integrity_classifies_future_scheduled_qr_target_as_waiting_not_broken(): void
    {
        $experience = Experience::create([
            'title' => 'Zamanlanmış deneyim',
            'slug' => 'zamanlanmis-deneyim-platform-check',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Zamanlanmış QR',
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('orvionart:inspect-platform', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(0, $exitCode);
        $this->assertIsArray($report);
        $this->assertSame(0, $report['counts']['qr_links_active_with_unavailable_target']);
        $this->assertSame(1, $report['counts']['qr_links_waiting_for_scheduled_experience']);
        $this->assertSame([$link->id], $report['issues']['active_qr_link_ids_waiting_for_scheduled_experience']);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => true, 'scan_count' => 0]);
    }

    public function test_platform_integrity_reports_missing_file_for_public_experience_media(): void
    {
        Storage::fake('local');
        $experience = Experience::create([
            'title' => 'Yayındaki deneyim',
            'slug' => 'yayindaki-deneyim-platform-check',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Eksik görsel',
            'disk' => 'local',
            'path' => 'missing/public-image.jpg',
            'original_name' => 'public-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);

        $this->artisan('orvionart:inspect-platform --json')
            ->expectsOutputToContain('media_file_missing')
            ->assertExitCode(1);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'path' => 'missing/public-image.jpg']);
    }


    public function test_platform_integrity_reports_missing_media_record_in_retained_page_revision(): void
    {
        $page = SitePage::create([
            'title' => 'Geçmiş sürüm sayfası',
            'slug' => 'gecmis-surum-sayfasi-platform-check',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [],
            'version' => 2,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id,
            'version' => 1,
            'event' => 'saved',
            'snapshot' => [
                'sections' => [['type' => 'image_text', 'image_asset_id' => 987654]],
                'published_sections' => [],
                'social_image_asset_id' => null,
                'published_social_image_asset_id' => null,
            ],
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('orvionart:inspect-platform', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, $report['counts']['historical_revision_media_reference_problems']);
        $this->assertSame([[
            'revision_id' => $revision->id,
            'site_page_id' => $page->id,
            'asset_id' => 987654,
            'problem' => 'asset_record_missing',
        ]], $report['issues']['historical_revision_media_reference_problems']);
        $this->assertDatabaseHas('site_page_revisions', ['id' => $revision->id, 'site_page_id' => $page->id]);
    }

    public function test_platform_integrity_reports_missing_file_referenced_only_by_retained_page_revision(): void
    {
        Storage::fake('local');
        $page = SitePage::create([
            'title' => 'Eski görsel sayfası',
            'slug' => 'eski-gorsel-sayfasi-platform-check',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [],
            'version' => 2,
        ]);
        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => $page->id,
            'uploaded_by' => null,
            'title' => 'Yalnız geçmiş sürümdeki görsel',
            'disk' => 'local',
            'path' => 'orvionart-site-media/missing-historical.jpg',
            'original_name' => 'missing-historical.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id,
            'version' => 1,
            'event' => 'saved',
            'snapshot' => [
                'sections' => [['type' => 'image_text', 'image_asset_id' => $asset->id]],
                'published_sections' => [],
                'social_image_asset_id' => null,
                'published_social_image_asset_id' => null,
            ],
            'created_at' => now(),
        ]);

        $exitCode = Artisan::call('orvionart:inspect-platform', ['--json' => true]);
        $report = json_decode(Artisan::output(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame(1, $report['counts']['historical_revision_media_reference_problems']);
        $this->assertSame('file_missing_or_disk_unavailable', $report['issues']['historical_revision_media_reference_problems'][0]['problem']);
        $this->assertSame($revision->id, $report['issues']['historical_revision_media_reference_problems'][0]['revision_id']);
    }

    public function test_platform_integrity_reports_published_site_page_reference_to_missing_media_record(): void
    {
        SitePage::create([
            'title' => 'Yayındaki sayfa',
            'slug' => 'yayindaki-sayfa-platform-check',
            'page_type' => 'standard',
            'status' => 'published',
            'sections' => [],
            'published_sections' => [['type' => 'hero', 'image_asset_id' => 987654]],
            'published_slug' => 'yayindaki-sayfa-platform-check',
            'published_at' => now()->subMinute(),
            'published_title' => 'Yayındaki sayfa',
        ]);

        $this->artisan('orvionart:inspect-platform --json')
            ->expectsOutputToContain('published_media_reference_invalid')
            ->assertExitCode(1);
    }
}
