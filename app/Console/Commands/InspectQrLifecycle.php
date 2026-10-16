<?php

namespace App\Console\Commands;

use App\Models\QrLink;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InspectQrLifecycle extends Command
{
    protected $signature = 'orvionart:inspect-qr-lifecycle {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'QR/NFC token, hedef erişimi ve tarama sayaçlarını salt okunur denetler';

    public function handle(): int
    {
        $now = now();
        $invalidTokenIds = [];
        $activeUnavailableTargetIds = [];
        $scanCountWithoutTimestampIds = [];
        $timestampWithoutScansIds = [];
        $futureScanTimestampIds = [];
        $scanBeforeCreationIds = [];

        // Inspect in chunks so the audit does not load the whole QR table into memory.
        QrLink::query()->with(['experience' => fn ($query) => $query->withTrashed()])
            ->orderBy('id')->chunkById(250, function ($links) use (
                &$invalidTokenIds,
                &$activeUnavailableTargetIds,
                &$scanCountWithoutTimestampIds,
                &$timestampWithoutScansIds,
                &$futureScanTimestampIds,
                &$scanBeforeCreationIds,
                $now
            ): void {
                foreach ($links as $link) {
                    $id = (int) $link->id;
                    $token = (string) $link->token;
                    if (strlen($token) !== 40 || !preg_match('/\A[A-Za-z0-9]{40}\z/', $token)) {
                        $invalidTokenIds[] = $id;
                    }

                    $experience = $link->experience;
                    if ($link->is_active && (!$experience || $experience->trashed() || !$experience->isPubliclyAvailable())) {
                        $activeUnavailableTargetIds[] = $id;
                    }

                    $scanCount = (int) $link->scan_count;
                    if ($scanCount > 0 && $link->last_scanned_at === null) {
                        $scanCountWithoutTimestampIds[] = $id;
                    }
                    if ($scanCount === 0 && $link->last_scanned_at !== null) {
                        $timestampWithoutScansIds[] = $id;
                    }
                    if ($link->last_scanned_at !== null && $link->last_scanned_at->gt($now)) {
                        $futureScanTimestampIds[] = $id;
                    }
                    if ($link->last_scanned_at !== null && $link->created_at !== null && $link->last_scanned_at->lt($link->created_at)) {
                        $scanBeforeCreationIds[] = $id;
                    }
                }
            });

        // A unique index should prevent duplicates, but this check also detects legacy/imported corruption.
        $duplicateTokenGroups = DB::table('qr_links')
            ->select('token')
            ->groupBy('token')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $duplicateTokenIds = [];
        foreach ($duplicateTokenGroups as $group) {
            $ids = DB::table('qr_links')->where('token', $group->token)->orderBy('id')->limit(20)->pluck('id');
            foreach ($ids as $id) {
                $duplicateTokenIds[] = (int) $id;
            }
        }
        $duplicateTokenIds = array_values(array_unique($duplicateTokenIds));

        $checks = [
            'invalid_token_format' => ['ids' => $invalidTokenIds, 'message' => 'QR/NFC token biçimi beklenen 40 karakterli alfasayısal biçime uymuyor.'],
            'duplicate_token' => ['ids' => $duplicateTokenIds, 'message' => 'Bir token birden fazla QR/NFC kaydında bulunuyor. Rapor token değerini içermez.'],
            'active_target_unavailable' => ['ids' => $activeUnavailableTargetIds, 'message' => 'Etkin QR/NFC bağlantısının hedef deneyimi şu anda herkese açık değil.'],
            'scan_count_without_timestamp' => ['ids' => $scanCountWithoutTimestampIds, 'message' => 'Tarama sayısı pozitif ancak son tarama zamanı boş.'],
            'timestamp_without_scans' => ['ids' => $timestampWithoutScansIds, 'message' => 'Son tarama zamanı var ancak tarama sayacı sıfır.'],
            'future_scan_timestamp' => ['ids' => $futureScanTimestampIds, 'message' => 'Son tarama zamanı gelecekte.'],
            'scan_before_creation' => ['ids' => $scanBeforeCreationIds, 'message' => 'Son tarama zamanı QR/NFC kaydının oluşturulma zamanından önce.'],
        ];

        $warnings = [];
        foreach ($checks as $code => $check) {
            $checks[$code]['count'] = count($check['ids']);
            $checks[$code]['sample_ids'] = array_slice($check['ids'], 0, 20);
            unset($checks[$code]['ids']);
            if ($checks[$code]['count'] > 0) {
                $warnings[] = ['code' => $code, 'message' => $check['message'], 'count' => $checks[$code]['count'], 'sample_ids' => $checks[$code]['sample_ids']];
            }
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => $now->toIso8601String(),
            'read_only' => true,
            'counts' => [
                'qr_links_total' => QrLink::query()->count(),
                'qr_links_active' => QrLink::query()->where('is_active', true)->count(),
                'checks_with_findings' => count($warnings),
            ],
            'checks' => $checks,
            'warnings' => $warnings,
            'limitations' => [
                'Bu komut hiçbir QR/NFC kaydını değiştirmez, token üretmez veya bağlantıyı etkinleştirmez.',
                'Token değerleri rapora yazılmaz; yalnızca ilgili kayıt kimlikleri örneklenir.',
                'Bu statik veri denetimi gerçek cihaz, QR görüntüsü, NFC donanımı veya tarayıcı kabul testinin yerine geçmez.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART QR/NFC yaşam döngüsü denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('QR/NFC kayıtları: '.$report['counts']['qr_links_total'].' toplam / '.$report['counts']['qr_links_active'].' etkin');
            foreach ($checks as $code => $check) {
                $this->line($code.': '.$check['count']);
                if ($check['count'] > 0) {
                    $this->warn($check['message'].' Örnek kayıt ID: '.implode(', ', $check['sample_ids']));
                }
            }
            $this->line('Salt okunur: hiçbir kayıt değiştirilmedi. Token değerleri gösterilmedi.');
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }
}
