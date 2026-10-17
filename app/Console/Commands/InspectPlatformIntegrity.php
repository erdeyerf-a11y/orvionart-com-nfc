<?php

namespace App\Console\Commands;

use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\QrLink;
use App\Models\SitePage;
use App\Models\SitePageRedirect;
use App\Models\SitePageRevision;
use App\Models\UsageRight;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectPlatformIntegrity extends Command
{
    protected $signature = 'orvionart:inspect-platform {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Deneyim, QR/NFC, medya, Site Builder ve kullanım hakkı kayıtlarını salt okunur biçimde denetler';

    private const PUBLIC_MEDIA_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/webm', 'video/ogg',
    ];

    public function handle(): int
    {
        $now = now();
        $warnings = [];
        $missingMediaIds = [];
        $multiOwnerMediaIds = [];
        $unavailableQrIds = [];
        $scheduledQrIds = [];
        $publishedMediaProblems = [];
        $historicalRevisionMediaProblems = [];
        $brokenRedirectIds = [];

        $allMedia = MediaAsset::query()->get();
        foreach ($allMedia as $asset) {
            if ($asset->experience_id !== null && $asset->site_page_id !== null) {
                $multiOwnerMediaIds[] = (int) $asset->id;
            }

            try {
                $exists = Storage::disk((string) $asset->disk)->exists((string) $asset->path);
            } catch (Throwable) {
                $exists = false;
            }

            if (!$exists) {
                $missingMediaIds[] = (int) $asset->id;
            }
        }

        $activeQrLinks = QrLink::query()->where('is_active', true)
            ->with(['experience' => fn ($query) => $query->withTrashed()])
            ->get();
        foreach ($activeQrLinks as $link) {
            $experience = $link->experience;
            if (!$experience || $experience->trashed()) {
                $unavailableQrIds[] = (int) $link->id;
                continue;
            }

            // A QR link attached to a correctly scheduled future experience is
            // intentionally active before the destination becomes public. Keep
            // it visible in the report without treating it as broken integrity.
            if ($experience->status === 'scheduled'
                && $experience->scheduled_at !== null
                && $experience->scheduled_at->gt($now)) {
                $scheduledQrIds[] = (int) $link->id;
                continue;
            }

            if (!$experience->isPubliclyAvailable()) {
                $unavailableQrIds[] = (int) $link->id;
            }
        }

        $publishedExperiences = Experience::query()->publiclyAvailable()->with('mediaAssets')->get();
        foreach ($publishedExperiences as $experience) {
            foreach ($experience->mediaAssets as $asset) {
                $problem = $this->publishedMediaProblem($asset, $experience->id, null);
                if ($problem !== null) {
                    $publishedMediaProblems[] = [
                        'context' => 'experience',
                        'context_id' => (int) $experience->id,
                        'asset_id' => (int) $asset->id,
                        'problem' => $problem,
                    ];
                }
            }
        }

        $publishedPages = SitePage::query()->where('status', 'published')->whereNotNull('published_sections')->get();
        $publicPageCount = 0;
        foreach ($publishedPages as $page) {
            if (!$page->isPubliclyAvailable()) {
                continue;
            }
            $publicPageCount++;
            $assetIds = $this->sectionAssetIds($page->published_sections ?? []);
            if ($page->published_social_image_asset_id) {
                $assetIds[] = (int) $page->published_social_image_asset_id;
            }

            foreach (array_values(array_unique($assetIds)) as $assetId) {
                $asset = $allMedia->firstWhere('id', $assetId);
                if (!$asset) {
                    $publishedMediaProblems[] = [
                        'context' => 'site_page',
                        'context_id' => (int) $page->id,
                        'asset_id' => $assetId,
                        'problem' => 'asset_record_missing',
                    ];
                    continue;
                }

                $problem = $this->publishedMediaProblem($asset, null, $page->id);
                if ($problem !== null) {
                    $publishedMediaProblems[] = [
                        'context' => 'site_page',
                        'context_id' => (int) $page->id,
                        'asset_id' => (int) $asset->id,
                        'problem' => $problem,
                    ];
                }
            }
        }

        // Retained revisions are restorable snapshots, so their media references
        // must remain inspectable even when the current draft/publication no longer
        // references those assets. Chunk the history to bound memory use.
        SitePageRevision::query()
            ->select(['id', 'site_page_id', 'snapshot'])
            ->orderBy('id')
            ->chunkById(100, function ($revisions) use (&$historicalRevisionMediaProblems, $allMedia): void {
                foreach ($revisions as $revision) {
                    $snapshot = is_array($revision->snapshot) ? $revision->snapshot : [];
                    $assetIds = $this->sectionAssetIds(is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : []);
                    $assetIds = array_merge($assetIds, $this->sectionAssetIds(is_array($snapshot['published_sections'] ?? null) ? $snapshot['published_sections'] : []));
                    foreach (['social_image_asset_id', 'published_social_image_asset_id'] as $field) {
                        if (isset($snapshot[$field]) && is_numeric($snapshot[$field]) && (int) $snapshot[$field] > 0) {
                            $assetIds[] = (int) $snapshot[$field];
                        }
                    }

                    foreach (array_values(array_unique($assetIds)) as $assetId) {
                        $asset = $allMedia->firstWhere('id', $assetId);
                        if (!$asset) {
                            $historicalRevisionMediaProblems[] = [
                                'revision_id' => (int) $revision->id,
                                'site_page_id' => (int) $revision->site_page_id,
                                'asset_id' => (int) $assetId,
                                'problem' => 'asset_record_missing',
                            ];
                            continue;
                        }

                        $problem = $this->publishedMediaProblem($asset, null, (int) $revision->site_page_id);
                        if ($problem !== null) {
                            $historicalRevisionMediaProblems[] = [
                                'revision_id' => (int) $revision->id,
                                'site_page_id' => (int) $revision->site_page_id,
                                'asset_id' => (int) $asset->id,
                                'problem' => $problem,
                            ];
                        }
                    }
                }
            });

        $redirects = SitePageRedirect::query()->get();
        foreach ($redirects as $redirect) {
            $targetExists = SitePage::query()
                ->where('status', 'published')
                ->where('published_slug', $redirect->new_slug)
                ->whereNotNull('published_sections')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', $now)
                ->exists();
            if (!$targetExists) {
                $brokenRedirectIds[] = (int) $redirect->id;
            }
        }

        if ($multiOwnerMediaIds !== []) {
            $warnings[] = ['code' => 'media_has_multiple_owners', 'message' => 'Bazı medya kayıtları aynı anda hem deneyime hem Site Builder sayfasına bağlanmış.', 'media_ids' => $multiOwnerMediaIds];
        }
        if ($missingMediaIds !== []) {
            $warnings[] = ['code' => 'media_file_missing', 'message' => 'Bazı medya dosyaları depolamada bulunamadı veya disk okunamadı.', 'media_ids' => $missingMediaIds];
        }
        if ($unavailableQrIds !== []) {
            $warnings[] = ['code' => 'active_qr_target_unavailable', 'message' => 'Etkin QR/NFC bağlantılarının hedefi şu anda herkese açık değil.', 'qr_link_ids' => $unavailableQrIds];
        }
        if ($publishedMediaProblems !== []) {
            $warnings[] = ['code' => 'published_media_reference_invalid', 'message' => 'Yayındaki içeriklerde eksik, yanlış sayfaya bağlı, dosyası bulunmayan veya desteklenmeyen medya referansı var.', 'references' => $publishedMediaProblems];
        }
        if ($brokenRedirectIds !== []) {
            $warnings[] = ['code' => 'site_redirect_target_unavailable', 'message' => 'Bazı Site Builder yönlendirmelerinin hedefi şu anda yayımlanmış değil.', 'redirect_ids' => $brokenRedirectIds];
        }
        if ($historicalRevisionMediaProblems !== []) {
            $warnings[] = ['code' => 'historical_revision_media_reference_invalid', 'message' => 'Saklanan Site Builder geçmiş sürümlerinde eksik, yanlış sayfaya bağlı, dosyası bulunmayan veya desteklenmeyen medya referansı var.', 'references' => $historicalRevisionMediaProblems];
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => $now->toIso8601String(),
            'read_only' => true,
            'scope' => ['experience', 'qr_nfc', 'media', 'site_builder', 'usage_right_registry'],
            'problem_codes' => array_values(array_unique(array_merge(
                array_map(static fn (array $item): string => (string) ($item['problem'] ?? ''), $publishedMediaProblems),
                array_map(static fn (array $item): string => (string) ($item['problem'] ?? ''), $historicalRevisionMediaProblems),
            ))),
            'counts' => [
                'experiences_total' => Experience::withTrashed()->count(),
                'experiences_publicly_available' => $publishedExperiences->count(),
                'qr_links_total' => QrLink::query()->count(),
                'qr_links_active' => $activeQrLinks->count(),
                'qr_links_active_with_unavailable_target' => count($unavailableQrIds),
                'qr_links_waiting_for_scheduled_experience' => count($scheduledQrIds),
                'media_assets_total' => $allMedia->count(),
                'media_assets_with_missing_file' => count($missingMediaIds),
                'media_assets_with_multiple_owners' => count($multiOwnerMediaIds),
                'published_site_pages' => $publicPageCount,
                'published_media_reference_problems' => count($publishedMediaProblems),
                'historical_revision_media_reference_problems' => count($historicalRevisionMediaProblems),
                'site_redirects_total' => $redirects->count(),
                'site_redirects_with_unavailable_target' => count($brokenRedirectIds),
                'usage_right_registry_records' => UsageRight::query()->count(),
                'usage_rights_with_unresolved_subject' => UsageRight::query()->where('subject_type', 'unresolved')->count(),
            ],
            'issues' => [
                'media_ids_with_missing_file' => $missingMediaIds,
                'media_ids_with_multiple_owners' => $multiOwnerMediaIds,
                'active_qr_link_ids_with_unavailable_target' => $unavailableQrIds,
                'active_qr_link_ids_waiting_for_scheduled_experience' => $scheduledQrIds,
                'published_media_reference_problems' => $publishedMediaProblems,
                'historical_revision_media_reference_problems' => $historicalRevisionMediaProblems,
                'redirect_ids_with_unavailable_target' => $brokenRedirectIds,
                'problem_codes' => array_values(array_unique(array_merge(
                    array_map(static fn (array $item): string => (string) ($item['problem'] ?? ''), $publishedMediaProblems),
                    array_map(static fn (array $item): string => (string) ($item['problem'] ?? ''), $historicalRevisionMediaProblems),
                ))),
            ],
            'warnings' => $warnings,
            'limitations' => [
                'Bu rapor salt okunurdur; veri düzeltmez veya dosya silmez.',
                'Bu komut Laravel çalışma zamanı, erişim güvenliği veya son kabul testlerinin yerine geçmez.',
                'Kullanım hakları kayıt defteri henüz gerçek erişim yetkisi uygulamaz.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART bütünleşik platform denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('Deneyimler: '.$report['counts']['experiences_publicly_available'].' herkese açık / '.$report['counts']['experiences_total'].' toplam');
            $this->line('QR/NFC: '.$report['counts']['qr_links_active'].' etkin / '.$report['counts']['qr_links_total'].' toplam; hedefi erişilemeyen: '.count($unavailableQrIds).'; yayın zamanını bekleyen zamanlanmış hedef: '.count($scheduledQrIds));
            $this->line('Medya: '.$report['counts']['media_assets_total'].' toplam; dosyası bulunamayan: '.count($missingMediaIds).'; birden fazla sahibi olan: '.count($multiOwnerMediaIds));
            $this->line('Site Builder: '.$publicPageCount.' yayındaki sayfa; hatalı medya referansı: '.count($publishedMediaProblems).'; geçmiş sürümlerde hatalı medya referansı: '.count($historicalRevisionMediaProblems).'; hedefi erişilemeyen yönlendirme: '.count($brokenRedirectIds));
            $this->line('Kullanım hakkı kayıtları: '.$report['counts']['usage_right_registry_records'].' (erişim yetkisi uygulamaz)');
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

    private function sectionAssetIds(array $sections): array
    {
        $ids = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            if (!empty($section['image_asset_id']) && is_numeric($section['image_asset_id'])) {
                $ids[] = (int) $section['image_asset_id'];
            }
            foreach (($section['items'] ?? []) as $item) {
                if (is_array($item) && !empty($item['image_asset_id']) && is_numeric($item['image_asset_id'])) {
                    $ids[] = (int) $item['image_asset_id'];
                }
            }
        }
        return $ids;
    }

    private function publishedMediaProblem(MediaAsset $asset, ?int $experienceId, ?int $sitePageId): ?string
    {
        if (!in_array($asset->mime_type, self::PUBLIC_MEDIA_MIME_TYPES, true)) {
            return 'unsupported_mime_type';
        }
        if ($experienceId !== null && (int) $asset->experience_id !== $experienceId) {
            return 'asset_owned_by_another_experience';
        }
        if ($sitePageId !== null && (int) $asset->site_page_id !== $sitePageId) {
            return 'asset_owned_by_another_site_page';
        }
        try {
            if (!Storage::disk((string) $asset->disk)->exists((string) $asset->path)) {
                return 'file_missing_or_disk_unavailable';
            }
        } catch (Throwable) {
            return 'file_missing_or_disk_unavailable';
        }
        return null;
    }
}
