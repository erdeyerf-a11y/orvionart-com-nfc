<?php

namespace Tests\Feature;

use App\Models\DemoContent;
use App\Models\MediaAsset;
use App\Models\SitePage;
use App\Models\SitePageRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaAssetDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_asset_cannot_be_deleted_when_demo_content_references_the_same_disk_and_path(): void
    {
        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Demo görseli',
            'disk' => 'local',
            'path' => 'orvionart-demo/2026/10/demo-image.jpg',
            'original_name' => 'demo-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);

        DemoContent::create([
            'title' => 'Demo içerik',
            'is_active' => true,
            'media_disk' => 'local',
            'media_path' => 'orvionart-demo/2026/10/demo-image.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 123,
        ]);

        $this->assertTrue($asset->isReferencedByDemoContent());
        $this->assertFalse($asset->canBeDeletedSafely());
    }

    public function test_same_path_on_a_different_disk_does_not_block_deletion(): void
    {
        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Kütüphane görseli',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/library-image.jpg',
            'original_name' => 'library-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);

        DemoContent::create([
            'title' => 'Farklı disk demo içeriği',
            'is_active' => true,
            'media_disk' => 'private-demo',
            'media_path' => 'orvionart-media/2026/10/library-image.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_size' => 123,
        ]);

        $this->assertFalse($asset->isReferencedByDemoContent());
        $this->assertTrue($asset->canBeDeletedSafely());
    }

    public function test_asset_cannot_be_deleted_when_another_asset_row_references_the_same_disk_and_path(): void
    {
        $attributes = [
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Paylaşılan dosya',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/shared-image.jpg',
            'original_name' => 'shared-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ];

        $first = MediaAsset::create($attributes);
        $second = MediaAsset::create(array_merge($attributes, ['title' => 'İkinci kayıt']));

        $this->assertTrue($first->isReferencedByAnotherAsset());
        $this->assertTrue($second->isReferencedByAnotherAsset());
        $this->assertFalse($first->canBeDeletedSafely());
        $this->assertFalse($second->canBeDeletedSafely());
    }

    public function test_same_path_on_a_different_disk_is_not_a_shared_asset_reference(): void
    {
        $first = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => null,
            'title' => 'Yerel dosya', 'disk' => 'local',
            'path' => 'orvionart-media/2026/10/shared-name.jpg',
            'original_name' => 'shared-name.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 123,
        ]);
        MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => null,
            'title' => 'Diğer disk', 'disk' => 'private',
            'path' => 'orvionart-media/2026/10/shared-name.jpg',
            'original_name' => 'shared-name.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 123,
        ]);

        $this->assertFalse($first->isReferencedByAnotherAsset());
        $this->assertTrue($first->canBeDeletedSafely());
    }


    public function test_media_asset_cannot_be_deleted_when_only_a_retained_site_page_revision_references_it(): void
    {
        $page = SitePage::create([
            'title' => 'Atölye sayfası',
            'slug' => 'atolye-sayfasi',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [['type' => 'text', 'title' => 'Güncel içerik']],
            'published_sections' => [],
            'version' => 3,
        ]);

        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => $page->id,
            'uploaded_by' => null,
            'title' => 'Eski sürüm görseli',
            'disk' => 'local',
            'path' => 'orvionart-site-media/2026/10/old-revision-image.jpg',
            'original_name' => 'old-revision-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);

        SitePageRevision::create([
            'site_page_id' => $page->id,
            'version' => 2,
            'event' => 'updated',
            'change_summary' => 'Önceki görsel kullanıldı',
            'snapshot' => [
                'title' => 'Atölye sayfası',
                'slug' => 'atolye-sayfasi',
                'page_type' => 'standard',
                'sections' => [['type' => 'image_text', 'image_asset_id' => $asset->id]],
                'published_sections' => [],
                'social_image_asset_id' => null,
                'published_social_image_asset_id' => null,
            ],
            'actor_id' => null,
            'created_at' => now(),
        ]);

        $this->assertTrue($asset->isReferencedBySitePage());
        $this->assertFalse($asset->canBeDeletedSafely());
    }

    public function test_media_asset_is_blocked_when_only_a_historical_social_image_references_it(): void
    {
        $page = SitePage::create([
            'title' => 'Hakkımızda',
            'slug' => 'hakkimizda',
            'page_type' => 'about',
            'status' => 'draft',
            'sections' => [],
            'published_sections' => [],
            'version' => 1,
        ]);
        $asset = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => $page->id, 'uploaded_by' => null,
            'title' => 'Eski sosyal görsel', 'disk' => 'local',
            'path' => 'orvionart-site-media/2026/10/old-social-image.jpg',
            'original_name' => 'old-social-image.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 123,
        ]);
        SitePageRevision::create([
            'site_page_id' => $page->id, 'version' => 1, 'event' => 'created',
            'change_summary' => 'İlk sürüm',
            'snapshot' => [
                'sections' => [], 'published_sections' => [],
                'social_image_asset_id' => $asset->id,
                'published_social_image_asset_id' => null,
            ],
            'actor_id' => null, 'created_at' => now(),
        ]);

        $this->assertTrue($asset->isReferencedBySitePage());
        $this->assertFalse($asset->canBeDeletedSafely());
    }

    public function test_unreferenced_library_asset_remains_deletable(): void
    {
        $asset = MediaAsset::create([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Bağımsız görsel',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/free-image.jpg',
            'original_name' => 'free-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 123,
        ]);

        $this->assertFalse($asset->isReferencedByDemoContent());
        $this->assertTrue($asset->canBeDeletedSafely());
    }
}
