<?php

namespace App\Console\Commands;

use App\Models\DemoContent;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class InspectMediaReferences extends Command
{
    protected $signature = 'orvionart:inspect-media-references {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Veritabanı medya referanslarının yol güvenliğini ve dosya varlığını salt okunur denetler';

    private const DISK = 'local';

    private const MANAGED_PREFIXES = [
        'orvionart-media',
        'orvionart-site-media',
        'orvionart-demo',
    ];

    public function handle(): int
    {
        $issues = [];
        $checked = 0;
        $warnings = [];

        try {
            $disk = Storage::disk(self::DISK);

            foreach (MediaAsset::query()->where('disk', self::DISK)->get(['id', 'path']) as $asset) {
                $checked++;
                $this->inspectReference($disk, 'media_asset', (int) $asset->id, $asset->path, $issues);
            }

            foreach (DemoContent::query()->where('media_disk', self::DISK)->whereNotNull('media_path')->get(['id', 'media_path']) as $content) {
                $checked++;
                $this->inspectReference($disk, 'demo_content', (int) $content->id, $content->media_path, $issues);
            }
        } catch (Throwable $exception) {
            $warnings[] = [
                'code' => 'media_reference_inspection_unavailable',
                'message' => 'Medya referansları veya depolama okunamadı; denetim tamamlanamadı.',
            ];
        }

        $counts = [
            'references_checked' => $checked,
            'unsafe_paths' => $this->countIssue($issues, 'unsafe_path'),
            'outside_managed_scope' => $this->countIssue($issues, 'outside_managed_scope'),
            'missing_files' => $this->countIssue($issues, 'missing_file'),
            'unsafe_filesystem_targets' => $this->countIssue($issues, 'unsafe_filesystem_target'),
        ];

        if ($issues !== []) {
            $warnings[] = [
                'code' => 'media_reference_issues_found',
                'message' => 'Bazı veritabanı medya referansları güvenli yol biçimine, yönetilen klasör kapsamına veya dosya varlığına uymuyor. Otomatik düzeltme yapılmadı.',
                'count' => count($issues),
            ];
        }

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'disk_checked' => self::DISK,
            'managed_prefixes' => self::MANAGED_PREFIXES,
            'counts' => $counts,
            'issues' => array_slice($issues, 0, 100),
            'issues_truncated' => count($issues) > 100,
            'warnings' => $warnings,
            'limitations' => [
                'Bu komut hiçbir dosya veya veritabanı kaydını değiştirmez, taşımaz ya da silmez.',
                'Yalnızca local disk üzerindeki MediaAsset ve DemoContent medya referanslarını inceler.',
                'Yönetilen klasörler dışındaki yollar otomatik olarak yanlış kabul edilmez; insan incelemesi için raporlanır.',
                'Dosya hedeflerinin realpath sınırı ve normal dosya olup olmadığı kontrol edilir; bu kontrol anlık bir salt okunur incelemedir ve sonradan değişiklik yarışını ortadan kaldırmaz.',
                'Bu statik uygulama denetimi gerçek yedek geri yükleme veya sunucu kabul testi değildir.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART medya referans denetimi (salt okunur)');
            $this->line('İncelenen referans: '.$checked);
            $this->line('Güvensiz yol: '.$counts['unsafe_paths']);
            $this->line('Yönetilen klasör kapsamı dışında: '.$counts['outside_managed_scope']);
            $this->line('Dosyası bulunamayan referans: '.$counts['missing_files']);
            $this->line('Güvensiz dosya sistemi hedefi: '.$counts['unsafe_filesystem_targets']);
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

    /** @param array<int, array<string, mixed>> $issues */
    private function inspectReference($disk, string $type, int $id, mixed $path, array &$issues): void
    {
        if (!is_string($path) || trim($path) === '' || $this->hasUnsafePath($path)) {
            $issues[] = ['code' => 'unsafe_path', 'record_type' => $type, 'record_id' => $id];
            return;
        }

        $normalized = str_replace('\\', '/', $path);
        if (!$this->isInManagedScope($normalized)) {
            $issues[] = ['code' => 'outside_managed_scope', 'record_type' => $type, 'record_id' => $id];
            return;
        }

        try {
            if (!$disk->exists($normalized)) {
                $issues[] = ['code' => 'missing_file', 'record_type' => $type, 'record_id' => $id];
                return;
            }

            // A syntactically safe, managed-looking path can still be a symlink
            // to a file outside the private disk, or point at a directory. Resolve
            // the actual target before treating this reference as healthy.
            $rootPath = realpath($disk->path(''));
            $targetPath = realpath($disk->path($normalized));
            $insideRoot = $rootPath !== false
                && $targetPath !== false
                && str_starts_with($targetPath, rtrim($rootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);

            if (!$insideRoot || !is_file($targetPath)) {
                $issues[] = ['code' => 'unsafe_filesystem_target', 'record_type' => $type, 'record_id' => $id];
            }
        } catch (Throwable $exception) {
            $issues[] = ['code' => 'storage_check_failed', 'record_type' => $type, 'record_id' => $id];
        }
    }

    private function hasUnsafePath(string $path): bool
    {
        if (str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) === 1) {
            return true;
        }

        $segments = explode('/', str_replace('\\', '/', $path));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function isInManagedScope(string $path): bool
    {
        foreach (self::MANAGED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, array<string, mixed>> $issues */
    private function countIssue(array $issues, string $code): int
    {
        return count(array_filter($issues, static fn (array $issue): bool => ($issue['code'] ?? null) === $code));
    }
}
