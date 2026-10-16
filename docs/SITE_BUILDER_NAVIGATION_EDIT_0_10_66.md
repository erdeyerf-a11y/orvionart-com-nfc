# Site Builder navigasyon bağlantısı düzenleme — 0.10.66

## Kullanıcı akışı
- Süper Admin, Site Builder → Menü ve navigasyon ekranında mevcut her bağlantının `Düzenle` alanını açabilir.
- Görünen adı, konumu (ana menü/footer), yayınlanmış sayfa veya HTTPS hedefini ve sıralama değerini güncelleyebilir.
- Sayfa hedefi ve haricî URL aynı anda seçilemez; en az bir hedef gerekir.

## Güvenlik ve veri bütünlüğü
- Haricî hedefler HTTPS ile sınırlıdır.
- Hedef sayfa, kaydetme transaction'ı içinde satır kilidi altında tekrar kontrol edilir ve yayınlanmamış sayfa bağlantısı reddedilir.
- Ana menüye bağlanan sayfanın yayınlanmış `menüde göster` izni bulunmalıdır.
- Bağlantı kaydı ve `site_navigation.updated` denetim kaydı aynı transaction içinde yazılır. Denetim kaydı başarısız olursa değişiklik geri alınır.
- Mevcut etkin/pasif durumu düzenleme sırasında korunur. Sıralama alanı 0–100000 aralığı ile sınırlıdır.
- Yeni migration gerekmez.

## Doğrulama sınırı
- Feature test kaynakları: başarılı düzenleme/audit, taslak hedefi reddetme ve audit hatasında rollback.
- Bu paket üretim ortamında Composer/Laravel bağımlılıkları olmadan oluşturulduysa bu Feature testleri çalıştırılmış sayılmaz. Gerçek DB ve Plesk kabul testi ayrıca yapılmalıdır.
