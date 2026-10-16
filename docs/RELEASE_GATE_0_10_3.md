# ORVIONART 0.10.3 — Sürüm kapısı ve kabul kontrolü

## Amaç

`scripts/release-gate.sh`, ZIP tesliminden önce kaynak kodu için tekrarlanabilir statik kontroller uygular. Bağımlılıklar kuruluysa Composer doğrulamasını ve Laravel test paketini de çalıştırır. Betik deploy etmez, migration çalıştırmaz, uygulama/veritabanı verisini değiştirmez ve sunucuda test yapılmış gibi bir sonuç üretmez.

## Kullanım

Kaynak paketi içinde:

```bash
bash scripts/release-gate.sh
```

Composer, `vendor/` ve Laravel testleri bulunması zorunlu olsun istenirse:

```bash
bash scripts/release-gate.sh --strict
```

Normal modda Composer/runtime testleri yoksa bunlar `WARN` olarak raporlanır. `--strict` modda bu eksikler başarısızlık sayılır. Başarılı statik kontroller, canlıya çıkış onayı değildir.

## Denetimler

- PHP 8.3+ ve `composer.json` JSON biçimi
- Proje içindeki PHP kaynaklarının sözdizimi
- `scripts/` altındaki Bash dosyalarının sözdizimi
- `RELEASE_MANIFEST.json` dosya hash/uzunluk doğrulaması
- Gerçek `.env`/`.env.*` dosyalarının teslimat ağacında bulunmaması (`.env.example` hariç)
- Kaynak ağacında eski ZIP'lerin iç içe gömülmemesi
- Ödeme özelliğinin varsayılan olarak kapalı olması
- Ortamda varsa `composer validate` ve `php artisan test`

## Sunucu kabulü için ayrıca gerekli

Gerçek ve izole staging ortamında şu maddeler tamamlanmadan canlıya geçilmez:

1. PHP/Composer/uzantı sürümlerini ve web sunucusu document root'unun yalnızca `public/` olduğunu doğrula.
2. Staging için ayrı uygulama dizini, ayrı veritabanı ve ayrı `.env` oluştur; canlı veritabanı bilgilerini kullanma.
3. HTTPS, parola/erişim kısıtlaması, `APP_DEBUG=false`, benzersiz `APP_KEY`, Secure/HttpOnly çerezler ve `noindex` davranışını gerçek tarayıcıda doğrula.
4. Yedek al; yedek dosyalarının hash'ini doğrula ve ayrı bir staging veritabanına geri yükleme tatbikatı yap.
5. Önce migration durumunu incele; yalnızca doğru staging veritabanı olduğu kesinleşince migration çalıştır.
6. Süper Admin giriş/çıkış, yetki reddi, CSRF, deneyim yayın durumu, QR/NFC, medya erişimi, Demo Engine, Site Builder, bakım modu ve mobil/tablet/masaüstü akışlarını test et.
7. Logları ve hata yanıtlarını kontrol et; gizli değerlerin görünmediğini doğrula.
8. Herhangi bir başarısızlıkta canlıya çıkışı durdur. Geri dönüş yöntemi ve sorumlusu önceden belli olmalıdır.

## Bilinen sınır

Bu teslimat hazırlanırken Composer mevcut olmadığı için Laravel runtime/feature testleri bu çalışma alanında çalıştırılamadı. Gerçek sunucuya bağlanılmadı; staging kurulumu, migration, yedek geri yükleme, tarayıcı ve mobil kabul testleri yapılmış değildir. Bu nedenle sonuç yalnızca gerçekten çalıştırılan yerel kontrollerle sınırlıdır.
