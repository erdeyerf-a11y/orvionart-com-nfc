<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\SitePageRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteRedirectInspectionTest extends TestCase
{
    use RefreshDatabase;

    private function publishedPage(string $slug): SitePage
    {
        return SitePage::create([
            'title' => 'Yayın sayfası',
            'slug' => $slug,
            'page_type' => 'standard',
            'status' => 'published',
            'sections' => [],
            'published_sections' => [['type' => 'text', 'text' => 'Yayın içeriği']],
            'published_title' => 'Yayın sayfası',
            'published_slug' => $slug,
            'published_page_type' => 'standard',
            'published_at' => now()->subMinute(),
            'version' => 1,
        ]);
    }

    public function test_valid_redirect_to_published_page_passes_read_only_inspection(): void
    {
        $target = $this->publishedPage('yeni-adres');
        $redirect = SitePageRedirect::create([
            'site_page_id' => $target->id,
            'old_slug' => 'eski-adres',
            'new_slug' => 'yeni-adres',
            'status_code' => 301,
        ]);

        $this->artisan('orvionart:inspect-site-redirects --json')->assertExitCode(0);
        $this->assertDatabaseHas('site_page_redirects', ['id' => $redirect->id, 'old_slug' => 'eski-adres']);
    }

    public function test_redirect_to_unavailable_target_is_reported_without_modification(): void
    {
        $target = SitePage::create([
            'title' => 'Taslak hedef',
            'slug' => 'taslak-hedef',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [],
            'version' => 1,
        ]);
        $redirect = SitePageRedirect::create([
            'site_page_id' => $target->id,
            'old_slug' => 'eski-taslak-hedef',
            'new_slug' => 'taslak-hedef',
            'status_code' => 301,
        ]);

        $this->artisan('orvionart:inspect-site-redirects --json')
            ->expectsOutputToContain('target_unavailable')
            ->assertExitCode(1);
        $this->assertDatabaseHas('site_page_redirects', ['id' => $redirect->id, 'new_slug' => 'taslak-hedef']);
    }

    public function test_redirect_with_unsupported_status_code_is_reported(): void
    {
        $target = $this->publishedPage('durum-kodu-hedef');
        SitePageRedirect::create([
            'site_page_id' => $target->id,
            'old_slug' => 'durum-kodu-eski',
            'new_slug' => 'durum-kodu-hedef',
            'status_code' => 307,
        ]);

        $this->artisan('orvionart:inspect-site-redirects --json')
            ->expectsOutputToContain('unsupported_status_code')
            ->assertExitCode(1);
    }

    public function test_redirect_source_shadowed_by_editable_page_slug_is_reported(): void
    {
        $target = $this->publishedPage('hedef-sayfa');
        SitePage::create([
            'title' => 'Eski adresi kullanan taslak',
            'slug' => 'eski-adres',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [],
            'version' => 1,
        ]);
        SitePageRedirect::create([
            'site_page_id' => $target->id,
            'old_slug' => 'eski-adres',
            'new_slug' => 'hedef-sayfa',
            'status_code' => 301,
        ]);

        $this->artisan('orvionart:inspect-site-redirects --json')
            ->expectsOutputToContain('source_shadowed_by_page')
            ->assertExitCode(1);
    }
}
