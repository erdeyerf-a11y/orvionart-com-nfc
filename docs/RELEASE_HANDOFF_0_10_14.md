# ORVIONART 0.10.14 — teslimat sınırları

## Eklenenler

- `orvionart:inspect-qr-lifecycle` salt okunur QR/NFC denetimi.
- Token biçimi ve tekrarları, etkin hedefin herkese açık durumu, tarama sayacı/son tarama zamanı tutarlılığı, gelecekteki ve oluşturulma öncesi tarama zamanları denetlenir.
- Rapor token değerlerini içermez; örnek kayıt kimlikleriyle sınırlıdır.
- Dört Laravel Feature test senaryosu kaynak koduna eklendi.

## Statik kontroller

- PHP ve shell sözdizimi denetimleri
- Kaynak SHA-256 manifesti
- Migration statik denetimi ve fixture regresyon testleri
- Güvenlik preflight fixture testleri
- Gerçek `.env` ve iç içe ZIP bulunmaması
- Ödeme özelliğinin varsayılan kapalı olması

## Yapılmayan testler

Bu çalışma ortamında Composer ve `vendor/` bağımlılıkları bulunmadığından `composer validate` ve Laravel Feature testleri çalıştırılmadı. Gerçek veritabanı migration/rollback, Plesk staging kurulumu, HTTPS/tarayıcı/mobil kabul testleri ve yedekten geri yükleme tatbikatı yapılmadı.

## Güvenli teslimat kuralı

Bu ZIP canlı sunucuya yüklenmiş veya üretime hazır olduğu kanıtlanmış bir ürün değildir. Önce ayrı staging ortamında kurulum, yedekleme, migration, geri yükleme ve kabul testleri tamamlanmalıdır. Ödeme özelliği kapalı kalır; kullanım hakları ticari kararlar netleşmeden otomatik erişim/ücret davranışına dönüştürülmez. Manus'a henüz teslimat yapılmamıştır.
