<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ReleaseReadinessCheck extends Command
{
    protected $signature = 'orvionart:release-check {--json : Raporu JSON biçiminde yazdır}';

    protected $description = 'Kurulum ve kontrollü staging teslimi öncesi salt okunur hazırlık denetimi';

    /** @var array<int, array{code:string,status:string,message:string}> */
    private array $checks = [];

    public function handle(): int
    {
        $this->checkRuntime();
        $this->checkOperationalLimits();
        $this->checkProjectFiles();
        $this->checkApplicationConfiguration();
        $this->checkWritableDirectories();
        $this->checkDatabaseAndMigrations();
        $this->checkManualRequirements();

        $hasFailures = collect($this->checks)->contains(fn (array $check): bool => $check['status'] === 'fail');
        $hasReviewItems = collect($this->checks)->contains(fn (array $check): bool => in_array($check['status'], ['warn', 'manual'], true));
        $overall = $hasFailures ? 'BLOCKED' : ($hasReviewItems ? 'REVIEW_REQUIRED' : 'PASS');

        $report = [
            'application' => 'ORVIONART',
            'version' => trim((string) @file_get_contents(base_path('VERSION'))),
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'overall' => $overall,
            'summary' => [
                'passed' => count(array_filter($this->checks, fn (array $check): bool => $check['status'] === 'pass')),
                'warnings' => count(array_filter($this->checks, fn (array $check): bool => $check['status'] === 'warn')),
                'failed' => count(array_filter($this->checks, fn (array $check): bool => $check['status'] === 'fail')),
                'manual_review' => count(array_filter($this->checks, fn (array $check): bool => $check['status'] === 'manual')),
            ],
            'checks' => $this->checks,
            'limitations' => [
                'Bu komut salt okunurdur; dosya, veritabanı veya yapılandırma değiştirmez.',
                'PASS sonucu tek başına canlıya çıkış onayı değildir; manuel kontroller ve kabul testleri ayrıca tamamlanmalıdır.',
                'Web sunucusunun gerçek document root ayarı, parola koruması, yedekten geri yükleme ve HTTPS erişimi komut satırından kesin olarak doğrulanamaz.',
                'Komut ödeme işlemi başlatmaz ve migration çalıştırmaz.',
            ],
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('ORVIONART kurulum / staging hazırlık denetimi (salt okunur)');
            $this->line('Sürüm: '.($report['version'] ?: 'bilinmiyor'));
            $this->line('Genel sonuç: '.$overall);
            foreach ($this->checks as $check) {
                $line = '['.strtoupper($check['status']).'] '.$check['code'].': '.$check['message'];
                if ($check['status'] === 'fail') {
                    $this->error($line);
                } elseif (in_array($check['status'], ['warn', 'manual'], true)) {
                    $this->warn($line);
                } else {
                    $this->line($line);
                }
            }
            $this->line('Salt okunur: dosya, veritabanı ve yapılandırma değiştirilmedi.');
            $this->line('Not: Bu rapor son kabul testi veya canlıya çıkış onayı değildir.');
        }

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }

    private function checkRuntime(): void
    {
        $this->add(PHP_VERSION_ID >= 80300, 'php_version', 'PHP sürümü '.PHP_VERSION.'; Laravel 12 için PHP 8.3 veya üstü gerekir.');

        $composerPath = base_path('composer.json');
        $composer = is_file($composerPath) ? json_decode((string) file_get_contents($composerPath), true) : null;
        $requirements = is_array($composer) ? ($composer['require'] ?? []) : [];
        $extensions = [];
        foreach ($requirements as $package => $constraint) {
            if (is_string($package) && str_starts_with($package, 'ext-')) {
                $extensions[] = substr($package, 4);
            }
        }
        foreach (array_unique($extensions) as $extension) {
            $isRequiredForConfiguredDriver = $extension !== 'pdo_mysql'
                || in_array((string) config('database.default'), ['mysql', 'mariadb'], true);
            if (!$isRequiredForConfiguredDriver) {
                continue;
            }
            $this->add(extension_loaded($extension), 'php_extension_'.$extension, 'PHP eklentisi: '.$extension.'.');
        }
    }

    /**
     * Report operational settings that commonly break resumable uploads or queued
     * video processing on shared hosting. These are warnings, not secret-bearing
     * diagnostics, and this check never changes PHP or queue configuration.
     */
    private function checkOperationalLimits(): void
    {
        $uploadLimit = $this->iniBytes((string) ini_get('upload_max_filesize'));
        $postLimit = $this->iniBytes((string) ini_get('post_max_size'));
        // A 4 MiB chunk needs room for multipart/form-data overhead.
        $uploadReady = $uploadLimit >= 5 * 1024 * 1024;
        $postReady = $postLimit >= 6 * 1024 * 1024;
        $this->add(
            $uploadReady,
            'php_upload_max_filesize',
            'PHP upload_max_filesize '.$this->formatBytes($uploadLimit).'; parçalı yükleme için en az 5 MiB önerilir.',
            'warn'
        );
        $this->add(
            $postReady,
            'php_post_max_size',
            'PHP post_max_size '.$this->formatBytes($postLimit).'; parçalı yükleme için en az 6 MiB önerilir.',
            'warn'
        );

        $queueConnection = (string) config('queue.default', '');
        $queueReady = $queueConnection !== '' && $queueConnection !== 'sync';
        $this->add(
            $queueReady,
            'queue_connection',
            $queueReady
                ? 'Arka plan işleri için kuyruk sürücüsü yapılandırılmış: '.$queueConnection.'. Worker ayrıca doğrulanmalıdır.'
                : 'QUEUE_CONNECTION sync veya boş; video işleme işleri arka planda çalışmayabilir.',
            'warn'
        );

        if (!(bool) config('orvionart.video_transcoding_enabled', true)) {
            $this->add(true, 'video_processing_binaries', 'Video dönüştürme kapalı; FFmpeg/FFprobe kontrolü uygulanmadı.');
            return;
        }

        $ffmpeg = (string) config('orvionart.ffmpeg_binary', 'ffmpeg');
        $ffprobe = (string) config('orvionart.ffprobe_binary', 'ffprobe');
        $ffmpegReady = $this->binaryAvailable($ffmpeg);
        $ffprobeReady = $this->binaryAvailable($ffprobe);
        $this->add($ffmpegReady, 'ffmpeg_binary', 'FFmpeg ikilisi '.($ffmpegReady ? 'bulunabilir.' : 'bulunamadı; video dönüştürme çalışmayabilir.'), 'warn');
        $this->add($ffprobeReady, 'ffprobe_binary', 'FFprobe ikilisi '.($ffprobeReady ? 'bulunabilir.' : 'bulunamadı; video doğrulama çalışmayabilir.'), 'warn');
    }

    private function iniBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        if ($value === '-1') {
            return PHP_INT_MAX;
        }
        if (!preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*([KMG]?)B?$/i', $value, $matches)) {
            return 0;
        }
        $number = (float) $matches[1];
        $unit = strtoupper($matches[2] ?? '');
        $power = match ($unit) {
            'G' => 3,
            'M' => 2,
            'K' => 1,
            default => 0,
        };
        return (int) min(PHP_INT_MAX, $number * (1024 ** $power));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === PHP_INT_MAX) {
            return 'sınırsız';
        }
        if ($bytes <= 0) {
            return '0 B / tanımsız';
        }
        return number_format($bytes / 1048576, 1, ',', '.').' MiB';
    }

    private function binaryAvailable(string $binary): bool
    {
        $binary = trim($binary);
        if ($binary === '' || str_contains($binary, "\0")) {
            return false;
        }
        if (str_contains($binary, DIRECTORY_SEPARATOR)) {
            return is_file($binary) && is_executable($binary);
        }
        $path = (string) getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory === '') {
                continue;
            }
            $candidate = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$binary;
            if (is_file($candidate) && is_executable($candidate)) {
                return true;
            }
        }
        return false;
    }

    private function checkProjectFiles(): void
    {
        foreach ([
            'composer_manifest' => 'composer.json',
            'composer_lock' => 'composer.lock',
            'vendor_autoload' => 'vendor/autoload.php',
            'artisan' => 'artisan',
            'public_entrypoint' => 'public/index.php',
        ] as $code => $relativePath) {
            $exists = is_file(base_path($relativePath));
            $message = $exists ? $relativePath.' mevcut.' : $relativePath.' bulunamadı.';
            $this->add($exists, $code, $message);
        }
    }

    private function checkApplicationConfiguration(): void
    {
        $environment = (string) config('app.env');
        $isServerEnvironment = in_array($environment, ['staging', 'production'], true);
        $this->add($isServerEnvironment, 'app_environment', 'APP_ENV='.$environment.'; staging veya production tercih edilir.', $isServerEnvironment ? 'pass' : 'warn');

        $key = (string) config('app.key', '');
        $this->add($key !== '', 'app_key', 'APP_KEY yapılandırılmış olmalıdır.');

        $debugOff = config('app.debug') === false;
        $debugCheckRequired = in_array($environment, ['staging', 'production'], true);
        $this->add(!$debugCheckRequired || $debugOff, 'debug_mode', 'Staging/production ortamlarında APP_DEBUG=false olmalıdır.');

        $url = (string) config('app.url', '');
        $https = strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
        $httpsCheckRequired = in_array($environment, ['staging', 'production'], true);
        $this->add(!$httpsCheckRequired || $https, 'app_url_https', 'Staging/production APP_URL değeri HTTPS kullanmalıdır.');

        $sessionSecure = (bool) config('session.secure', false);
        $sessionHttpOnly = (bool) config('session.http_only', false);
        $sessionCheckRequired = in_array($environment, ['staging', 'production'], true);
        $this->add(!$sessionCheckRequired || ($sessionSecure && $sessionHttpOnly), 'session_cookie_flags', 'Staging/production çerezleri Secure ve HttpOnly olmalıdır.');

        $paymentsEnabled = (bool) config('orvionart.payments_enabled', false);
        $this->add(!$paymentsEnabled, 'payments_disabled', 'Başlangıç aşamasında ödeme entegrasyonu pasif tutulmalıdır.');
    }

    private function checkWritableDirectories(): void
    {
        foreach ([
            'storage/app',
            'storage/app/private',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ] as $relativePath) {
            $path = base_path($relativePath);
            $ok = is_dir($path) && is_writable($path);
            $this->add($ok, 'writable_'.str_replace(['/', '-'], '_', $relativePath), $relativePath.' dizini mevcut ve yazılabilir olmalıdır.');
        }
    }

    private function checkDatabaseAndMigrations(): void
    {
        try {
            DB::connection()->getPdo();
            $this->add(true, 'database_connection', 'Veritabanı bağlantısı başarılı.');
        } catch (Throwable) {
            $this->add(false, 'database_connection', 'Veritabanı bağlantısı kurulamadı; bağlantı ayrıntıları güvenlik nedeniyle gösterilmedi.');
            $this->add(false, 'migration_state', 'Migration durumu veritabanı bağlantısı olmadan doğrulanamadı.');
            return;
        }

        $migrationTable = (string) config('database.migrations.table', 'migrations');
        if (!Schema::hasTable($migrationTable)) {
            $this->add(false, 'migration_state', 'Migration tablosu bulunamadı; kurulum durumu doğrulanamadı.');
            return;
        }

        try {
            $applied = DB::table($migrationTable)->pluck('migration')->map(fn ($name): string => (string) $name)->all();
            $files = glob(database_path('migrations/*.php')) ?: [];
            $available = array_map(fn (string $path): string => basename($path, '.php'), $files);
            $pending = array_values(array_diff($available, $applied));
            $orphaned = array_values(array_diff($applied, $available));
            $ok = $pending === [] && $orphaned === [];
            $message = $ok
                ? 'Migration dosyaları ile veritabanındaki uygulanmış kayıtlar tutarlı.'
                : 'Migration tutarsızlığı var; bekleyen: '.count($pending).', kaynak dosyası bulunmayan uygulanmış kayıt: '.count($orphaned).'.';
            $this->add($ok, 'migration_state', $message);
        } catch (Throwable) {
            $this->add(false, 'migration_state', 'Migration durumu okunamadı; ayrıntılar güvenlik nedeniyle gösterilmedi.');
        }
    }

    private function checkManualRequirements(): void
    {
        $this->checks[] = [
            'code' => 'staging_access_restriction',
            'status' => 'manual',
            'message' => 'Staging alanında parola/erişim kısıtlaması gerçek web sunucusunda doğrulanmalı.',
        ];
        $this->checks[] = [
            'code' => 'document_root_and_secrets',
            'status' => 'manual',
            'message' => 'Document root yalnızca public/ dizinine bakmalı; .env ve gizli yapılandırmalar web üzerinden erişilemez olmalı.',
        ];
        $this->checks[] = [
            'code' => 'backup_restore_drill',
            'status' => 'manual',
            'message' => 'Staging veritabanı ve medya yedeğinden geri yükleme tatbikatı ayrıca yapılmalı.',
        ];
        $this->checks[] = [
            'code' => 'https_and_browser_acceptance',
            'status' => 'manual',
            'message' => 'Gerçek HTTPS erişimi, yönetici akışları, mobil ekranlar ve son kabul testleri tarayıcıda doğrulanmalı.',
        ];
    }

    private function add(bool $passed, string $code, string $message, ?string $failedStatus = null): void
    {
        $this->checks[] = [
            'code' => $code,
            'status' => $passed ? 'pass' : ($failedStatus ?? 'fail'),
            'message' => $message,
        ];
    }
}
