<?php

namespace App\Console\Commands;

use App\Models\SiteNavigationItem;
use App\Models\SitePage;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class InspectSiteBuilder extends Command
{
    protected $signature = 'orvionart:inspect-site-builder {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Site Builder yayın anlık görüntülerini ve menü bağlantılarını salt okunur biçimde denetler';

    public function handle(): int
    {
        $now = now();
        $warnings = [];
        $incompletePublishedPageIds = [];
        $duplicatePublishedSlugs = [];
        $invalidNavigationIds = [];
        $headerMenuPolicyViolations = [];
        $futurePublicationIds = [];

        $publishedPages = SitePage::query()->where('status', 'published')->get();
        foreach ($publishedPages as $page) {
            if ($page->published_at === null
                || $page->published_slug === null
                || trim((string) $page->published_slug) === ''
                || !is_array($page->published_sections)) {
                $incompletePublishedPageIds[] = (int) $page->id;
            }

            if ($page->published_at !== null && $page->published_at->gt($now)) {
                $futurePublicationIds[] = (int) $page->id;
            }
        }

        $slugGroups = $publishedPages
            ->filter(fn (SitePage $page) => $page->published_slug !== null && trim((string) $page->published_slug) !== '')
            ->groupBy(fn (SitePage $page) => Str::lower((string) $page->published_slug));
        foreach ($slugGroups as $slug => $pages) {
            if ($pages->count() > 1) {
                $duplicatePublishedSlugs[] = [
                    'slug' => $slug,
                    'page_ids' => $pages->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                ];
            }
        }

        $navigationItems = SiteNavigationItem::query()->with('page')->where('is_active', true)->get();
        foreach ($navigationItems as $item) {
            $hasPage = $item->site_page_id !== null;
            $hasExternalUrl = trim((string) $item->external_url) !== '';

            if ($hasPage === $hasExternalUrl) {
                $invalidNavigationIds[] = (int) $item->id;
                continue;
            }

            if ($hasExternalUrl) {
                $externalUrl = trim((string) $item->external_url);
                $externalScheme = strtolower((string) parse_url($externalUrl, PHP_URL_SCHEME));
                $externalHost = (string) parse_url($externalUrl, PHP_URL_HOST);
                if (filter_var($externalUrl, FILTER_VALIDATE_URL) === false || $externalScheme !== 'https' || $externalHost === '') {
                    $invalidNavigationIds[] = (int) $item->id;
                    continue;
                }
            }

            if ($hasPage) {
                $page = $item->page;
                if (!$page || !$page->isPubliclyAvailable()) {
                    $invalidNavigationIds[] = (int) $item->id;
                    continue;
                }

                if ($item->location === 'header' && !$page->published_show_in_menu) {
                    $headerMenuPolicyViolations[] = (int) $item->id;
                }
            }
        }

        if ($incompletePublishedPageIds !== []) {
            $warnings[] = ['code' => 'published_page_snapshot_incomplete', 'message' => 'Yayında işaretli bazı sayfalarda yayın tarihi, yayın adresi veya yayın içerik anlık görüntüsü eksik.', 'page_ids' => $incompletePublishedPageIds];
        }
        if ($duplicatePublishedSlugs !== []) {
            $warnings[] = ['code' => 'duplicate_published_slug', 'message' => 'Birden fazla sayfa aynı yayın adresini kullanıyor.', 'duplicates' => $duplicatePublishedSlugs];
        }
        if ($invalidNavigationIds !== []) {
            $warnings[] = ['code' => 'active_navigation_target_invalid', 'message' => 'Etkin menü öğelerinden bazılarının hedefi eksik, geçersiz veya herkese açık değil.', 'navigation_item_ids' => array_values(array_unique($invalidNavigationIds))];
        }
        if ($headerMenuPolicyViolations !== []) {
            $warnings[] = ['code' => 'header_navigation_policy_mismatch', 'message' => 'Bazı üst menü öğeleri, menüde gösterilmesine izin verilmeyen sayfalara bağlı.', 'navigation_item_ids' => $headerMenuPolicyViolations];
        }
        if ($futurePublicationIds !== []) {
            $warnings[] = ['code' => 'published_at_in_future', 'message' => 'Yayın durumu yayımlandı olan bazı sayfaların yayın zamanı gelecekte.', 'page_ids' => $futurePublicationIds];
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => $now->toIso8601String(),
            'read_only' => true,
            'counts' => [
                'pages_total' => SitePage::query()->count(),
                'pages_status_published' => $publishedPages->count(),
                'pages_publicly_available' => $publishedPages->filter(fn (SitePage $page) => $page->isPubliclyAvailable())->count(),
                'published_pages_with_incomplete_snapshot' => count($incompletePublishedPageIds),
                'duplicate_published_slug_groups' => count($duplicatePublishedSlugs),
                'active_navigation_items' => $navigationItems->count(),
                'active_navigation_items_with_invalid_target' => count(array_unique($invalidNavigationIds)),
                'header_navigation_policy_mismatches' => count($headerMenuPolicyViolations),
                'published_pages_with_future_publish_time' => count($futurePublicationIds),
            ],
            'issues' => [
                'published_page_ids_with_incomplete_snapshot' => $incompletePublishedPageIds,
                'duplicate_published_slugs' => $duplicatePublishedSlugs,
                'active_navigation_item_ids_with_invalid_target' => array_values(array_unique($invalidNavigationIds)),
                'header_navigation_item_ids_with_policy_mismatch' => $headerMenuPolicyViolations,
                'published_page_ids_with_future_publish_time' => $futurePublicationIds,
            ],
            'warnings' => $warnings,
            'limitations' => [
                'Bu rapor salt okunurdur; sayfa, menü veya yayın durumunu değiştirmez.',
                'Statik/veri bütünlüğü kontrolü gerçek tarayıcı, erişilebilirlik veya sunucu kabul testinin yerine geçmez.',
                'Medya dosyalarının varlığı ve bölüm içindeki medya referansları ayrıca orvionart:inspect-platform komutuyla kontrol edilir.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART Site Builder denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('Sayfalar: '.$report['counts']['pages_publicly_available'].' herkese açık / '.$report['counts']['pages_total'].' toplam');
            $this->line('Yayın anlık görüntüsü eksik: '.count($incompletePublishedPageIds).'; yinelenen yayın adresi grubu: '.count($duplicatePublishedSlugs));
            $this->line('Etkin menü: '.$navigationItems->count().'; geçersiz hedef: '.count(array_unique($invalidNavigationIds)).'; üst menü kural uyumsuzluğu: '.count($headerMenuPolicyViolations));
            if ($warnings === []) {
                $this->info('Bu kontroller kapsamında uyarı bulunmadı. Bu, son kabul testi değildir.');
            } else {
                foreach ($warnings as $warning) {
                    $this->warn(strtoupper($warning['code']).': '.$warning['message']);
                }
            }
            $this->line('Salt okunur: hiçbir kayıt veya dosya değiştirilmedi.');
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }
}
