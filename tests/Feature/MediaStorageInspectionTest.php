<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_storage_inspection_reports_unreferenced_managed_file_without_deleting_it(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-media/2026/10/orphan.jpg', 'orphan');

        $this->artisan('orvionart:inspect-media-storage --json')
            ->expectsOutputToContain('unreferenced_managed_media_files')
            ->expectsOutputToContain('orvionart-media/2026/10/orphan.jpg')
            ->assertExitCode(1);

        $this->assertTrue(Storage::disk('local')->exists('orvionart-media/2026/10/orphan.jpg'));
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_media_storage_inspection_does_not_report_a_file_referenced_by_media_asset(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-media/2026/10/linked.jpg', 'linked');
        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Bağlı görsel',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/linked.jpg',
            'original_name' => 'linked.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 6,
        ]);

        $this->artisan('orvionart:inspect-media-storage --json')
            ->expectsOutputToContain('"unreferenced_managed_files": 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
        $this->assertTrue(Storage::disk('local')->exists('orvionart-media/2026/10/linked.jpg'));
    }
    public function test_media_storage_inspection_reports_quarantine_leftovers_without_deleting_them(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-trash/2026/10/pending-cleanup.jpg', 'pending');

        $this->artisan('orvionart:inspect-media-storage --json')
            ->expectsOutputToContain('media_quarantine_cleanup_pending')
            ->expectsOutputToContain('orvionart-trash/2026/10/pending-cleanup.jpg')
            ->assertExitCode(1);

        $this->assertTrue(Storage::disk('local')->exists('orvionart-trash/2026/10/pending-cleanup.jpg'));
    }

}
