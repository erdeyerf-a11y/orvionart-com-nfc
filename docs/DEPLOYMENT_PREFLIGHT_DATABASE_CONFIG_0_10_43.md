# ORVIONART 0.10.43 — Veritabanı yapılandırma ön kontrolü

## Sorun

Önceki ön kontrol akışında DB host/name/user/password alanlarının örnek değer olup olmadığı yalnızca `--check-db` bağlantı kontrolü seçildiğinde değerlendiriliyordu. Bu nedenle yalnızca dosya kontrolleri çalıştırılan bir `.env` kopyası, veritabanı alanları henüz doldurulmamış olsa bile bu özel engeli göstermeyebilirdi.

## Değişiklik

`scripts/preflight.php`, DB bağlantısı açmadan önce her çalıştırmada:

- `DB_CONNECTION=mysql` olmasını ister; mevcut `composer.json` `ext-pdo_mysql` gerektirir.
- DB host, veritabanı adı, kullanıcı ve parola alanlarının boş ya da bilinen yer tutucu biçiminde olmadığını kontrol eder.
- `DB_PORT` değerinin 1–65535 aralığında sayısal olmasını ister.
- Gizli veya yapılandırma değerlerini rapora yazdırmaz.
- `--check-db` seçeneğinde ayrıca mevcut salt okunur `SELECT 1` kontrolünü çalıştırır.

## Test

`bash scripts/test-preflight.sh`, güvenli örnek ortamı, debug/ödeme açık senaryosunu ve DB kullanıcı/parola yer tutucularının bağlantı testi istenmeden engellenmesini kontrol eder. Testler yalnızca geçici fixture dizini kullanır; gerçek uygulama `.env` dosyasına veya veritabanına dokunmaz.

## Sınırlar

Bu kontrol veritabanı bilgilerinin doğru olduğunu veya bağlantının başarılı olduğunu kanıtlamaz. Gerçek bağlantı için `php scripts/preflight.php --check-db`, ardından izole staging ortamında migration ve uygulama kabul testleri gerekir.
