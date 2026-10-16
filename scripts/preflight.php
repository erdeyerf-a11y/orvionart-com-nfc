<?php
/**
 * ORVIONART non-destructive deployment preflight.
 * Usage: php scripts/preflight.php [--check-db]
 * Never prints .env values or writes to the application/database.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$checkDb = in_array('--check-db', $argv, true);
$results = [];

function result(string $level, string $label, string $detail): void
{
    global $results;
    $results[] = [$level, $label, $detail];
}

function parseEnvFile(string $path): array
{
    if (!is_file($path) || !is_readable($path)) return [];
    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') continue;
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
        }
        $values[$key] = $value;
    }
    return $values;
}

function findExecutable(string $name): ?string
{
    $path = (string) getenv('PATH');
    foreach (explode(PATH_SEPARATOR, $path) as $dir) {
        $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        if (is_file($candidate) && is_executable($candidate)) return $candidate;
    }
    return null;
}

// Runtime and source integrity checks.
if (PHP_VERSION_ID >= 80300) result('PASS', 'PHP sürümü', PHP_VERSION . ' (PHP 8.3 veya üstü)');
else result('BLOCKER', 'PHP sürümü', PHP_VERSION . ' bulundu; PHP 8.3 veya üstü gerekir.');

$composerJson = $root . '/composer.json';
$composer = is_file($composerJson) ? json_decode((string) file_get_contents($composerJson), true) : null;
if (!is_array($composer)) result('BLOCKER', 'composer.json', 'Dosya yok veya JSON geçersiz.');
else result('PASS', 'composer.json', 'JSON okunabildi.');

$requiredExtensions = [];
foreach (($composer['require'] ?? []) as $name => $constraint) {
    if (str_starts_with((string) $name, 'ext-')) $requiredExtensions[] = substr((string) $name, 4);
}
$missingExtensions = [];
foreach (array_unique($requiredExtensions) as $extension) {
    if (!extension_loaded($extension)) $missingExtensions[] = $extension;
}
if ($missingExtensions === []) result('PASS', 'PHP eklentileri', 'composer.json içinde belirtilen ext-* bağımlılıkları mevcut.');
else result('BLOCKER', 'PHP eklentileri', 'Eksik: ' . implode(', ', $missingExtensions));

$composerBin = findExecutable('composer');
if ($composerBin) result('PASS', 'Composer', 'Çalıştırılabilir dosya bulundu.');
else result('BLOCKER', 'Composer', 'PATH içinde composer bulunamadı. Kurulumdan önce sunucuda Composer erişimi doğrulanmalı.');

if (is_file($root . '/artisan')) result('PASS', 'Laravel giriş dosyası', 'artisan bulundu.');
else result('BLOCKER', 'Laravel giriş dosyası', 'artisan bulunamadı.');

$envPath = $root . '/.env';
$env = parseEnvFile($envPath);
if (is_file($envPath)) result('PASS', '.env', 'Dosya mevcut; içerik ekrana yazdırılmadı.');
else result('BLOCKER', '.env', '.env bulunamadı. Sunucuya özel yapılandırma oluşturulmalı; sırlar kaynak koda eklenmemeli.');

if (is_file($root . '/.env.example')) result('PASS', '.env.example', 'Örnek yapılandırma mevcut.');
else result('WARN', '.env.example', 'Örnek yapılandırma dosyası bulunamadı.');

if (is_file($root . '/vendor/autoload.php')) result('PASS', 'Composer bağımlılıkları', 'vendor/autoload.php mevcut.');
else result('WARN', 'Composer bağımlılıkları', 'vendor/autoload.php yok; composer install henüz çalıştırılmamış olabilir.');

if (is_file($root . '/composer.lock')) result('PASS', 'composer.lock', 'Kilitleme dosyası mevcut.');
else result('WARN', 'composer.lock', 'Kilitleme dosyası yok. Bağımlılık sürümleri hedef sunucuda doğrulanmalı; lock dosyası üretim tesliminden önce sabitlenmeli.');

$appKey = trim((string) ($env['APP_KEY'] ?? ''));
if ($appKey === '' || str_contains($appKey, 'CHANGE_ME')) {
    result('BLOCKER', 'APP_KEY', 'APP_KEY boş veya örnek değer. Sunucu ortamında benzersiz anahtar oluşturulmalı.');
} elseif (str_starts_with($appKey, 'base64:')) {
    $decodedKey = base64_decode(substr($appKey, 7), true);
    if ($decodedKey !== false && strlen($decodedKey) === 32) result('PASS', 'APP_KEY', 'Base64 anahtar biçimi ve uzunluğu uygun; değer gösterilmedi.');
    else result('BLOCKER', 'APP_KEY', 'Base64 anahtar biçimi geçersiz veya anahtar uzunluğu 32 bayt değil. Değer gösterilmedi.');
} else {
    result('WARN', 'APP_KEY', 'Anahtar mevcut ancak standart base64: biçiminde değil; Laravel yapılandırmasıyla ayrıca doğrulanmalı. Değer gösterilmedi.');
}

$appEnv = strtolower(trim((string) ($env['APP_ENV'] ?? '')));
if (in_array($appEnv, ['staging', 'production'], true)) result('PASS', 'APP_ENV', 'Ortam ' . $appEnv . ' olarak ayarlı.');
elseif ($appEnv === 'local') result('BLOCKER', 'APP_ENV', 'Sunucu hazırlık/üretim ortamında APP_ENV=local kullanılmamalı.');
else result('WARN', 'APP_ENV', 'APP_ENV staging veya production olarak açıkça ayarlanmalı.');

$appUrl = trim((string) ($env['APP_URL'] ?? ''));
if (filter_var($appUrl, FILTER_VALIDATE_URL) && strtolower((string) parse_url($appUrl, PHP_URL_SCHEME)) === 'https') {
    result('PASS', 'APP_URL', 'Geçerli HTTPS adresi ayarlı.');
} elseif (in_array($appEnv, ['staging', 'production'], true)) {
    result('BLOCKER', 'APP_URL', 'Hazırlık/üretim ortamında APP_URL geçerli bir HTTPS adresi olmalı. Adresin kendisi ekrana yazdırılmadı.');
} else {
    result('WARN', 'APP_URL', 'APP_URL geçerli bir HTTPS adresi olarak doğrulanamadı.');
}

$appDebug = strtolower(trim((string) ($env['APP_DEBUG'] ?? '')));
if ($appDebug === 'false' || $appDebug === '0') result('PASS', 'APP_DEBUG', 'Hata ayrıntıları ziyaretçilere açılmayacak şekilde kapalı.');
elseif ($appDebug === 'true' || $appDebug === '1') result('BLOCKER', 'APP_DEBUG', 'APP_DEBUG açık. Hazırlık/üretim sunucusunda ayrıntılı hata gösterimi kapatılmalı.');
else result('BLOCKER', 'APP_DEBUG', 'APP_DEBUG açıkça false olarak ayarlanmalı; eksik veya belirsiz değer kabul edilmez.');

$secureCookie = strtolower(trim((string) ($env['SESSION_SECURE_COOKIE'] ?? '')));
if (in_array($secureCookie, ['true', '1'], true)) result('PASS', 'SESSION_SECURE_COOKIE', 'Oturum çerezi yalnızca HTTPS için yapılandırılmış.');
else result('BLOCKER', 'SESSION_SECURE_COOKIE', 'Hazırlık/üretim ortamında SESSION_SECURE_COOKIE=true olmalı.');

$httpOnlyCookie = strtolower(trim((string) ($env['SESSION_HTTP_ONLY'] ?? '')));
if (in_array($httpOnlyCookie, ['true', '1'], true)) result('PASS', 'SESSION_HTTP_ONLY', 'Oturum çerezi JavaScript erişimine kapalı.');
else result('BLOCKER', 'SESSION_HTTP_ONLY', 'SESSION_HTTP_ONLY=true olmalı.');

$sameSite = strtolower(trim((string) ($env['SESSION_SAME_SITE'] ?? '')));
if (in_array($sameSite, ['lax', 'strict'], true)) result('PASS', 'SESSION_SAME_SITE', 'Oturum çerezi SameSite=' . $sameSite . ' olarak ayarlı.');
else result('BLOCKER', 'SESSION_SAME_SITE', 'SESSION_SAME_SITE lax veya strict olmalı.');

$paymentsEnabled = strtolower(trim((string) ($env['ORVIONART_PAYMENTS_ENABLED'] ?? 'false')));
if (in_array($paymentsEnabled, ['false', '0', 'no', 'off', ''], true)) result('PASS', 'Ödeme entegrasyonu', 'Ödeme özelliği kapalı.');
else result('BLOCKER', 'Ödeme entegrasyonu', 'ORVIONART_PAYMENTS_ENABLED etkin görünüyor; bu aşamada ödeme kapalı kalmalı.');

if (is_file($envPath)) {
    $permissions = @fileperms($envPath);
    if ($permissions === false) result('WARN', '.env dosya izinleri', 'Dosya izinleri okunamadı.');
    else {
        $mode = $permissions & 0777;
        if (($mode & 0004) !== 0) result('BLOCKER', '.env dosya izinleri', 'Dosya herkes tarafından okunabilir; izinler daraltılmalı.');
        elseif (($mode & 0040) !== 0) result('WARN', '.env dosya izinleri', 'Grup okuma izni var; dosya sahibi/grup gereksinimine göre 600 veya güvenli 640 kullanımı doğrulanmalı.');
        else result('PASS', '.env dosya izinleri', 'Diğer kullanıcıların okuma izni yok.');
    }
}

foreach (['storage', 'bootstrap/cache'] as $relative) {
    $path = $root . '/' . $relative;
    if (is_dir($path) && is_writable($path)) result('PASS', 'Yazma izni: ' . $relative, 'Dizin yazılabilir.');
    else result('BLOCKER', 'Yazma izni: ' . $relative, 'Dizin yok veya PHP kullanıcısı tarafından yazılamıyor.');
}

if (is_dir($root . '/public')) result('PASS', 'public web kökü', 'public dizini mevcut; sunucunun document root ayarı panelden ayrıca doğrulanmalı.');
else result('BLOCKER', 'public web kökü', 'public dizini bulunamadı.');

if (is_file($root . '/public/.htaccess')) result('PASS', 'Apache kuralları', 'public/.htaccess mevcut; gerçek web sunucusunda rewrite ayrıca doğrulanmalı.');
else result('WARN', 'Apache kuralları', 'public/.htaccess bulunamadı; web sunucusu yönlendirme yapılandırmasını kontrol edin.');


// Validate database configuration even when the optional live connection check is not requested.
// This prevents a copied .env.example from appearing deployment-ready merely because --check-db was omitted.
$dbConnection = strtolower(trim((string) ($env['DB_CONNECTION'] ?? '')));
if ($dbConnection === 'mysql') {
    result('PASS', 'Veritabanı sürücüsü', 'DB_CONNECTION=mysql; composer.json ile uyumlu.');
} else {
    result('BLOCKER', 'Veritabanı sürücüsü', 'DB_CONNECTION mysql olarak ayarlanmalı; mevcut kaynak paketi PDO MySQL gerektiriyor.');
}
$dbHost = trim((string) ($env['DB_HOST'] ?? ''));
$dbPort = trim((string) ($env['DB_PORT'] ?? ''));
$dbName = trim((string) ($env['DB_DATABASE'] ?? ''));
$dbUser = trim((string) ($env['DB_USERNAME'] ?? ''));
$dbPassword = (string) ($env['DB_PASSWORD'] ?? '');
$placeholderDatabaseValues = [$dbHost, $dbName, $dbUser, $dbPassword];
$hasDatabasePlaceholder = false;
foreach ($placeholderDatabaseValues as $candidate) {
    if ($candidate === '' || preg_match('/replace[_ -]?with|your[_ -]?(database|user|password)|change[_ -]?me/i', $candidate) === 1) {
        $hasDatabasePlaceholder = true;
        break;
    }
}
if ($hasDatabasePlaceholder) {
    result('BLOCKER', 'Veritabanı yapılandırması', 'DB_HOST, DB_DATABASE, DB_USERNAME ve DB_PASSWORD gerçek ortama göre doldurulmalı; değerler rapora yazdırılmadı.');
} else {
    result('PASS', 'Veritabanı yapılandırması', 'Bağlantı alanları boş veya örnek yer tutucu değil; gizli değerler gösterilmedi.');
}
if ($dbPort !== '' && ctype_digit($dbPort) && (int) $dbPort >= 1 && (int) $dbPort <= 65535) {
    result('PASS', 'Veritabanı portu', 'Port sayısal ve geçerli aralıkta.');
} else {
    result('BLOCKER', 'Veritabanı portu', 'DB_PORT 1–65535 aralığında sayısal bir değer olmalı.');
}

if ($checkDb) {
    $connection = strtolower(trim((string) ($env['DB_CONNECTION'] ?? '')));
    if ($connection !== 'mysql') {
        result('BLOCKER', 'Veritabanı bağlantısı', '--check-db seçildi ancak DB_CONNECTION=mysql değil veya ayarlanmamış.');
    } elseif (!extension_loaded('pdo_mysql')) {
        result('BLOCKER', 'Veritabanı bağlantısı', 'pdo_mysql eklentisi bulunamadı.');
    } else {
        $host = trim((string) ($env['DB_HOST'] ?? '127.0.0.1'));
        $port = trim((string) ($env['DB_PORT'] ?? '3306'));
        $database = trim((string) ($env['DB_DATABASE'] ?? ''));
        $username = (string) ($env['DB_USERNAME'] ?? '');
        $password = (string) ($env['DB_PASSWORD'] ?? '');
        if ($database === '' || $username === '' || str_contains(strtolower($database), 'replace_with') || str_contains(strtolower($username), 'replace_with')) {
            result('BLOCKER', 'Veritabanı bağlantısı', 'DB_DATABASE veya DB_USERNAME boş/örnek değer. Değerler güvenlik nedeniyle gösterilmiyor.');
        } else {
            try {
                $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $database . ';charset=utf8mb4';
                $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
                $pdo->query('SELECT 1');
                result('PASS', 'Veritabanı bağlantısı', 'Salt okunur SELECT 1 başarılı; şema veya veri değiştirilmedi.');
                $pdo = null;
            } catch (Throwable $e) {
                result('BLOCKER', 'Veritabanı bağlantısı', 'Bağlantı başarısız. Gizli hata ayrıntıları yazdırılmadı; .env ve DB izinleri kontrol edilmeli.');
            }
        }
    }
} else {
    result('INFO', 'Veritabanı bağlantısı', 'Bağlantı denenmedi. İstenirse `php scripts/preflight.php --check-db` ile yalnızca SELECT 1 testi yapılabilir.');
}

$counts = ['PASS' => 0, 'INFO' => 0, 'WARN' => 0, 'BLOCKER' => 0];
echo "ORVIONART DEPLOYMENT PREFLIGHT\n";
echo "Sürüm: " . trim((string) @file_get_contents($root . '/VERSION')) . "\n";
echo "Mod: " . ($checkDb ? 'dosya + salt okunur DB bağlantısı' : 'dosya ve ortam kontrolleri') . "\n\n";
foreach ($results as [$level, $label, $detail]) {
    $counts[$level] = ($counts[$level] ?? 0) + 1;
    echo sprintf("[%s] %s — %s\n", $level, $label, $detail);
}
echo "\nÖZET: PASS={$counts['PASS']} INFO={$counts['INFO']} WARN={$counts['WARN']} BLOCKER={$counts['BLOCKER']}\n";
echo "Bu kontrol dosyaları/veritabanı şemasını değiştirmez ve migration çalıştırmaz.\n";
exit($counts['BLOCKER'] > 0 ? 1 : 0);
