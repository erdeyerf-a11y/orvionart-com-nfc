# ORVIONART V1.5 gereksinim/eksik iş matrisi — 0.10.54

Bu dosya statik kaynak incelemesine dayanır. Bir sınıf, rota veya test kaynağının varlığı çalışan işlevin kabul edildiği anlamına gelmez.

| Alan | Kaynakta bulunanlar | Kalan işler / kabul sınırı |
|---|---|---|
| Süper Admin | Merkezi admin rotaları, rol/oturum korumaları | Gerçek staging login, yetki ve tarayıcı kabulü |
| Deneyim Yönetimi | Deneyim modeli, yayın durumu ve kamu sayfası akışı | Uçtan uca create/edit/publish ve yaşam döngüsü testi |
| Medya Kütüphanesi | Medya kayıtları, özel dosya yolları, referans koruması | Büyük medya/disk kapasitesi ve gerçek sunucu izinleri |
| Parçalı video yükleme | Oturum, parça hash'i, sahiplik, devam etme istemcisi | Gerçek mobil bağlantı kesilmesi, paralel upload, 512 MiB kabul testi |
| Video işleme | FFprobe doğrulama, FFmpeg MP4 türevi ve JPEG thumbnail kaynakları | Laravel queue/runtime, gerçek yüklenen videolar, codec/Range/seek kabulü |
| QR/NFC | Kalıcı token/redirect ve durum korumaları | Gerçek QR baskı/tarama, NFC yazma ve cihaz kabulü |
| Demo Engine | İçerik, oturum, erişim ve retention kodu | Gerçek günlük/cihaz yolculuğu ve yük testi |
| Site Builder sayfaları | Taslak/yayın snapshot, revision/restore, SEO, menü, preview | Gerçek kullanıcı kabulü; tüm sayfa türlerinin içerik tasarımı |
| Site Builder tema | 0.10.54: arka plan/yüzey, font, buton, sticky header, footer düzeni | Logo/favicon, global sosyal görsel, ayrıntılı header/footer ve tüm V1.5 ayarları |
| Bölüm editörü | Mevcut güvenli section tipleri, aktiflik, yukarı/aşağı sıralama | V1.5 bileşenlerinin tamamı (video tanıtım, logo bandı, görsel hizalama, masaüstü/mobil görsel tercihi vb.) |
| Kullanım hakları | Entitlement kayıt defteri ve açıklama belgeleri | İş kuralı kullanıcı tarafından netleştirilmeden otomatik başlangıç/ücret kuralı eklenmemeli |
| Ödeme | Özellik bayrağı varsayılan kapalı | Sağlayıcı/iş kuralları kararı yok; entegrasyon etkinleştirilmemeli |
| SEO | Canonical, sitemap, noindex ve yayın snapshot alanları | Gerçek alan adı, robots/sitemap/canonical staging kabulü |
| Yedek/geri yükleme | Operatör betikleri ve doğrulama dokümanı | İzole DB'de gerçek backup/restore tatbikatı |
| Dağıtım | Preflight, manifest, Release Gate, ZIP/SHA-256 | Plesk staging, Composer install, migration/rollback, HTTP/HTTPS ve geri dönüş testi |
| Veritabanı | Mevcut composer/migration'lar PDO MySQL/MariaDB hedefli | PostgreSQL tercihiyle çelişki var; final kurulum öncesi açık mimari kararı gerekir |

## Bu sürümde
0.10.54 Site Builder tema seçeneklerini güvenli allowlist üzerinden genişletir. Laravel Feature testleri, gerçek DB ve tarayıcı kabul testleri çalıştırılmış sayılmaz. Manus'a teslimat ve canlı sunucu dağıtımı yapılmamıştır.
