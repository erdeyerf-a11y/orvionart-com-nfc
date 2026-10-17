<?php

namespace App\Console\Commands;

use App\Models\DemoContent;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectMediaStorage extends Command
{
    protected $signature = 'orvionart:inspect-media-storage {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'ORVIONART tarafından yönetilen medya klasörlerini veritabanı kayıtlarıyla karşılaştırır; salt okunurdur';

    private const MANAGED_PREFIXES = [
        'orvionart-media',
        'orvionart-site-media',
        'orvionart-demo',
    ];

    public function handle(): int
    {
        $diskName = 'local';
        $referenced = [];
        $warnings = [];
        $orphanedFiles = [];
        $filesByPrefix = [];
        $quarantinedFiles = [];

        foreach (MediaAsset::query()->get(['disk', 'path']) as $asset) {
            if ($asset->disk === $diskName && is_string($asset->path) && $asset->path !== '') {
                $referenced[$this->referenceKey($asset->disk, $asset->path)] = true;
            }
        }

        foreach (DemoContent::query()->whereNotNull('media_path')->get(['media_disk', 'media_path']) as $content) {
            if ($content->media_disk === $diskName && is_string($content->media_path) && $content->media_path !== '') {
                $referenced[$this->referenceKey($content->media_disk, $content->media_path)] = true;
            }
        }

        try {
            $disk = Storage::disk($diskName);
            foreach (self::MANAGED_PREFIXES as $prefix) {
                $files = $disk->allFiles($prefix);
                $filesByPrefix[$prefix] = count($files);
                foreach ($files as $path) {
                    if (!isset($referenced[$this->referenceKey($diskName, $path)])) {
                        $orphanedFiles[] = ['disk' => $diskName, 'path' => $path, 'managed_prefix' => $prefix];
                    }
                }
            }
            // Quarantine is inspected separately: files there may be leftovers
            // from a successful DB deletion whose final filesystem purge failed.
            $quarantinedFiles = $disk->allFiles('orvionart-trash');
        } catch (Throwable $exception) {
            $warnings[] = [
                'code' => 'media_storage_unavailable',
                'message' => 'Özel medya depolaması okunamadı; bu rapor dosya bütünlüğünü doğrulayamadı.',
            ];
        }

        if ($orphanedFiles !== []) {
            $warnings[] = [
                'code' => 'unreferenced_managed_media_files',
                'message' => 'Yönetilen medya klasörlerinde veritabanında eşleşen medya kaydı bulunmayan dosyalar var. Otomatik silinmedi.',
                'count' => count($orphanedFiles),
            ];
        }
        if ($quarantinedFiles !== []) {
            $warnings[] = [
                'code' => 'media_quarantine_cleanup_pending',
                'message' => 'Özel medya karantinasında temizliği tamamlanmamış dosyalar var. Otomatik silinmedi; inceleme gerekiyor.',
                'count' => count($quarantinedFiles),
            ];
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'disk_checked' => $diskName,
            'scope' => self::MANAGED_PREFIXES,
            'unreferenced_paths' => array_values(array_map(static fn (array $item): string => (string) ($item['path'] ?? ''), $orphanedFiles)),
            'quarantined_paths' => array_values($quarantinedFiles),
            'counts' => [
                'media_asset_records_on_local_disk' => MediaAsset::query()->where('disk', $diskName)->count(),
                'demo_content_records_on_local_disk' => DemoContent::query()->where('media_disk', $diskName)->whereNotNull('media_path')->count(),
                'files_by_prefix' => $filesByPrefix,
                'unreferenced_managed_files' => count($orphanedFiles),
                'quarantined_files_pending_review' => count($quarantinedFiles),
            ],
            'unreferenced_files' => $orphanedFiles,
            'quarantined_files' => array_map(static fn (string $path): array => ['disk' => $diskName, 'path' => $path], $quarantinedFiles),
            'quarantined_paths' => array_values($quarantinedFiles),
            'warnings' => $warnings,
            'limitations' => [
                'Bu komut dosya veya veritabanı kaydı silmez, değiştirmez ya da taşımaz.',
                'Yalnızca local disk üzerindeki ORVIONART tarafından yönetilen üç medya klasörünü ve özel karantina klasörünü inceler.',
                'Bulunan sahipsiz dosyalar otomatik silinmemelidir; yedek ve iş kayıtları incelendikten sonra ayrı bir işlemle ele alınmalıdır.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART medya depolama denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            foreach ($filesByPrefix as $prefix => $count) {
                $this->line($prefix.': '.$count.' dosya');
            }
            $this->line('Veritabanı kaydıyla eşleşmeyen yönetilen dosya: '.count($orphanedFiles));
            $this->line('Özel karantinada inceleme bekleyen dosya: '.count($quarantinedFiles));
            foreach ($warnings as $warning) {
                $this->warn(strtoupper($warning['code']).': '.$warning['message']);
            }
            if ($warnings === []) {
                $this->info('Uyarı bulunmadı. Bu rapor son kabul testi değildir.');
            }
            $this->line('Salt okunur: hiçbir kayıt veya dosya değiştirilmedi.');
        }

        return $warnings === [] ? self::SUCCESS : self::FAILURE;
    }

    private function referenceKey(string $disk, string $path): string
    {
        return $disk."\0".ltrim(str_replace('\\', '/', $path), '/');
    }
}
