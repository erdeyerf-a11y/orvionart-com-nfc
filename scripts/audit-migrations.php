<?php

declare(strict_types=1);

/**
 * Static migration audit. It never boots Laravel or connects to a database.
 * Exit 0 means no static blockers were found; it is not proof that migrations run.
 */
$root = dirname(__DIR__);
$json = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--json') {
        $json = true;
    } elseif (str_starts_with($arg, '--root=')) {
        $candidate = substr($arg, 7);
        if ($candidate === '' || !is_dir($candidate)) {
            fwrite(STDERR, "HATA: --root geçerli bir dizin olmalı.\n");
            exit(2);
        }
        $root = realpath($candidate) ?: $candidate;
    } else {
        fwrite(STDERR, "Kullanım: php scripts/audit-migrations.php [--json] [--root=/proje/koku]\n");
        exit(2);
    }
}

$directory = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
if (!is_dir($directory)) {
    fwrite(STDERR, "HATA: database/migrations dizini bulunamadı.\n");
    exit(2);
}
$files = glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [];
sort($files, SORT_STRING);
$issues = [];
$seenTimestamps = [];

if ($files === []) {
    $issues[] = ['level' => 'BLOCKER', 'file' => 'database/migrations', 'message' => 'Migration dosyası bulunamadı.'];
}

foreach ($files as $file) {
    $name = basename($file);
    $source = file_get_contents($file);
    if ($source === false) {
        $issues[] = ['level' => 'BLOCKER', 'file' => 'database/migrations/' . $name, 'message' => 'Dosya okunamadı.'];
        continue;
    }
    $relative = 'database/migrations/' . $name;

    if (!preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_[a-z0-9_]+\.php$/', $name, $match)) {
        $issues[] = ['level' => 'BLOCKER', 'file' => $relative, 'message' => 'Laravel migration dosya adı biçimi geçersiz.'];
    } elseif (isset($seenTimestamps[$match[1]])) {
        $issues[] = ['level' => 'BLOCKER', 'file' => $relative, 'message' => 'Aynı zaman damgasını kullanan birden fazla migration var.'];
    } else {
        $seenTimestamps[$match[1]] = $name;
    }

    if (!preg_match('/\bfunction\s+up\s*\(/', $source)) {
        $issues[] = ['level' => 'BLOCKER', 'file' => $relative, 'message' => 'up() metodu bulunamadı.'];
    }
    if (!preg_match('/\bfunction\s+down\s*\(/', $source)) {
        $issues[] = ['level' => 'BLOCKER', 'file' => $relative, 'message' => 'down() geri dönüş metodu bulunamadı.'];
    }
    if (!preg_match('/return\s+new\s+class\s+extends\s+Migration|class\s+\w+\s+extends\s+Migration/', $source)) {
        $issues[] = ['level' => 'BLOCKER', 'file' => $relative, 'message' => 'Migration sınıfı tanımı tanınamadı.'];
    }

    // Raw destructive SQL in up() is flagged for human review, not auto-rejected.
    $upStart = strpos($source, 'function up');
    $downStart = strpos($source, 'function down');
    if ($upStart !== false) {
        $upBody = substr($source, $upStart, $downStart !== false && $downStart > $upStart ? $downStart - $upStart : null);
        if (preg_match('/DB::(?:statement|unprepared)\s*\(\s*[\'\"]\s*(?:DROP\s+(?:TABLE|DATABASE|COLUMN)|TRUNCATE\s+TABLE|DELETE\s+FROM)\b/i', $upBody)) {
            $issues[] = ['level' => 'REVIEW', 'file' => $relative, 'message' => 'up() içinde yıkıcı ham SQL olasılığı var; staging veritabanında insan incelemesi gerekli.'];
        }
    }
}

$blockers = count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'BLOCKER'));
$reviews = count(array_filter($issues, static fn (array $issue): bool => $issue['level'] === 'REVIEW'));
$result = [
    'tool' => 'ORVIONART migration static audit',
    'root' => basename($root),
    'files_checked' => count($files),
    'blockers' => $blockers,
    'reviews' => $reviews,
    'status' => $blockers > 0 ? 'BLOCKED' : ($reviews > 0 ? 'REVIEW_REQUIRED' : 'STATIC_CHECKS_PASS_RUNTIME_PENDING'),
    'issues' => $issues,
    'limitations' => [
        'Laravel bootstrap veya veritabanı bağlantısı yapılmadı.',
        'Migration up/down çalıştırılmadı; SQL uyumluluğu ve veri koruma davranışı kanıtlanmadı.',
    ],
];

if ($json) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} else {
    printf("ORVIONART MIGRATION AUDIT — %d dosya\n", count($files));
    foreach ($issues as $issue) {
        printf("%s: %s — %s\n", $issue['level'], $issue['file'], $issue['message']);
    }
    printf("ÖZET BLOCKER=%d REVIEW=%d\n", $blockers, $reviews);
    printf("SONUÇ: %s\n", $result['status']);
    echo "SINIR: Bu statik kontroldür; veritabanı migration testi değildir.\n";
}
exit($blockers > 0 ? 1 : 0);
