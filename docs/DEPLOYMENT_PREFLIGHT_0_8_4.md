# ORVIONART 0.8.4 — Kurulum öncesi denetim

## Amaç

Bu aşama, gerçek sunucuya bağlanmadan önce sunucuda çalıştırılabilecek, salt okunur bir ön kontrol aracı sağlar. Script dosya/veritabanı şemasını değiştirmez, migration çalıştırmaz ve gizli `.env` değerlerini ekrana yazmaz.

## Komutlar

Uygulama kök dizininde:

```bash
php scripts/preflight.php
```

Bu komut PHP sürümünü, `composer.json` bağımlılıklarını, Composer erişimini, `.env`/`APP_KEY`/`APP_DEBUG` durumunu, temel dosyaları ve yazılabilir dizinleri denetler. Eksik `.env`, eksik PHP eklentileri veya debug ayarındaki kritik sorunlar `BLOCKER` üretir.

Veritabanı bağlantısını yalnızca okuma amaçlı ayrıca kontrol etmek için:

```bash
php scripts/preflight.php --check-db
```

İkinci komut, `.env` içindeki DB ayarlarını kullanarak sadece `SELECT 1` çalıştırır. Tablo oluşturmaz, veri yazmaz ve migration çalıştırmaz. Veritabanı bilgilerini veya bağlantı hatasının gizli ayrıntılarını yazdırmaz.

## Yorumlama

- `PASS`: Bu kontrol geçildi.
- `INFO`: Bilgilendirme; örneğin DB kontrolünün özellikle istenmediği.
- `WARN`: Kurulum sırasına bağlı veya manuel inceleme isteyen durum.
- `BLOCKER`: Kuruluma geçmeden önce çözülmesi gereken engel.

Script'in başarılı çıkış kodu vermesi, uygulamanın çalıştığını veya güvenlik kabul testlerini geçtiğini kanıtlamaz. Composer bağımlılık kurulumu, migration, Super Admin girişi, medya erişimi, QR/NFC, Demo Engine, mobil görünüm, yedekten geri dönüş ve kontrollü staging kabul testleri ayrıca yapılmalıdır.

## Kontrollü kurulum sırası

1. Sunucuda gerçek PHP sürümü, PHP eklentileri, Composer, MySQL/MariaDB ve Plesk/web kökü doğrulanır.
2. Canlı sistemden ayrı dizin, alt alan adı ve ayrı staging veritabanı belirlenir.
3. Kaynak dosyalar staging dizinine açılır; web document root yalnızca `public/` dizinini göstermelidir.
4. `.env` yalnızca sunucuda oluşturulur. Gerçek sırlar ZIP içine konmaz. `APP_DEBUG=false`, staging için ayrı DB ve ayrı `APP_KEY` kullanılır.
5. `php scripts/preflight.php` çalıştırılır; tüm BLOCKER kayıtları çözülür.
6. Gerekli bağımlılıklar staging dizininde kurulur. Composer sürümü ve PHP sürümü aynı hedef ortam için doğrulanır.
7. `php scripts/preflight.php --check-db` çalıştırılır. Bu yalnızca bağlantıyı kontrol eder.
8. Veritabanı yedeği ve geri yükleme yolu doğrulanmadan migration çalıştırılmaz.
9. Staging alanı HTTPS, parola/erişim koruması ve `noindex` ile korunur.
10. Temel ve kabul testleri tamamlanmadan canlıya geçilmez.

## Geri alma ilkesi

- Uygulama dosyaları sürüm klasörlerinde tutulur; canlı dizinin üstüne kontrolsüz kopyalama yapılmaz.
- Migration öncesinde veritabanı yedeği alınır ve geri yükleme yolu doğrulanır.
- Geri alma adımı, hedef sunucunun gerçek dosya yolları ve Plesk yapılandırması görülmeden varsayım olarak uygulanmaz.
- Bu proje aşamasında sunucuya bağlantı/dağıtım yapılmamıştır.
