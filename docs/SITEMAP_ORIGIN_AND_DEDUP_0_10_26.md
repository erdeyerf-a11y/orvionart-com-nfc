# Sitemap köken ve tekrar koruması — 0.10.26

## Amaç

Sitemap içindeki URL'lerin yapılandırılmış uygulama kökeniyle tutarlı olmasını sağlamak; dış canonical adresleri ve yinelenen konumları arama motoru sitemap'ine taşımamak.

## Değişiklikler

- URL mutlak olmalı ve `APP_URL` ile aynı şema, host ve etkin portu kullanmalı.
- URL kullanıcı adı/parola veya fragment içeriyorsa sitemap'e alınmaz.
- Farklı kökene giden canonical URL dışarıda bırakılır.
- Aynı `loc` birden çok kez oluşursa tek kayıt tutulur ve daha yeni `lastmod` korunur.
- `scheduled` durumundaki deneyimlerde `scheduled_at`, `published` durumundakilerde `published_at` önceliklidir.

## Test kapsamı

`tests/Feature/SiteBuilderTest.php` şu davranışlar için test kaynakları içerir:

1. Harici canonical URL'nin dışarıda bırakılması.
2. Aynı kökene ait tekrarlanan canonical URL'nin tek kayıt olarak çıkması.
3. Zamanlanmış deneyim için `scheduled_at` kullanılması.

Bu geliştirme ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmamıştır. PHP sözdizimi ve statik release kontrolleri, uygulamanın gerçek HTTP/runtime davranışını kanıtlamaz. Staging üzerinde kabul testi gereklidir.
