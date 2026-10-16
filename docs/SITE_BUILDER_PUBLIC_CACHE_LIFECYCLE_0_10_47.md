# Site Builder kamu sayfalarında önbellek yaşam döngüsü — 0.10.47

## Sorun

Kamuya açık Site Builder sayfaları `public, max-age=60` ile sunuluyordu. Sayfa yayından kaldırıldığında, taslağa alındığında veya arşivlendiğinde tarayıcılar ve paylaşımlı önbellekler önceki yanıtı bir süre daha sunabilirdi. Bu davranış, veritabanındaki güncel yayın durumu denetimiyle çelişir.

## Değişiklik

- Ana sayfa ve Site Builder sayfalarının render yanıtları `Cache-Control: no-store, private` döndürür.
- Yayın snapshot'ı, `isPubliclyAvailable()` ve taslak/arsiv 404 kontrolleri aynı kalır.
- Bu değişiklik yalnızca yanıt önbelleklemesini sınırlar; yayın, slug, SEO veya bakım modu kurallarını değiştirmez.
- `SiteBuilderTest` içinde ana sayfa, standart sayfa ve deneyim listesi sayfası için başlık regresyon doğrulamaları eklendi.

## Test sınırı

Bu teslimatta PHP sözdizimi ve statik release gate çalıştırılabilir. Laravel Feature testleri Composer bağımlılıkları ve `vendor/autoload.php` bulunmadığı için çalıştırılmış sayılmaz. Gerçek tarayıcı/CDN davranışı ve sunucu kabul testi ayrıca yapılmalıdır.
