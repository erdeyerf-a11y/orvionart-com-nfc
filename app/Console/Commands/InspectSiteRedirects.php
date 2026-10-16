<?php

namespace App\Console\Commands;

use App\Models\SitePage;
use App\Models\SitePageRedirect;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectSiteRedirects extends Command
{
    protected $signature = 'orvionart:inspect-site-redirects {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Site Builder eski adres yönlendirmelerini salt okunur denetler';

    public function handle(): int
    {
        $now = now();
        $findings = [
            'invalid_old_slug' => [],
            'invalid_new_slug' => [],
            'self_redirect' => [],
            'unsupported_status_code' => [],
            'target_unavailable' => [],
            'source_shadowed_by_page' => [],
            'case_insensitive_duplicate_source' => [],
        ];

        $redirects = SitePageRedirect::query()->orderBy('id')->get();
        foreach ($redirects as $redirect) {
            $id = (int) $redirect->id;
            $oldSlug = (string) $redirect->old_slug;
            $newSlug = (string) $redirect->new_slug;

            if (!$this->isValidSlug($oldSlug)) {
                $findings['invalid_old_slug'][] = $id;
            }
            if (!$this->isValidSlug($newSlug)) {
                $findings['invalid_new_slug'][] = $id;
            }
            if ($oldSlug !== '' && mb_strtolower($oldSlug) === mb_strtolower($newSlug)) {
                $findings['self_redirect'][] = $id;
            }
            if (!in_array((int) $redirect->status_code, [301, 302], true)) {
                $findings['unsupported_status_code'][] = $id;
            }

            $target = SitePage::query()
                ->where('published_slug', $newSlug)
                ->where('status', 'published')
                ->whereNotNull('published_sections')
                ->whereNotNull('published_at')
                ->where('published_at', '<=', $now)
                ->first();
            if (!$target || !$target->isPubliclyAvailable()) {
                $findings['target_unavailable'][] = $id;
            }

            // SitePageController deliberately refuses redirects when a page's editable slug
            // still claims the old address; such redirect rows exist but cannot be followed.
            if (SitePage::query()->where('slug', $oldSlug)->exists()) {
                $findings['source_shadowed_by_page'][] = $id;
            }
        }

        // A case-sensitive database index may still permit source slugs that differ only by case.
        $duplicateGroups = DB::table('site_page_redirects')
            ->selectRaw('LOWER(old_slug) AS normalized_slug, COUNT(*) AS aggregate_count')
            ->groupByRaw('LOWER(old_slug)')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        foreach ($duplicateGroups as $group) {
            $ids = SitePageRedirect::query()
                ->whereRaw('LOWER(old_slug) = ?', [$group->normalized_slug])
                ->orderBy('id')
                ->limit(20)
                ->pluck('id');
            foreach ($ids as $id) {
                $findings['case_insensitive_duplicate_source'][] = (int) $id;
            }
        }

        foreach ($findings as $key => $ids) {
            $findings[$key] = array_values(array_unique($ids));
        }

        $messages = [
            'invalid_old_slug' => 'Eski adres biçimi geçersiz.',
            'invalid_new_slug' => 'Yeni hedef adres biçimi geçersiz.',
            'self_redirect' => 'Yönlendirme kendi adresine dönüyor.',
            'unsupported_status_code' => 'Yönlendirme durum kodu 301 veya 302 değil.',
            'target_unavailable' => 'Yönlendirme hedefi şu anda herkese açık bir sayfa değil.',
            'source_shadowed_by_page' => 'Eski adres, sayfanın düzenlenebilir slug alanıyla çakıştığı için yönlendirme çalışmayabilir.',
            'case_insensitive_duplicate_source' => 'Büyük/küçük harf farkı dışında aynı eski adresi kullanan yönlendirmeler var.',
        ];

        $warnings = [];
        foreach ($findings as $code => $ids) {
            if ($ids !== []) {
                $warnings[] = [
                    'code' => $code,
                    'message' => $messages[$code],
                    'count' => count($ids),
                    'sample_ids' => array_slice($ids, 0, 20),
                ];
            }
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => $now->toIso8601String(),
            'read_only' => true,
            'counts' => [
                'redirects_total' => $redirects->count(),
                'redirects_with_findings' => count(array_unique(array_merge(...array_values($findings)))),
                'finding_categories' => count($warnings),
            ],
            'findings' => $warnings,
            'limitations' => [
                'Bu komut yönlendirme kaydı oluşturmaz, değiştirmez veya silmez.',
                'Yönlendirme hedeflerinin erişilebilirliğini mevcut veritabanı kayıtlarına göre denetler; gerçek HTTP/tarayıcı testi değildir.',
                'Büyük/küçük harf karşılaştırması SQL LOWER() davranışına bağlıdır; üretim veritabanında ayrıca kabul testi yapılmalıdır.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART Site Builder yönlendirme denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('Yönlendirme: '.$report['counts']['redirects_total'].' toplam');
            foreach ($warnings as $warning) {
                $this->warn($warning['code'].': '.$warning['count'].' kayıt — '.$warning['message'].' Örnek ID: '.implode(', ', $warning['sample_ids']));
            }
            if ($warnings === []) {
                $this->info('Uyarı bulunmadı. Bu rapor son kabul testi değildir.');
            }
            $this->line('Salt okunur: hiçbir kayıt değiştirilmedi.');
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }

    private function isValidSlug(string $slug): bool
    {
        return $slug !== '' && strlen($slug) <= 180 && preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $slug) === 1;
    }
}
