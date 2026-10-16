<?php

namespace Tests\Feature;

use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SiteBuilderIntegrityCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_builder_inspection_is_read_only_when_pages_and_navigation_are_consistent(): void
    {
        SitePage::create([
            'title' => 'Hakkımızda',
            'slug' => 'hakkimizda-denetim',
            'page_type' => 'about',
            'status' => 'published',
            'sections' => [],
            'published_sections' => [['type' => 'hero', 'title' => 'Hakkımızda', 'is_active' => true]],
            'published_title' => 'Hakkımızda',
            'published_slug' => 'hakkimizda-denetim',
            'published_page_type' => 'about',
            'published_show_in_menu' => true,
            'published_at' => now()->subMinute(),
            'version' => 1,
        ]);
        $page = SitePage::query()->firstOrFail();
        SiteNavigationItem::create([
            'location' => 'header',
            'label' => 'Hakkımızda',
            'site_page_id' => $page->id,
            'sort_order' => 10,
            'is_active' => true,
        ]);

        $this->artisan('orvionart:inspect-site-builder')
            ->expectsOutputToContain('salt okunur')
            ->assertExitCode(0);

        $this->assertDatabaseHas('site_pages', ['id' => $page->id, 'status' => 'published']);
        $this->assertDatabaseHas('site_navigation_items', ['site_page_id' => $page->id, 'is_active' => 1]);
    }

    public function test_site_builder_inspection_reports_active_navigation_to_draft_page(): void
    {
        $page = SitePage::create([
            'title' => 'Gizli taslak',
            'slug' => 'gizli-taslak-denetim',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [],
            'version' => 1,
        ]);
        $item = SiteNavigationItem::create([
            'location' => 'header',
            'label' => 'Taslak bağlantısı',
            'site_page_id' => $page->id,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('orvionart:inspect-site-builder', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('active_navigation_target_invalid', $output);
        $this->assertStringContainsString((string) $item->id, $output);
        $this->assertDatabaseHas('site_navigation_items', ['id' => $item->id, 'site_page_id' => $page->id]);
    }

    public function test_site_builder_inspection_reports_incomplete_published_snapshot(): void
    {
        $page = SitePage::create([
            'title' => 'Bozuk yayın kaydı',
            'slug' => 'bozuk-yayin-kaydi',
            'page_type' => 'standard',
            'status' => 'published',
            'sections' => [],
            'published_sections' => null,
            'published_slug' => null,
            'published_at' => null,
            'version' => 1,
        ]);

        $this->artisan('orvionart:inspect-site-builder --json')
            ->expectsOutputToContain('published_page_snapshot_incomplete')
            ->assertExitCode(1);

        $this->assertDatabaseHas('site_pages', ['id' => $page->id, 'status' => 'published']);
    }

    public function test_site_builder_inspection_reports_non_https_external_navigation(): void
    {
        $item = SiteNavigationItem::create([
            'location' => 'footer',
            'label' => 'Güvensiz bağlantı',
            'external_url' => 'http://example.test/path',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $exitCode = Artisan::call('orvionart:inspect-site-builder', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('active_navigation_target_invalid', $output);
        $this->assertStringContainsString((string) $item->id, $output);
        $this->assertStringNotContainsString('http://example.test/path', $output);
    }

}
