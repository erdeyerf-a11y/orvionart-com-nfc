# QR/NFC kullanılamayan içerik ekranı — 0.10.62

## Davranış
- Token hiç bulunmuyorsa veya hedef kaydı bozuksa mevcut 404 davranışı korunur.
- Bilinen QR bağlantısı kapalıysa ziyaretçiye genel, içerik ayrıntısı açığa çıkarmayan ORVIONART sayfası gösterilir (HTTP 404).
- İçeriğin `ends_at` zamanı geçmişse süre sonu mesajı gösterilir (HTTP 410 Gone).
- Taslak, arşiv veya yayın zamanı gelmemiş içerik için aynı genel erişim sayfası 404 ile gösterilir.
- Başarısız erişimler `scan_count` ve `last_scanned_at` alanlarını değiştirmez.
- Başarılı yönlendirme, `/go/{token}` kalıcı QR/NFC adresi ve sayaç davranışını korur.
- Hata ekranları `no-store`, `noindex`, `no-referrer` başlıklarıyla sunulur.

## Doğrulama sınırı
Test kaynakları güncellenmiştir. Composer/Laravel çalışma zamanı yoksa Feature testleri yürütülmüş sayılmaz. Gerçek Plesk/MariaDB, tarayıcı ve fiziksel QR/NFC kabul testi ayrıca yapılmalıdır.
