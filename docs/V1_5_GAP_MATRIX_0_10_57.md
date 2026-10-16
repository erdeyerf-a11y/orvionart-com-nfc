# ORVIONART V1.5 gereksinim/eksik iş matrisi — 0.10.58

Bu dosya kaynak kod statik incelemesine dayanır. Kod veya test dosyasının varlığı gerçek sunucuda çalışan işlevin kabul edildiği anlamına gelmez.

| Alan | Kaynakta bulunanlar | Kalan zorunlu doğrulama / iş |
|---|---|---|
| Süper Admin | Tek panel, giriş/oturum/CSRF, yetki middleware'i, dashboard | Gerçek Plesk giriş/çıkış ve yetki testi; kurulum sonrası ilk giriş |
| Deneyim Yönetimi | Başlık, kısa açıklama, metin, taslak/zamanlama/yayın/arşiv, kalıcı public ID | Gerçek Laravel CRUD ve yayın durumu kabulü; V1.5'teki günlük içerik modelinin kapsamı ayrıca tamamlanmalı |
| Basit kullanım akışı | Dashboard'da adım adım yönlendirme ve gerçek kayıt sayıları; deneyimden medyaya kısayol | Tarayıcıda akış ve mobil kabul testi |
| Medya/video | Özel depolama, deneyime bağlama, 512 MB parçalı video yükleme, işleme kaynakları | Composer/runtime, gerçek upload, worker/FFmpeg, Plesk sınırları ve cihaz testi |
| QR/NFC | Kalıcı token URL, QR SVG indirme, NFC için aynı URL, durum kontrolü/sayaç | Gerçek HTTPS URL'de QR telefon taraması ve NFC etiketi yazma testi |
| Ziyaretçi sayfası | Deneyim metni, medya galerisi, taslak/pasif erişim koruması | Gerçek cihaz, medya ve gizlilik kabul testi |
| Demo Engine | Ayrı demo içerik havuzu, günlük atama ve süre kuralları kaynakları | Gerçek cihaz ve zamanlanmış görev kabulü |
| Site Builder | Sayfa, bölüm, navigasyon, tema, logo/favicon, yayın snapshot'ı/revizyon | Global sosyal görsel ve V1.5 bölüm türlerinin kalanları; tarayıcı kabulü |
| Kurulum/dağıtım | `.env.example`, preflight, staging/backup yardımcı betikleri, CLI Super Admin oluşturma | Composer kurulumu, DB driver kararı/uyumu, gerçek migration/rollback, yedek geri yükleme ve HTTPS staging URL'si |
| Veritabanı | Kaynak `composer.json` ve migration'lar MySQL/MariaDB hedefli | Gerçek Plesk MariaDB sürümü ve erişimi doğrulanmalı; PostgreSQL varsayımı yapılmamalı |
| Son teslimat | Sürümlü kaynak ZIP, manifest ve SHA-256 üretilebilir | Laravel runtime testleri ve V1.5 son kabul testleri geçmeden “çalışır/production-ready” denmemeli |

## 0.10.57 değişikliği
Bu sürüm yeni veritabanı migration'ı eklemez. Yönetici kullanımını sadeleştirir; deneyimden medya yükleme akışını kısaltır ve yükleme sonrası doğru deneyim ekranına geri döner. Canlı sunucuya dağıtım yapılmamıştır.


## 0.10.58 değişikliği
- Deneyim listesinde QR/NFC ve medya özelliklerini yanlışlıkla kapalı gösteren metin düzeltildi.
- Kaynak kodundaki QR/NFC akışı `docs/QR_NFC_SOURCE_AUDIT_0_10_58.md` belgesinde izlenebilir biçimde açıklandı.
- Bu değişiklik gerçek sunucu kabulü değildir. Composer/runtime, veritabanı, HTTPS alan adı ve gerçek telefon testi hâlâ Manus tarafından yapılmalıdır.
