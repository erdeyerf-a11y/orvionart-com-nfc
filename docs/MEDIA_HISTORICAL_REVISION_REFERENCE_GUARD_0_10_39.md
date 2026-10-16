# Medya silme koruması: Site Builder geçmiş sürümleri — 0.10.39

## Risk

Site Builder, taslak ve yayın snapshot'larının yanı sıra geçmiş revision snapshot'larını da saklar. Bir görsel mevcut taslaktan ve yayından kaldırılmış olsa bile geçmiş bir snapshot onu bölüm görseli, kart öğesi görseli veya sosyal paylaşım görseli olarak tutuyor olabilir. Medya dosyasının silinmesi bu sürümün daha sonra geri yüklenmesini bozabilir.

## Değişiklik

`MediaAsset::isReferencedBySitePage()` artık ilgili sayfanın güncel taslak/yayın alanlarını ve `SitePageRevision` snapshot kayıtlarını kontrol eder. Bölüm görselleri, iç içe `items` görselleri ve sosyal paylaşım görselleri taranır. Revision kayıtları 100'lük parçalarla okunur ve ilk referans bulunduğunda tarama durur. Bu koruma `canBeDeletedSafely()` üzerinden medya silme yoluna uygulanır.

Bir tarihsel revision referansı bulunduğunda medya silme engellenir. Bu sürüm otomatik revision temizleme veya medyayı revision'lardan otomatik ayıklama yapmaz; geçmiş sürümler kullanıcının açıkça yöneteceği içerik olarak korunur.

## Regresyon kaynakları

`tests/Feature/MediaAssetDeletionSafetyTest.php` içinde iki test kaynağı eklendi:

- Güncel sayfa içeriği artık görseli kullanmasa bile geçmiş bölüm snapshot'ı referans veriyorsa silme engellenir.
- Yalnızca geçmiş sosyal paylaşım görseli alanı referans veriyorsa silme engellenir.

## Doğrulama sınırı

PHP sözdizimi ve statik Release Gate kontrolleri çalıştırılabilir. Bu çalışma ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmış sayılmaz. Veritabanı davranışı staging ortamında doğrulanmalıdır.
