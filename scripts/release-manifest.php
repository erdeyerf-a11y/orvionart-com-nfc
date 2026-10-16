<?php
/**
 * ORVIONART release source manifest.
 * Usage: php scripts/release-manifest.php create|verify
 * Hashes distributable source files; intentionally excludes secrets, runtime data,
 * dependency installations, historical ZIP archives and the manifest itself.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$manifestPath = $root.DIRECTORY_SEPARATOR.'RELEASE_MANIFEST.json';
$mode = $argv[1] ?? 'verify';
if (!in_array($mode, ['create', 'verify'], true)) {
    fwrite(STDERR, "Kullanım: php scripts/release-manifest.php create|verify\n");
    exit(2);
}

$excludedDirectoryNames = [
    '.git', 'vendor', 'node_modules',
];
$excludedRelativePrefixes = [
    'docs/archive/',
    'storage/framework/cache/',
    'storage/framework/sessions/',
    'storage/framework/views/',
    'storage/logs/',
    'bootstrap/cache/',
];

$shouldExclude = static function (string $relative) use ($excludedDirectoryNames, $excludedRelativePrefixes): bool {
    $relative = str_replace('\\', '/', $relative);
    if ($relative === 'RELEASE_MANIFEST.json' || $relative === '.env') {
        return true;
    }
    if (str_starts_with($relative, '.env.') && $relative !== '.env.example') {
        return true;
    }
    if (preg_match('/\.zip$/i', $relative) === 1) {
        return true;
    }
    foreach ($excludedDirectoryNames as $directory) {
        if ($relative === $directory || str_starts_with($relative, $directory.'/') || str_contains($relative, '/'.$directory.'/')) {
            return true;
        }
    }
    foreach ($excludedRelativePrefixes as $prefix) {
        if (str_starts_with($relative, $prefix)) {
            return true;
        }
    }
    // Runtime storage contents are not release source; preserve only ignore placeholders.
    if (str_starts_with($relative, 'storage/') && basename($relative) !== '.gitignore') {
        return true;
    }
    if (str_starts_with($relative, 'bootstrap/cache/') && basename($relative) !== '.gitignore') {
        return true;
    }
    return false;
};

$collect = static function () use ($root, $shouldExclude): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || $file->isLink()) {
            continue;
        }
        $absolute = $file->getPathname();
        $relative = substr($absolute, strlen($root) + 1);
        $relative = str_replace(DIRECTORY_SEPARATOR, '/', $relative);
        if ($shouldExclude($relative)) {
            continue;
        }
        $hash = hash_file('sha256', $absolute);
        if ($hash === false) {
            throw new RuntimeException('Dosya hash değeri okunamadı: '.$relative);
        }
        $files[$relative] = ['bytes' => $file->getSize(), 'sha256' => $hash];
    }
    ksort($files, SORT_STRING);
    return $files;
};

try {
    if ($mode === 'create') {
        $files = $collect();
        $version = trim((string) @file_get_contents($root.'/VERSION'));
        if ($version === '') {
            throw new RuntimeException('VERSION dosyası boş veya eksik.');
        }
        $manifest = [
            'format' => 'orvionart-release-manifest-v1',
            'application' => 'ORVIONART',
            'version' => $version,
            'generated_at_utc' => gmdate('Y-m-d\TH:i:s\Z'),
            'manifest_scope' => 'Distributable source files only; excludes secrets, runtime data, vendor, historical ZIPs, and this manifest.',
            'file_count' => count($files),
            'files' => $files,
        ];
        $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false || file_put_contents($manifestPath, $json."\n", LOCK_EX) === false) {
            throw new RuntimeException('RELEASE_MANIFEST.json yazılamadı.');
        }
        echo 'PASS: sürüm manifesti oluşturuldu; dosya sayısı='.count($files).'; sürüm='.$version."\n";
        echo "Kapsam: kaynak dosyaları; .env, runtime verisi, vendor ve ZIP arşivleri hariç.\n";
        exit(0);
    }

    if (!is_file($manifestPath)) {
        throw new RuntimeException('RELEASE_MANIFEST.json bulunamadı; önce create çalıştırın.');
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest) || ($manifest['format'] ?? null) !== 'orvionart-release-manifest-v1' || !is_array($manifest['files'] ?? null)) {
        throw new RuntimeException('Manifest biçimi geçersiz.');
    }
    $expected = $manifest['files'];
    $errors = [];
    foreach ($expected as $relative => $record) {
        if (!is_string($relative) || !is_array($record)) {
            $errors[] = 'Manifest kaydı geçersiz.';
            continue;
        }
        $absolute = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($absolute) || is_link($absolute)) {
            $errors[] = 'Eksik dosya: '.$relative;
            continue;
        }
        $actualHash = hash_file('sha256', $absolute);
        $actualBytes = filesize($absolute);
        if ($actualHash === false || $actualBytes === false || !hash_equals((string) ($record['sha256'] ?? ''), $actualHash) || (int) ($record['bytes'] ?? -1) !== $actualBytes) {
            $errors[] = 'Değişmiş dosya: '.$relative;
        }
    }
    $current = $collect();
    foreach (array_diff(array_keys($current), array_keys($expected)) as $extra) {
        $errors[] = 'Manifestte olmayan dosya: '.$extra;
    }
    if ($errors !== []) {
        foreach ($errors as $error) {
            fwrite(STDERR, 'FAIL: '.$error."\n");
        }
        fwrite(STDERR, 'Manifest doğrulaması başarısız. Kaynak değiştiyse yeniden create çalıştırın ve paketi yeniden oluşturun.\n');
        exit(1);
    }
    echo 'PASS: release manifesti doğrulandı; sürüm='.($manifest['version'] ?? 'bilinmiyor').'; dosya sayısı='.count($expected)."\n";
    echo "Not: Bu kontrol Laravel çalışma zamanı veya sunucu kabul testi değildir.\n";
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'HATA: '.$exception->getMessage()."\n");
    exit(2);
}
