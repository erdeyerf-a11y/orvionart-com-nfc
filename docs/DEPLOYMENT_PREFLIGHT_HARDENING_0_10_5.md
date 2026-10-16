# ORVIONART 0.10.5 — Sunucu ön kontrolü güvenlik sıkılaştırması

## Amaç

`php scripts/preflight.php` mevcut, salt okunur ön kontrolünü genişletir. Bu araç dağıtım yapmaz, migration çalıştırmaz, dosya yazmaz ve veritabanına bağlanmaz; yalnızca `--check-db` verilirse yapılandırılmış MySQL'e `SELECT 1` gönderir.

## Yeni kontroller

- `APP_KEY`: boş/örnek değer engellenir; `base64:` biçimindeki anahtarın çözümlenmiş uzunluğu 32 bayt olmalıdır. Anahtarın kendisi hiçbir zaman yazdırılmaz.
- `APP_ENV`: `staging` veya `production` beklenir; `local` engellenir.
- `APP_URL`: staging/production için geçerli HTTPS URL zorunludur; URL değeri rapora basılmaz.
- `APP_DEBUG`: yalnızca açıkça `false` veya `0` kabul edilir.
- `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true` ve `SESSION_SAME_SITE=lax|strict` doğrulanır.
- `ORVIONART_PAYMENTS_ENABLED` varsayılan olarak kapalı kabul edilir; açık değer engellenir.
- `.env` izinleri denetlenir; herkesin okuyabildiği dosya engellenir. Grup okuma izni yalnızca uyarı verir çünkü bazı Plesk/PHP-FPM kurulumlarında güvenli grup modeli kullanılabilir.
- `--check-db` ile veritabanı adı/kullanıcısı boş veya örnek değer ise bağlantı denenmeden engellenir.

## Kullanım

Kaynak paketin kök dizininde:

```bash
php scripts/preflight.php
```

Yalnızca bağlantıyı doğrulamak için ek seçenek:

```bash
php scripts/preflight.php --check-db
```

İkinci komut yalnızca `SELECT 1` yapar. Şema/veri değiştirmez ve migration çalıştırmaz.

## Yorumlama

- `PASS`: bu kontrol geçti.
- `INFO`: bilgi amaçlı; kontrol çalıştırılmadı.
- `WARN`: manuel değerlendirme gerekir.
- `BLOCKER`: staging kabulünden önce düzeltilmelidir.

Bu betiğin geçmesi, uygulamanın bütünüyle test edildiği veya canlıya alınmaya hazır olduğu anlamına gelmez. Gerçek PHP-FPM/web sunucusu, Plesk document root, HTTPS, parola koruması, veritabanı yetkileri, migration, yedek/geri yükleme ve tarayıcı testleri ayrıca doğrulanmalıdır.
