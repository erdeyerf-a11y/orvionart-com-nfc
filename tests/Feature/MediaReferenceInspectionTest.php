<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaReferenceInspectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_reference_inspection_reports_missing_managed_file_without_changing_record(): void
    {
        Storage::fake('local');
        $asset = MediaAsset::create([
            'experience_id' => null,
            'uploaded_by' => null,
            'title' => 'Eksik görsel',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/missing.jpg',
            'original_name' => 'missing.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 100,
        ]);

        $this->artisan('orvionart:inspect-media-references --json')
            ->expectsOutputToContain('"missing_files": 1')
            ->expectsOutputToContain('"code": "missing_file"')
            ->assertExitCode(1);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'path' => 'orvionart-media/2026/10/missing.jpg']);
    }

    public function test_media_reference_inspection_accepts_existing_managed_file(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-media/2026/10/present.jpg', 'present');
        $asset = MediaAsset::create([
            'experience_id' => null,
            'uploaded_by' => null,
            'title' => 'Var olan görsel',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/present.jpg',
            'original_name' => 'present.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 7,
        ]);

        $this->artisan('orvionart:inspect-media-references --json')
            ->expectsOutputToContain('"missing_files": 0')
            ->assertExitCode(0);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_media_reference_inspection_reports_path_traversal_without_checking_or_deleting_file(): void
    {
        Storage::fake('local');
        $asset = MediaAsset::create([
            'experience_id' => null,
            'uploaded_by' => null,
            'title' => 'Şüpheli yol',
            'disk' => 'local',
            'path' => 'orvionart-media/../../outside.jpg',
            'original_name' => 'outside.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 7,
        ]);

        $this->artisan('orvionart:inspect-media-references --json')
            ->expectsOutputToContain('"unsafe_paths": 1')
            ->expectsOutputToContain('"code": "unsafe_path"')
            ->assertExitCode(1);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'path' => 'orvionart-media/../../outside.jpg']);
    }
    public function test_media_reference_inspection_reports_symlink_target_outside_private_disk_without_mutating_record(): void
    {
        if (!function_exists('symlink')) {
            $this->markTestSkipped('Bu ortam sembolik bağlantı oluşturmayı desteklemiyor.');
        }

        Storage::fake('local');
        $disk = Storage::disk('local');
        $relativePath = 'orvionart-media/2026/10/linked.jpg';
        $linkPath = $disk->path($relativePath);
        if (!is_dir(dirname($linkPath))) {
            mkdir(dirname($linkPath), 0777, true);
        }

        $outsidePath = tempnam(sys_get_temp_dir(), 'orvionart-outside-');
        if ($outsidePath === false) {
            $this->markTestSkipped('Geçici dosya oluşturulamadı.');
        }
        file_put_contents($outsidePath, 'outside private disk');

        try {
            if (!@symlink($outsidePath, $linkPath)) {
                $this->markTestSkipped('Bu ortam sembolik bağlantı oluşturmayı desteklemiyor.');
            }

            $asset = MediaAsset::create([
                'experience_id' => null,
                'uploaded_by' => null,
                'title' => 'Dış hedefe bağlı medya',
                'disk' => 'local',
                'path' => $relativePath,
                'original_name' => 'linked.jpg',
                'mime_type' => 'image/jpeg',
                'kind' => 'image',
                'file_size' => 20,
            ]);

            $this->artisan('orvionart:inspect-media-references --json')
                ->expectsOutputToContain('"unsafe_filesystem_targets": 1')
                ->expectsOutputToContain('"code": "unsafe_filesystem_target"')
                ->assertExitCode(1);

            $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'path' => $relativePath]);
            $this->assertFileExists($outsidePath);
        } finally {
            if (is_link($linkPath)) {
                unlink($linkPath);
            }
            if (is_file($outsidePath)) {
                unlink($outsidePath);
            }
        }
    }

}
