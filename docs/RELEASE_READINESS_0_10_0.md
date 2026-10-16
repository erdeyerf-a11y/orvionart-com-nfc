# ORVIONART 0.10.0 — Kurulum ve staging hazırlık denetimi

## Amaç

`orvionart:release-check`, kontrollü staging kurulumu öncesinde PHP çalışma ortamı, bağımlılık dosyaları, uygulama yapılandırması, yazılabilir dizinler, veritabanı bağlantısı ve migration tutarlılığı için salt okunur bir rapor üretir.

Bu komut kurulum yapmaz, migration çalıştırmaz, dosya değiştirmez, yedek oluşturmaz ve ödeme işlemi başlatmaz.

## Kullanım

Metin raporu:

```bash
php artisan orvionart:release-check
```

JSON raporu:

```bash
php artisan orvionart:release-check --json
```

## Sonuçların anlamı

- `PASS`: Otomatik kontrollerde başarısız kontrol yok; bu, canlıya çıkış onayı değildir.
- `REVIEW_REQUIRED`: Otomatik kontrollerde bloklayıcı hata bulunmadı; uyarı veya gerçek sunucuda manuel doğrulama bekleyen maddeler var.
- `BLOCKED`: En az bir otomatik kontrol başarısız. Yayın hazırlığına geçmeden önce sorun giderilmelidir.

Komut, otomatik kontrollerde hata bulunursa `1`, hata yoksa `0` çıkış kodu döndürür. Manuel inceleme maddeleri her raporda yer alır; çıkış kodu sıfır olsa bile bunlar tamamlanmadan canlıya geçilmez.

## Otomatik kontrol alanları

- PHP sürümü ve `composer.json` içindeki `ext-*` gereksinimleri
- `composer.json`, `composer.lock`, `vendor/autoload.php`, `artisan`, `public/index.php`
- `APP_ENV`, `APP_KEY`, staging/production ortamında `APP_DEBUG=false`
- Staging/production `APP_URL` için HTTPS şeması
- Staging/production oturum çerezleri için Secure ve HttpOnly bayrakları
- `ORVIONART_PAYMENTS_ENABLED=false`
- Uygulama depolama ve cache dizinlerinin yazılabilirliği
- Veritabanı bağlantısı
- Migration tablosu, bekleyen migration dosyaları ve kaynağı bulunmayan uygulanmış migration kayıtları

## Gerçek sunucuda ayrıca doğrulanması gerekenler

1. Staging alt alan adı/dizini parola veya eşdeğer erişim kontrolü arkasında olmalı.
2. Web sunucusunun document root'u uygulama kökü değil yalnızca `public/` dizini olmalı.
3. `.env`, yedekler, loglar ve diğer gizli dosyalar HTTP üzerinden erişilememeli.
4. HTTPS sertifikası tarayıcıda doğrulanmalı; `APP_URL` ayarı tek başına HTTPS'nin çalıştığını kanıtlamaz.
5. Veritabanı ve medya yedekleri alınmalı ve ayrı bir staging hedefinde geri yükleme tatbikatı yapılmalı.
6. Admin girişi, deneyim yayınlama, QR/NFC, Demo Engine, medya, Site Builder, bakım modu ve mobil arayüz kabul testleri çalıştırılmalı.
7. Cron/scheduler görevlerinin gerçekten çalıştığı ayrıca doğrulanmalı.
8. Canlıya geçişten önce dosya listesi, migration planı, yedek ve geri dönüş adımları yazılı olarak onaylanmalı.

## Güvenlik sınırları

- Rapor veritabanı parolası, `APP_KEY`, token veya `.env` içeriği yazdırmaz.
- Veritabanı hatalarının ayrıntısı çıktıdan gizlenir; ayrıntı gerekiyorsa yetkili sunucu logları kontrollü incelenmelidir.
- Komut salt okunurdur. Eksikleri otomatik düzeltmez.
- Ödeme entegrasyonu bu sürümde uygulanmamıştır. `ORVIONART_PAYMENTS_ENABLED` varsayılanı `false` olarak tutulur; bu bayrak ödeme entegrasyonu oluşturmaz.
- Composer bağımlılıkları ve Laravel testleri bu geliştirme ortamında kurulup çalıştırılmadı. Komut gerçek staging ortamında tekrar çalıştırılmalıdır.
