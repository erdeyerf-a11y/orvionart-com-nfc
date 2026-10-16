# ORVIONART 0.10.18 — Paylaşılan medya yolu silme koruması

## Amaç

Birden fazla `media_assets` satırı aynı depolama diski ve dosya yolunu gösteriyorsa, satırlardan biri silinirken ortak fiziksel dosyanın da silinmesi diğer kaydı boşa düşürebilir.

## Uygulama

- `MediaAsset::isReferencedByAnotherAsset()` aynı `disk` ve `path` değerleriyle başka bir kayıt arar; geçerli kaydın kendi kimliğini hariç tutar.
- `canBeDeletedSafely()` bu kontrolü deneyim, Site Builder ve Demo Engine referanslarıyla birlikte uygular.
- Aynı göreli yolun farklı diskte bulunması eşleşme sayılmaz.
- Silme denetimi otomatik olarak hiçbir kaydı birleştirmez veya değiştirmez.

## Test kaynakları

`tests/Feature/MediaAssetDeletionSafetyTest.php` aynı disk/yol için iki kaydın da silinemez sayılmasını ve farklı diskteki aynı yolun yanlış eşleşme oluşturmamasını kapsar.

## Sınırlar

Bu değişiklik uygulama düzeyinde bir korumadır. Eşzamanlı yükleme/silme yarışları ve gerçek veritabanı kilit davranışı Composer/Laravel ortamı kurulduğunda ayrıca test edilmelidir. Bu sürümde canlı sunucuya dağıtım yapılmamıştır.
