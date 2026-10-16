# QR/NFC yönetiminde arama ve filtreleme — 0.10.63

## Davranış
- Yönetim panelindeki QR/NFC bağlantı kayıtları etiket metnine veya bağlı deneyimin başlığına göre aranabilir.
- Durum filtresi `Tümü`, `Etkin` ve `Devre dışı` seçeneklerini destekler.
- Arama ve durum filtresi aynı anda uygulanır.
- Sayfalama bağlantıları mevcut sorgu parametrelerini korur; Temizle bağlantısı varsayılan listeyi açar.
- Arama metni en fazla 120 karakterdir; durum değeri izinli üç seçenekle sınırlandırılır.
- Mobil ekranda filtre alanları tek sütun olarak gösterilir.

## Güvenlik ve veri
- Özellik mevcut yönetici korumalı `admin.qr-links.index` rotasını kullanır.
- Yeni veritabanı tablosu veya migration eklenmedi. Arama yalnızca etiket ve deneyim başlığı alanlarını sorgular; token/ziyaretçi bilgileri üzerinden ek veri açığa çıkarmaz.

## Doğrulama sınırı
- Laravel Feature test kaynağı arama, durum filtresi ve sonuçların birbirinden ayrılmasını kapsar.
- Bu çalışma ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmadı. PHP lint ve statik Release Gate çalıştırmaları gerçek DB, Plesk veya tarayıcı kabulünün yerine geçmez.
