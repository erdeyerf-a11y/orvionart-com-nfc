<?php

namespace Tests\Feature;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaPreviewSafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Preview Admin',
            'email' => 'preview-admin@example.test',
            'password' => 'A-very-long-test-password-2026',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    private function asset(array $overrides = []): MediaAsset
    {
        return MediaAsset::create(array_merge([
            'experience_id' => null,
            'site_page_id' => null,
            'uploaded_by' => null,
            'title' => 'Test image',
            'disk' => 'local',
            'path' => 'orvionart-media/2026/10/test.jpg',
            'original_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 4,
        ], $overrides));
    }

    public function test_admin_preview_streams_managed_file_from_private_local_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('orvionart-media/2026/10/test.jpg', 'test');
        $asset = $this->asset();

        $this->actingAs($this->admin())
            ->get('/admin/media/'.$asset->id.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertHeader('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    public function test_admin_preview_rejects_asset_on_non_local_disk(): void
    {
        $asset = $this->asset(['disk' => 'public']);

        $this->actingAs($this->admin())
            ->get('/admin/media/'.$asset->id.'/preview')
            ->assertNotFound();
    }

    public function test_admin_preview_rejects_traversal_path_even_when_file_exists(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('../outside.jpg', 'test');
        $asset = $this->asset(['path' => '../outside.jpg']);

        $this->actingAs($this->admin())
            ->get('/admin/media/'.$asset->id.'/preview')
            ->assertNotFound();
    }
    public function test_global_security_headers_add_default_content_security_policy_when_route_has_none(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; style-src 'self' 'unsafe-inline'; upgrade-insecure-requests");
    }

}
