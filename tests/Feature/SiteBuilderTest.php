<?php

namespace Tests\Feature;

use App\Models\SitePage;
use App\Models\SitePageRevision;
use App\Models\User;
use App\Models\Experience;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\SiteNavigationItem;
use App\Models\SiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name'=>'Site Builder Admin','email'=>'sitebuilder@example.test','password'=>'A-very-long-test-password-2026','role'=>'super_admin','is_active'=>true]);
    }

    public function test_site_builder_form_offers_testimonial_and_statistics_sections(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.site-builder.create'))
            ->assertOk()
            ->assertSee('Müşteri Görüşleri')
            ->assertSee('İstatistikler')
            ->assertSee('Görüş metni | İsim')
            ->assertSee('Değer | Açıklama');
    }

    public function test_published_page_renders_testimonial_and_statistics_sections_as_escaped_content(): void
    {
        SitePage::create([
            'title' => 'Hakkımızda', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [],
            'published_sections' => [
                ['type' => 'testimonials', 'title' => 'Müşteri görüşleri', 'items' => [
                    ['title' => 'Çok güzel bir çalışma', 'text' => 'Ayşe'],
                    ['title' => '<script>alert(1)</script>', 'text' => 'Güvenli içerik'],
                ], 'is_active' => true],
                ['type' => 'stats', 'title' => 'Rakamlarla', 'items' => [
                    ['title' => '250+', 'text' => 'Tamamlanan çalışma'],
                    ['title' => '12 yıl', 'text' => 'Deneyim'],
                ], 'is_active' => true],
            ],
            'published_title' => 'Hakkımızda', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('class="testimonials-grid"', false)
            ->assertSee('class="stats-grid"', false)
            ->assertSee('Çok güzel bir çalışma')
            ->assertSee('Ayşe')
            ->assertSee('250+')
            ->assertSee('Tamamlanan çalışma')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_draft_site_page_is_not_publicly_accessible(): void
    {
        $page=SitePage::create(['title'=>'Taslak sayfa','slug'=>'taslak-sayfa','page_type'=>'standard','status'=>'draft','sections'=>[['type'=>'hero','title'=>'Taslak']],'version'=>1]);
        $this->get('/s/taslak-sayfa')->assertNotFound();
    }

    public function test_published_home_page_renders_from_published_snapshot(): void
    {
        SitePage::create(['title'=>'ORVIONART','slug'=>'home','page_type'=>'home','status'=>'published','sections'=>[['type'=>'hero','title'=>'Yeni taslak']],
            'published_sections'=>[['type'=>'hero','title'=>'Yayınlanan ana sayfa','is_active'=>true]],'published_title'=>'ORVIONART','published_slug'=>'home','published_page_type'=>'home','published_show_in_menu'=>false,'published_at'=>now(),'noindex'=>true,'published_noindex'=>true,'version'=>2]);
        $this->get('/')->assertOk()->assertSee('Yayınlanan ana sayfa')->assertDontSee('Yeni taslak')->assertHeader('X-Robots-Tag','noindex, nofollow')->assertHeader('Cache-Control','no-store, private');
    }


    public function test_site_builder_contact_section_and_footer_render_safe_clickable_contact_links(): void
    {
        SiteSetting::create(['setting_key' => 'general', 'setting_value' => [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'contact_email' => 'iletisim@example.test', 'contact_phone' => '+90 (555) 123-45-67',
            'footer_text' => '© ORVIONART', 'footer_layout' => 'split',
        ]]);

        SitePage::create([
            'title' => 'İletişim', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [],
            'published_sections' => [['type' => 'contact', 'title' => 'Bize ulaşın', 'is_active' => true]],
            'published_title' => 'İletişim', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Bize ulaşın')
            ->assertSee('href="mailto:iletisim@example.test"', false)
            ->assertSee('href="tel:+905551234567"', false)
            ->assertSee('Telefon: +90 (555) 123-45-67')
            ->assertSee('class="footer-contact"', false);
    }

    public function test_public_site_builder_page_is_noindex_outside_production_even_when_page_allows_indexing(): void
    {
        config(['app.env' => 'testing']);
        SitePage::create([
            'title' => 'Hazırlık sayfası', 'slug' => 'hazirlik-sayfasi', 'page_type' => 'standard',
            'status' => 'published', 'sections' => [],
            'published_sections' => [['type' => 'hero', 'title' => 'Hazırlık içeriği', 'is_active' => true]],
            'published_title' => 'Hazırlık sayfası', 'published_slug' => 'hazirlik-sayfasi',
            'published_page_type' => 'standard', 'published_show_in_menu' => false,
            'published_at' => now(), 'published_noindex' => false, 'version' => 1,
        ]);

        $this->get('/s/hazirlik-sayfasi')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function test_experience_list_section_only_shows_public_experiences(): void
    {
        SitePage::create(['title'=>'Deneyimler','slug'=>'deneyimler','page_type'=>'standard','status'=>'published','sections'=>[],
            'published_sections'=>[['type'=>'experience_list','title'=>'Deneyimler','is_active'=>true]],'published_title'=>'Deneyimler','published_slug'=>'deneyimler','published_page_type'=>'standard','published_show_in_menu'=>false,'published_at'=>now(),'published_noindex'=>true,'version'=>1]);
        Experience::create(['title'=>'Yayındaki deneyim','slug'=>'yayindaki-deneyim','public_id'=>str_repeat('A',40),'status'=>'published','published_at'=>now(),'summary'=>'Görünen özet']);
        Experience::create(['title'=>'Taslak deneyim','slug'=>'taslak-deneyim','public_id'=>str_repeat('B',40),'status'=>'draft','summary'=>'Görünmemeli']);
        $this->get('/s/deneyimler')->assertOk()->assertSee('Yayındaki deneyim')->assertDontSee('Taslak deneyim')->assertHeader('Cache-Control','no-store, private');
    }

    public function test_sitemap_route_returns_xml_and_does_not_fail_outside_production(): void
    {
        $response=$this->get('/sitemap.xml');
        $response->assertOk()->assertHeader('Content-Type','application/xml; charset=UTF-8')->assertSee('<urlset',false);
    }



    public function test_production_search_indexing_is_opt_in_and_disabled_by_default(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://orvionart.example', 'orvionart.public_indexing_enabled' => false]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /')->assertDontSee('Sitemap:');
        $this->get('/sitemap.xml')->assertOk()->assertSee('<urlset', false)->assertDontSee('<url>', false);

        config(['orvionart.public_indexing_enabled' => true]);
        $this->get('/robots.txt')->assertOk()->assertSee('Allow: /')->assertSee('Sitemap: https://orvionart.example/sitemap.xml');
    }

    public function test_production_sitemap_lastmod_tracks_publication_not_later_draft_edits(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://orvionart.example', 'orvionart.public_indexing_enabled' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');
        $publishedAt = now()->subDays(12)->startOfSecond();
        $draftEditedAt = now()->subMinute()->startOfSecond();

        SitePage::create([
            'title' => 'Canlı sayfa', 'slug' => 'canli-sayfa', 'page_type' => 'standard',
            'status' => 'published', 'sections' => [['type' => 'hero', 'title' => 'Yeni taslak']],
            'published_sections' => [['type' => 'hero', 'title' => 'Canlı içerik', 'is_active' => true]],
            'published_title' => 'Canlı sayfa', 'published_slug' => 'canli-sayfa',
            'published_page_type' => 'standard', 'published_show_in_menu' => false,
            'published_at' => $publishedAt, 'updated_at' => $draftEditedAt,
            'published_noindex' => false, 'version' => 2,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertSee($publishedAt->toAtomString(), false);
        $response->assertDontSee($draftEditedAt->toAtomString(), false);
    }

    public function test_production_sitemap_experience_lastmod_uses_publication_timestamp(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://orvionart.example', 'orvionart.public_indexing_enabled' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');
        $publishedAt = now()->subDays(8)->startOfSecond();
        $draftEditedAt = now()->subSeconds(30)->startOfSecond();

        Experience::create([
            'title' => 'Yayınlanan deneyim', 'slug' => 'yayinda-deneyim',
            'public_id' => str_repeat('C', 40), 'status' => 'published',
            'published_at' => $publishedAt, 'updated_at' => $draftEditedAt,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertSee($publishedAt->toAtomString(), false);
        $response->assertDontSee($draftEditedAt->toAtomString(), false);
    }

    public function test_production_sitemap_excludes_external_canonical_urls_and_deduplicates_same_origin_canonicals(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://orvionart.example', 'orvionart.public_indexing_enabled' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');

        foreach ([
            ['title' => 'Kanonik A', 'slug' => 'kanonik-a', 'canonical_url' => 'https://orvionart.example/tek-kanonik'],
            ['title' => 'Kanonik B', 'slug' => 'kanonik-b', 'canonical_url' => 'https://orvionart.example/tek-kanonik'],
            ['title' => 'Harici kanonik', 'slug' => 'harici-kanonik', 'canonical_url' => 'https://external.example/dis-kaynak'],
        ] as $row) {
            SitePage::create([
                'title' => $row['title'], 'slug' => $row['slug'], 'page_type' => 'standard',
                'status' => 'published', 'sections' => [], 'published_sections' => [],
                'published_title' => $row['title'], 'published_slug' => $row['slug'],
                'published_page_type' => 'standard', 'published_show_in_menu' => false,
                'published_at' => now()->subDay(), 'canonical_url' => $row['canonical_url'],
                'published_canonical_url' => $row['canonical_url'], 'published_noindex' => false, 'version' => 1,
            ]);
        }

        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = $response->getContent();
        $this->assertSame(1, substr_count($xml, '<loc>https://orvionart.example/tek-kanonik</loc>'));
        $this->assertStringNotContainsString('external.example', $xml);
    }

    public function test_scheduled_experience_sitemap_lastmod_uses_the_time_it_became_public(): void
    {
        config(['app.env' => 'production', 'app.url' => 'https://orvionart.example', 'orvionart.public_indexing_enabled' => true]);
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');
        \Illuminate\Support\Facades\URL::forceRootUrl('https://orvionart.example');
        $scheduledAt = now()->subMinutes(20)->startOfSecond();
        $stalePublishedAt = now()->subDays(20)->startOfSecond();

        Experience::create([
            'title' => 'Zamanlanmış deneyim', 'slug' => 'zamanlanmis-deneyim',
            'public_id' => str_repeat('D', 40), 'status' => 'scheduled',
            'published_at' => $stalePublishedAt, 'scheduled_at' => $scheduledAt,
        ]);

        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertSee($scheduledAt->toAtomString(), false);
        $response->assertDontSee($stalePublishedAt->toAtomString(), false);
        \Illuminate\Support\Facades\URL::forceRootUrl(null);
    }

    public function test_published_page_uses_published_slug_until_new_slug_is_published(): void
    {
        SitePage::create([
            'title'=>'Canlı Başlık','slug'=>'canli-adres','page_type'=>'standard','status'=>'published',
            'sections'=>[['type'=>'hero','title'=>'Taslak içerik']],
            'published_sections'=>[['type'=>'hero','title'=>'Canlı içerik','is_active'=>true]],
            'published_title'=>'Canlı Başlık','published_slug'=>'canli-adres','published_page_type'=>'standard',
            'published_show_in_menu'=>false,'published_at'=>now(),'published_noindex'=>true,'version'=>1,
        ]);
        $this->get('/s/canli-adres')->assertOk()->assertSee('Canlı içerik')->assertHeader('Cache-Control','no-store, private');
    }

    public function test_published_slug_change_creates_permanent_redirect_after_publish(): void
    {
        $admin=$this->admin();
        $page=SitePage::create([
            'title'=>'Eski başlık','slug'=>'eski-adres','page_type'=>'standard','status'=>'published',
            'sections'=>[['type'=>'hero','title'=>'Yeni içerik','is_active'=>true]],
            'published_sections'=>[['type'=>'hero','title'=>'Eski yayın','is_active'=>true]],
            'published_title'=>'Eski başlık','published_slug'=>'eski-adres','published_page_type'=>'standard',
            'published_show_in_menu'=>false,'published_at'=>now(),'published_noindex'=>true,'version'=>1,
        ]);
        $payload=[
            'title'=>'Yeni başlık','slug'=>'yeni-adres','page_type'=>'standard','show_in_menu'=>'0','noindex'=>'1',
            'seo_title'=>'','meta_description'=>'','canonical_url'=>'','social_image_asset_id'=>'',
            'sections'=>[['type'=>'hero','title'=>'Yeni içerik','subtitle'=>'','body'=>'','image_asset_id'=>'','image_alt'=>'','cta_label'=>'','cta_url'=>'','variant'=>'default','items_text'=>'','is_active'=>'1']],
        ];
        $this->actingAs($admin)->put('/admin/site-builder/'.$page->id,$payload)->assertRedirect();
        // Draft save must leave the old published address and content intact.
        $this->get('/s/eski-adres')->assertOk()->assertSee('Eski yayın');
        $this->actingAs($admin)->post('/admin/site-builder/'.$page->id.'/publish')->assertRedirect();
        $this->get('/s/eski-adres')->assertRedirect('/s/yeni-adres');
        $this->get('/s/yeni-adres')->assertOk()->assertSee('Yeni içerik');
    }

    public function test_publish_rejects_slug_already_used_by_another_published_page_with_clear_response(): void
    {
        $admin = $this->admin();
        SitePage::create([
            'title' => 'Mevcut canlı sayfa', 'slug' => 'mevcut-canli-sayfa', 'page_type' => 'standard',
            'status' => 'published', 'sections' => [], 'published_sections' => [],
            'published_title' => 'Mevcut canlı sayfa', 'published_slug' => 'adres-cakismasi',
            'published_page_type' => 'standard', 'published_show_in_menu' => false,
            'published_at' => now()->subMinute(), 'published_noindex' => true, 'version' => 1,
        ]);
        $draft = SitePage::create([
            'title' => 'Yeni taslak', 'slug' => 'adres-cakismasi', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Yeni içerik', 'is_active' => true]],
            'noindex' => true, 'version' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$draft->id.'/publish')
            ->assertStatus(422)
            ->assertSee('Bu adres başka bir sayfanın yayındaki adresi olarak kullanılıyor.');

        $this->assertDatabaseHas('site_pages', ['id' => $draft->id, 'status' => 'draft', 'published_slug' => null]);
        $this->get('/s/adres-cakismasi')->assertOk()->assertSee('Mevcut canlı sayfa');
    }

    public function test_site_builder_preview_is_explicitly_noindex_and_never_cacheable(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Önizleme', 'slug' => 'onizleme-guvenligi', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Sadece önizleme', 'is_active' => true]],
            'noindex' => true, 'version' => 1,
        ]);

        $this->actingAs($admin)
            ->get('/admin/site-builder/'.$page->id.'/preview?viewport=mobile')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Sadece önizleme');
    }

    public function test_publish_rejects_a_stale_media_reference_without_changing_the_live_page(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Medya kontrolü',
            'slug' => 'medya-kontrolu',
            'page_type' => 'standard',
            'status' => 'draft',
            'sections' => [[
                'type' => 'image_text',
                'title' => 'Atölye',
                'image_asset_id' => 987654321,
                'items' => [],
            ]],
            'published_sections' => null,
            'version' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$page->id.'/publish')
            ->assertUnprocessable();

        $this->assertDatabaseHas('site_pages', [
            'id' => $page->id,
            'status' => 'draft',
            'published_sections' => null,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'site_page.published',
            'subject_id' => $page->id,
        ]);
    }

    public function test_site_builder_update_increments_the_locked_version_and_audits_the_transition(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Önceki başlık', 'slug' => 'kilitli-surum', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [], 'noindex' => true, 'version' => 7,
        ]);

        $payload = [
            'title' => 'Güncel başlık', 'slug' => 'kilitli-surum', 'page_type' => 'standard',
            'show_in_menu' => '0', 'noindex' => '1', 'seo_title' => '',
            'meta_description' => '', 'canonical_url' => '', 'social_image_asset_id' => '',
            'sections' => [[
                'type' => 'hero', 'title' => 'Güncel bölüm', 'subtitle' => '', 'body' => '',
                'image_asset_id' => '', 'image_alt' => '', 'cta_label' => '', 'cta_url' => '',
                'variant' => 'default', 'items_text' => '', 'is_active' => '1',
            ]],
        ];

        $this->actingAs($admin)->put('/admin/site-builder/'.$page->id, $payload)->assertRedirect();

        $this->assertDatabaseHas('site_pages', ['id' => $page->id, 'title' => 'Güncel başlık', 'version' => 8]);
        $this->assertDatabaseHas('site_page_revisions', ['site_page_id' => $page->id, 'version' => 8, 'event' => 'saved']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'site_page.updated',
            'subject_id' => $page->id,
        ]);
        $audit = AuditLog::query()->where('action', 'site_page.updated')->where('subject_id', $page->id)->latest('id')->firstOrFail();
        $this->assertSame(7, (int) $audit->metadata['before_version']);
        $this->assertSame(8, (int) $audit->metadata['version']);
    }

    public function test_super_admin_can_open_site_builder(): void
    {
        $this->actingAs($this->admin())->get('/admin/site-builder')->assertOk()->assertSee('Site Builder');
    }

    public function test_site_builder_rejects_more_than_100_items_in_a_section(): void
    {
        $admin = $this->admin();
        $items = implode("\n", array_map(fn (int $i): string => 'Başlık '.$i.' | Açıklama '.$i, range(1, 101)));

        $this->actingAs($admin)->from('/admin/site-builder/create')->post('/admin/site-builder', [
            'title' => 'SSS', 'slug' => 'sss-siniri', 'page_type' => 'faq', 'show_in_menu' => '1', 'noindex' => '1',
            'sections' => [[
                'type' => 'faq', 'title' => 'Sık sorulan sorular', 'subtitle' => '', 'body' => '',
                'image_asset_id' => '', 'image_alt' => '', 'cta_label' => '', 'cta_url' => '',
                'variant' => 'default', 'items_text' => $items, 'is_active' => '1',
            ]],
        ])->assertRedirect('/admin/site-builder/create')->assertSessionHasErrors('sections.0.items_text');

        $this->assertDatabaseMissing('site_pages', ['slug' => 'sss-siniri']);
    }

    public function test_site_builder_rejects_oversized_item_titles_and_unsafe_slider_urls(): void
    {
        $admin = $this->admin();
        $base = [
            'title' => 'Slider testi', 'slug' => 'slider-guvenlik-testi', 'page_type' => 'standard',
            'show_in_menu' => '1', 'noindex' => '1',
        ];

        $this->actingAs($admin)->from('/admin/site-builder/create')->post('/admin/site-builder', $base + [
            'sections' => [[
                'type' => 'faq', 'title' => 'SSS', 'items_text' => str_repeat('A', 181).' | Açıklama',
                'variant' => 'default', 'is_active' => '1',
            ]],
        ])->assertRedirect('/admin/site-builder/create')->assertSessionHasErrors('sections.0.items_text');

        $this->actingAs($admin)->from('/admin/site-builder/create')->post('/admin/site-builder', $base + [
            'sections' => [[
                'type' => 'hero_slider', 'title' => 'Slider',
                'items_text' => 'Başlık | Açıklama | | Aç | javascript:alert(1)',
                'variant' => 'default', 'is_active' => '1',
            ]],
        ])->assertRedirect('/admin/site-builder/create')->assertSessionHasErrors('sections.0.items_text');

        $this->assertDatabaseMissing('site_pages', ['slug' => 'slider-guvenlik-testi']);
    }

    public function test_site_builder_rejects_malformed_https_section_button_urls(): void
    {
        $this->actingAs($this->admin())->from('/admin/site-builder/create')->post('/admin/site-builder', [
            'title' => 'Bağlantı testi', 'slug' => 'baglanti-guvenlik-testi', 'page_type' => 'standard',
            'show_in_menu' => '1', 'noindex' => '1',
            'sections' => [[
                'type' => 'cta', 'title' => 'İletişim', 'cta_label' => 'Bize ulaşın', 'cta_url' => 'https://',
                'variant' => 'default', 'is_active' => '1',
            ]],
        ])->assertRedirect('/admin/site-builder/create')->assertSessionHasErrors('sections.0.cta_url');

        $this->assertDatabaseMissing('site_pages', ['slug' => 'baglanti-guvenlik-testi']);
    }

    public function test_site_builder_page_creation_starts_as_draft(): void
    {
        $admin=$this->admin();
        $this->actingAs($admin)->post('/admin/site-builder',['title'=>'Hakkımızda','slug'=>'hakkimizda','page_type'=>'about','show_in_menu'=>'1','noindex'=>'1',
            'sections'=>[['type'=>'hero','title'=>'ORVIONART hakkında','subtitle'=>'','body'=>'','image_asset_id'=>'','image_alt'=>'','cta_label'=>'','cta_url'=>'','variant'=>'default','items_text'=>'','is_active'=>'1']]])
            ->assertRedirect();
        $this->assertDatabaseHas('site_pages',['slug'=>'hakkimizda','status'=>'draft','show_in_menu'=>1]);
        $this->assertDatabaseHas('site_page_revisions',['event'=>'created']);
    }

    public function test_revision_restore_rejects_slug_already_used_by_another_page_without_changing_current_draft(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Güncel sayfa', 'slug' => 'guncel-sayfa', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Güncel içerik', 'is_active' => true]],
            'noindex' => true, 'version' => 3,
        ]);
        SitePage::create([
            'title' => 'Diğer sayfa', 'slug' => 'eski-adres-artik-kullaniliyor', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [], 'noindex' => true, 'version' => 1,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id, 'version' => 1, 'event' => 'saved',
            'snapshot' => [
                'title' => 'Eski başlık', 'slug' => 'eski-adres-artik-kullaniliyor', 'page_type' => 'standard',
                'show_in_menu' => false, 'sections' => [['type' => 'hero', 'title' => 'Eski içerik', 'is_active' => true]],
                'seo_title' => null, 'meta_description' => null, 'canonical_url' => null, 'noindex' => true,
                'social_image_asset_id' => null,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$page->id.'/revisions/'.$revision->id.'/restore')
            ->assertSessionHasErrors('revision');

        $this->assertDatabaseHas('site_pages', [
            'id' => $page->id, 'slug' => 'guncel-sayfa', 'title' => 'Güncel sayfa', 'version' => 3,
        ]);
    }

    public function test_revision_restore_rejects_unsafe_slider_url_without_changing_draft(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Güvenli taslak', 'slug' => 'guvenli-taslak', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Mevcut içerik']],
            'noindex' => true, 'version' => 3,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id, 'version' => 1, 'event' => 'saved',
            'snapshot' => [
                'title' => 'Eski başlık', 'slug' => 'eski-guvenli-taslak', 'page_type' => 'standard',
                'show_in_menu' => false, 'sections' => [[
                    'type' => 'hero_slider', 'title' => 'Slider', 'subtitle' => '', 'body' => '',
                    'image_asset_id' => null, 'image_alt' => '', 'cta_label' => '', 'cta_url' => '',
                    'variant' => 'default', 'is_active' => true, 'items' => [[
                        'title' => 'Güvensiz bağlantı', 'text' => '', 'image_asset_id' => null,
                        'cta_label' => 'Tıkla', 'cta_url' => 'javascript:alert(1)',
                    ]],
                ]],
                'seo_title' => null, 'meta_description' => null, 'canonical_url' => null,
                'noindex' => true, 'social_image_asset_id' => null,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$page->id.'/revisions/'.$revision->id.'/restore')
            ->assertSessionHasErrors('revision');

        $this->assertDatabaseHas('site_pages', [
            'id' => $page->id, 'slug' => 'guvenli-taslak', 'title' => 'Güvenli taslak', 'version' => 3,
        ]);
    }

    public function test_revision_restore_rejects_invalid_canonical_url_without_changing_draft(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Kanonik taslak', 'slug' => 'kanonik-taslak', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Mevcut içerik']],
            'noindex' => true, 'version' => 2,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id, 'version' => 1, 'event' => 'saved',
            'snapshot' => [
                'title' => 'Eski başlık', 'slug' => 'eski-kanonik-taslak', 'page_type' => 'standard',
                'show_in_menu' => false, 'sections' => [['type' => 'hero', 'title' => 'Eski içerik', 'is_active' => true]],
                'seo_title' => null, 'meta_description' => null, 'canonical_url' => 'javascript:alert(1)',
                'noindex' => true, 'social_image_asset_id' => null,
            ],
            'created_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$page->id.'/revisions/'.$revision->id.'/restore')
            ->assertSessionHasErrors('revision');

        $this->assertDatabaseHas('site_pages', [
            'id' => $page->id, 'slug' => 'kanonik-taslak', 'title' => 'Kanonik taslak', 'version' => 2,
        ]);
    }

    public function test_revision_restore_changes_draft_only_and_keeps_live_snapshot_unchanged(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Güncel başlık', 'slug' => 'guncel-adres', 'page_type' => 'standard',
            'status' => 'published', 'sections' => [['type' => 'hero', 'title' => 'Güncel taslak', 'is_active' => true]],
            'published_sections' => [['type' => 'hero', 'title' => 'Canlı içerik', 'is_active' => true]],
            'published_title' => 'Canlı başlık', 'published_slug' => 'canli-adres', 'published_page_type' => 'standard',
            'published_show_in_menu' => false, 'published_at' => now()->subMinute(), 'published_noindex' => true,
            'noindex' => true, 'version' => 4,
        ]);
        $revision = SitePageRevision::create([
            'site_page_id' => $page->id, 'version' => 2, 'event' => 'saved',
            'snapshot' => [
                'title' => 'Eski taslak başlığı', 'slug' => 'eski-taslak-adres', 'page_type' => 'standard',
                'show_in_menu' => false, 'sections' => [['type' => 'hero', 'title' => 'Eski taslak içeriği', 'is_active' => true]],
                'seo_title' => null, 'meta_description' => null, 'canonical_url' => null, 'noindex' => true,
                'social_image_asset_id' => null,
            ],
            'created_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post('/admin/site-builder/'.$page->id.'/revisions/'.$revision->id.'/restore')
            ->assertRedirect('/admin/site-builder/'.$page->id.'/edit');

        $this->assertDatabaseHas('site_pages', [
            'id' => $page->id, 'slug' => 'eski-taslak-adres', 'title' => 'Eski taslak başlığı',
            'published_slug' => 'canli-adres', 'published_title' => 'Canlı başlık', 'version' => 5,
        ]);
        $this->get('/s/canli-adres')->assertOk()->assertSee('Canlı içerik')->assertHeader('Cache-Control','no-store, private');
        $this->assertDatabaseHas('site_page_revisions', ['site_page_id' => $page->id, 'event' => 'restored', 'version' => 5]);
    }


    public function test_site_builder_media_upload_rolls_back_asset_when_audit_write_fails(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Medya sayfası', 'slug' => 'medya-sayfasi', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [], 'version' => 1,
        ]);

        Event::listen('eloquent.creating: '.AuditLog::class, static function (AuditLog $log): void {
            if ($log->action === 'site_page.media_uploaded') {
                throw new \RuntimeException('Injected audit persistence failure for regression test.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->post(route('admin.site-builder.media.upload', $page), [
                'file' => UploadedFile::fake()->image('site-image.jpg'),
                'title' => 'Test görseli',
                'alt_text' => 'Test görseli',
            ]);
            $this->fail('Denetim kaydı hatası isteği başarısız kılmalıydı.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected audit persistence failure for regression test.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertSame(0, MediaAsset::where('site_page_id', $page->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles('orvionart-site-media/'.$page->id));
    }

    public function test_site_builder_media_upload_persists_asset_and_audit_together(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Medya sayfası', 'slug' => 'medya-sayfasi', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [], 'version' => 1,
        ]);

        $this->actingAs($admin)->post(route('admin.site-builder.media.upload', $page), [
            'file' => UploadedFile::fake()->image('site-image.jpg'),
            'title' => 'Test görseli',
            'alt_text' => 'Test görseli',
        ])->assertRedirect();

        $asset = MediaAsset::where('site_page_id', $page->id)->firstOrFail();
        $this->assertSame('local', $asset->disk);
        $this->assertTrue(Storage::disk('local')->exists($asset->path));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'site_page.media_uploaded',
            'subject_id' => (string) $asset->id,
        ]);
    }


    public function test_site_theme_controls_are_saved_and_applied_from_allowlisted_settings(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART Test', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb',
            'background_color' => '#f1f2f3', 'surface_color' => '#fafafa',
            'font_family' => 'system', 'heading_font_family' => 'serif', 'body_font_family' => 'sans-serif',
            'button_style' => 'square', 'header_behavior' => 'sticky', 'footer_layout' => 'stacked',
            'content_width' => 1200, 'default_seo_title' => 'ORVIONART',
            'default_meta_description' => 'Test açıklaması', 'contact_email' => '', 'contact_phone' => '',
            'maintenance_mode' => '0', 'maintenance_message' => '', 'footer_text' => '© ORVIONART',
        ])->assertRedirect();

        $settings = SiteSetting::query()->where('setting_key', 'general')->firstOrFail()->setting_value;
        $this->assertSame('#f1f2f3', $settings['background_color']);
        $this->assertSame('#fafafa', $settings['surface_color']);
        $this->assertSame('serif', $settings['heading_font_family']);
        $this->assertSame('sans-serif', $settings['body_font_family']);
        $this->assertSame('square', $settings['button_style']);
        $this->assertSame('sticky', $settings['header_behavior']);
        $this->assertSame('stacked', $settings['footer_layout']);

        SitePage::create([
            'title' => 'ORVIONART', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [], 'published_sections' => [['type' => 'hero', 'title' => 'Tema testi', 'is_active' => true]],
            'published_title' => 'ORVIONART', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('--page-bg:#f1f2f3', false)
            ->assertSee('--surface:#fafafa', false)
            ->assertSee('--heading-font:Georgia,serif', false)
            ->assertSee('--font:Arial,sans-serif', false)
            ->assertSee('--button-radius:4px', false)
            ->assertSee('header.site-header{position:sticky;top:0;z-index:20}', false)
            ->assertSee('class="footer-inner layout-stacked"', false);
    }

    public function test_site_brand_logo_and_favicon_uploads_are_private_and_served_through_safe_routes(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
            'logo_file' => UploadedFile::fake()->image('logo.png', 240, 80),
            'favicon_file' => UploadedFile::fake()->image('favicon.png', 64, 64),
        ])->assertRedirect();

        $settings = SiteSetting::where('setting_key', 'general')->firstOrFail()->setting_value;
        $this->assertStringStartsWith('orvionart-brand/logo_path-', $settings['logo_path']);
        $this->assertStringStartsWith('orvionart-brand/favicon_path-', $settings['favicon_path']);
        Storage::disk('local')->assertExists($settings['logo_path']);
        Storage::disk('local')->assertExists($settings['favicon_path']);

        SitePage::create([
            'title' => 'ORVIONART', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [], 'published_sections' => [['type' => 'hero', 'title' => 'Marka testi', 'is_active' => true]],
            'published_title' => 'ORVIONART', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);
        $this->get('/')->assertOk()
            ->assertSee('/brand/logo', false)
            ->assertSee('alt="ORVIONART"', false)
            ->assertSee('rel="icon"', false);
        $this->get(route('brand.asset', 'logo'))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('brand.asset', 'favicon'))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/brand/unknown')->assertNotFound();
    }

    public function test_global_social_share_image_is_private_and_used_as_public_page_fallback(): void
    {
        Storage::fake('local');
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => 'Genel açıklama',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
            'social_image_file' => UploadedFile::fake()->image('share.png', 1200, 630),
        ])->assertRedirect();

        $settings = SiteSetting::where('setting_key', 'general')->firstOrFail()->setting_value;
        $this->assertMatchesRegularExpression('~^orvionart-brand/social_image_path-[A-Za-z0-9]{40}\.png$~', $settings['social_image_path']);
        Storage::disk('local')->assertExists($settings['social_image_path']);

        SitePage::create([
            'title' => 'Paylaşılabilir ana sayfa', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [], 'published_sections' => [['type' => 'hero', 'title' => 'Sosyal paylaşım testi', 'is_active' => true]],
            'published_title' => 'Paylaşılabilir ana sayfa', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('property="og:image" content="'.route('brand.asset', 'social-image').'"', false)
            ->assertSee('name="twitter:image" content="'.route('brand.asset', 'social-image').'"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false);
        $this->get(route('brand.asset', 'social-image'))->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_global_social_share_image_rejects_svg_uploads(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
            'social_image_file' => UploadedFile::fake()->create('share.svg', 2, 'image/svg+xml'),
        ])->assertSessionHasErrors('social_image_file');
        $this->assertDatabaseMissing('site_settings', ['setting_key' => 'general']);
    }

    public function test_replacing_or_removing_brand_assets_deletes_only_superseded_private_files(): void
    {
        Storage::fake('local');
        $oldLogo = 'orvionart-brand/logo_path-'.str_repeat('A', 40).'.png';
        $oldFavicon = 'orvionart-brand/favicon_path-'.str_repeat('B', 40).'.webp';
        $oldSocialImage = 'orvionart-brand/social_image_path-'.str_repeat('C', 40).'.png';
        Storage::disk('local')->put($oldLogo, 'old-logo');
        Storage::disk('local')->put($oldFavicon, 'old-favicon');
        Storage::disk('local')->put($oldSocialImage, 'old-social-image');
        SiteSetting::create(['setting_key' => 'general', 'setting_value' => [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART', 'logo_path' => $oldLogo, 'favicon_path' => $oldFavicon, 'social_image_path' => $oldSocialImage,
        ]]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART', 'remove_logo' => '1', 'remove_social_image' => '1',
        ])->assertRedirect();

        $settings = SiteSetting::where('setting_key', 'general')->firstOrFail()->setting_value;
        $this->assertNull($settings['logo_path']);
        $this->assertSame($oldFavicon, $settings['favicon_path']);
        $this->assertNull($settings['social_image_path']);
        Storage::disk('local')->assertMissing($oldLogo);
        Storage::disk('local')->assertExists($oldFavicon);
        Storage::disk('local')->assertMissing($oldSocialImage);
    }

    public function test_site_brand_upload_rejects_svg_and_other_unapproved_content_types(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
            'logo_file' => UploadedFile::fake()->create('logo.svg', 2, 'image/svg+xml'),
        ])->assertSessionHasErrors('logo_file');
        $this->assertDatabaseMissing('site_settings', ['setting_key' => 'general']);
    }

    public function test_public_theme_render_falls_back_for_corrupt_legacy_color_values(): void
    {
        SiteSetting::create(['setting_key' => 'general', 'setting_value' => [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#112233; background:url(javascript:alert(1))',
            'accent_color' => 'red;position:fixed',
            'background_color' => '#ffffff;--inject:1', 'surface_color' => 'url(javascript:alert(1))',
        ]]);
        SitePage::create([
            'title' => 'ORVIONART', 'slug' => 'home', 'page_type' => 'home', 'status' => 'published',
            'sections' => [], 'published_sections' => [['type' => 'hero', 'title' => 'Güvenli tema', 'is_active' => true]],
            'published_title' => 'ORVIONART', 'published_slug' => 'home', 'published_page_type' => 'home',
            'published_show_in_menu' => false, 'published_at' => now(), 'published_noindex' => true, 'version' => 1,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('--ink:#10202c', false)
            ->assertSee('--accent:#58d3cb', false)
            ->assertSee('--page-bg:#f7f9fb', false)
            ->assertSee('--surface:#ffffff', false)
            ->assertDontSee('url(javascript:alert(1))', false);
    }

    public function test_legacy_site_settings_update_preserves_new_theme_controls(): void
    {
        $admin = $this->admin();
        SiteSetting::create(['setting_key' => 'general', 'setting_value' => [
            'site_name' => 'Eski ad', 'brand_text' => 'ORVIONART', 'button_style' => 'square',
            'header_behavior' => 'sticky', 'footer_layout' => 'stacked', 'background_color' => '#eeeeee',
        ]]);

        // Simulate an older form/client that does not submit the newer controls.
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'Yeni ad', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb', 'font_family' => 'system',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
        ])->assertRedirect();

        $settings = SiteSetting::query()->where('setting_key', 'general')->firstOrFail()->setting_value;
        $this->assertSame('Yeni ad', $settings['site_name']);
        $this->assertSame('square', $settings['button_style']);
        $this->assertSame('sticky', $settings['header_behavior']);
        $this->assertSame('stacked', $settings['footer_layout']);
        $this->assertSame('#eeeeee', $settings['background_color']);
    }

    public function test_site_theme_settings_reject_unapproved_css_values(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
            'site_name' => 'ORVIONART', 'brand_text' => 'ORVIONART',
            'primary_color' => '#10202c', 'accent_color' => '#58d3cb',
            'background_color' => '#f7f9fb', 'surface_color' => '#ffffff',
            'font_family' => 'system', 'heading_font_family' => 'system', 'body_font_family' => 'system',
            'button_style' => 'url(javascript:alert(1))', 'header_behavior' => 'sticky', 'footer_layout' => 'split',
            'content_width' => 1160, 'default_seo_title' => '', 'default_meta_description' => '',
            'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
            'maintenance_message' => '', 'footer_text' => '© ORVIONART',
        ])->assertSessionHasErrors('button_style');

        $this->assertDatabaseMissing('site_settings', ['setting_key' => 'general']);
    }

    public function test_site_settings_update_and_audit_are_atomic_when_audit_write_fails(): void
    {
        $admin = $this->admin();
        SiteSetting::create(['setting_key' => 'general', 'setting_value' => [
            'site_name' => 'Önceki ad', 'brand_text' => 'Önceki marka',
        ]]);

        Event::listen('eloquent.creating: '.AuditLog::class, static function (AuditLog $log): void {
            if ($log->action === 'site_settings.updated') {
                throw new \RuntimeException('Injected settings audit failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->put(route('admin.site-builder.settings.save'), [
                'site_name' => 'Yeni ad', 'brand_text' => 'Yeni marka',
                'primary_color' => '#112233', 'accent_color' => '#445566', 'font_family' => 'system',
                'content_width' => 1200, 'default_seo_title' => '', 'default_meta_description' => '',
                'contact_email' => '', 'contact_phone' => '', 'maintenance_mode' => '0',
                'maintenance_message' => '', 'footer_text' => '',
            ]);
            $this->fail('Denetim kaydı hatası isteği başarısız kılmalıydı.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected settings audit failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertSame('Önceki ad', SiteSetting::where('setting_key', 'general')->firstOrFail()->setting_value['site_name']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site_settings.updated']);
    }

    public function test_navigation_item_can_be_edited_and_records_before_and_after_audit_state(): void
    {
        $admin = $this->admin();
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'Eski iletişim', 'external_url' => 'https://orvionart.example/eski',
            'sort_order' => 30, 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.site-builder.navigation.update', $item), [
            'label' => 'İletişim', 'location' => 'header', 'site_page_id' => '',
            'external_url' => 'https://orvionart.example/iletisim', 'sort_order' => 10,
        ])->assertRedirect()->assertSessionHas('success', 'Menü bağlantısı güncellendi.');

        $this->assertDatabaseHas('site_navigation_items', [
            'id' => $item->id, 'label' => 'İletişim', 'location' => 'header',
            'external_url' => 'https://orvionart.example/iletisim', 'site_page_id' => null,
            'sort_order' => 10, 'is_active' => true,
        ]);
        $log = AuditLog::where('action', 'site_navigation.updated')->latest('id')->firstOrFail();
        $this->assertSame((string) $item->id, (string) $log->subject_id);
        $this->assertSame('Eski iletişim', $log->metadata['before']['label']);
        $this->assertSame('İletişim', $log->metadata['after']['label']);
        $this->assertSame('header', $log->metadata['after']['location']);
    }

    public function test_navigation_item_edit_rejects_unpublished_page_without_changing_existing_link(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Taslak hedef', 'slug' => 'taslak-hedef-duzenleme', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Taslak']], 'version' => 1,
        ]);
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'Mevcut bağlantı', 'external_url' => 'https://orvionart.example/mevcut',
            'sort_order' => 20, 'is_active' => true,
        ]);

        $this->actingAs($admin)->from(route('admin.site-builder.navigation'))
            ->patch(route('admin.site-builder.navigation.update', $item), [
                'label' => 'Değişmemeli', 'location' => 'header', 'site_page_id' => $page->id,
                'external_url' => '', 'sort_order' => 0,
            ])->assertRedirect(route('admin.site-builder.navigation'))->assertSessionHasErrors('navigation');

        $this->assertDatabaseHas('site_navigation_items', [
            'id' => $item->id, 'label' => 'Mevcut bağlantı', 'location' => 'footer',
            'external_url' => 'https://orvionart.example/mevcut', 'is_active' => true,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site_navigation.updated', 'subject_id' => (string) $item->id]);
    }

    public function test_navigation_item_edit_rolls_back_when_audit_write_fails(): void
    {
        $admin = $this->admin();
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'Eski bağlantı', 'external_url' => 'https://orvionart.example/eski',
            'sort_order' => 20, 'is_active' => true,
        ]);
        Event::listen('eloquent.creating: '.AuditLog::class, static function (AuditLog $log): void {
            if ($log->action === 'site_navigation.updated') {
                throw new \RuntimeException('Injected navigation update audit failure.');
            }
        });
        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->patch(route('admin.site-builder.navigation.update', $item), [
                'label' => 'Yeni bağlantı', 'location' => 'footer', 'site_page_id' => '',
                'external_url' => 'https://orvionart.example/yeni', 'sort_order' => 5,
            ]);
            $this->fail('Denetim kaydı hatası isteği başarısız kılmalıydı.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected navigation update audit failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertDatabaseHas('site_navigation_items', [
            'id' => $item->id, 'label' => 'Eski bağlantı', 'external_url' => 'https://orvionart.example/eski', 'sort_order' => 20,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site_navigation.updated', 'subject_id' => (string) $item->id]);
    }

    public function test_navigation_toggle_uses_locked_current_state_and_audits_the_committed_value(): void
    {
        $admin = $this->admin();
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'İletişim', 'external_url' => 'https://orvionart.example/iletisim',
            'sort_order' => 10, 'is_active' => true,
        ]);

        $this->actingAs($admin)->patch(route('admin.site-builder.navigation.toggle', $item))->assertRedirect();

        $this->assertDatabaseHas('site_navigation_items', ['id' => $item->id, 'is_active' => false]);
        $log = AuditLog::where('action', 'site_navigation.toggled')->latest('id')->firstOrFail();
        $this->assertSame((string) $item->id, (string) $log->subject_id);
        $this->assertFalse((bool) $log->metadata['is_active']);
    }

    public function test_navigation_toggle_cannot_activate_a_link_to_an_unpublished_page(): void
    {
        $admin = $this->admin();
        $page = SitePage::create([
            'title' => 'Taslak hedef', 'slug' => 'taslak-hedef', 'page_type' => 'standard',
            'status' => 'draft', 'sections' => [['type' => 'hero', 'title' => 'Taslak']],
            'version' => 1,
        ]);
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'Taslak bağlantı', 'site_page_id' => $page->id,
            'sort_order' => 10, 'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.site-builder.navigation'))
            ->patch(route('admin.site-builder.navigation.toggle', $item))
            ->assertRedirect(route('admin.site-builder.navigation'))
            ->assertSessionHasErrors('navigation');

        $this->assertDatabaseHas('site_navigation_items', ['id' => $item->id, 'is_active' => false]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'site_navigation.toggled',
            'subject_id' => (string) $item->id,
        ]);
    }

    public function test_navigation_delete_rolls_back_when_audit_write_fails(): void
    {
        $admin = $this->admin();
        $item = SiteNavigationItem::create([
            'location' => 'footer', 'label' => 'Silinmemeli', 'external_url' => 'https://orvionart.example/kontakt',
            'sort_order' => 10, 'is_active' => true,
        ]);
        Event::listen('eloquent.creating: '.AuditLog::class, static function (AuditLog $log): void {
            if ($log->action === 'site_navigation.deleted') {
                throw new \RuntimeException('Injected navigation audit failure.');
            }
        });

        $this->withoutExceptionHandling();
        try {
            $this->actingAs($admin)->delete(route('admin.site-builder.navigation.delete', $item));
            $this->fail('Denetim kaydı hatası isteği başarısız kılmalıydı.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Injected navigation audit failure.', $exception->getMessage());
        } finally {
            Event::forget('eloquent.creating: '.AuditLog::class);
        }

        $this->assertDatabaseHas('site_navigation_items', ['id' => $item->id, 'label' => 'Silinmemeli']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'site_navigation.deleted']);
    }

}
