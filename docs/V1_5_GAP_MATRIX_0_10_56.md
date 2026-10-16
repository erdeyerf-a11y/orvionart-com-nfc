# ORVIONART V1.5 gereksinim/eksik iş matrisi — 0.10.56

Bu dosya statik kaynak incelemesine dayanır. Kaynak kod veya test dosyasının bulunması, gerçek sunucuda çalışan işlevin kabul edildiği anlamına gelmez.

| Alan | Mevcut kaynak | Kalan doğrulama |
|---|---|---|
| Temel deneyim içeriği | Başlık, kısa açıklama, metin, taslak/yayın/zamanlama, mobil ziyaretçi sayfası | Laravel çalışma zamanı ve gerçek tarayıcıda oluşturma/yayınlama |
| QR/NFC | Deneyim ekranından QR/NFC hedefi oluşturma; kalıcı URL; QR SVG indirme; NFC için URL kopyalama; okutma sayısı; taslak/arşiv erişim koruması | Laravel Feature testleri, gerçek alan adı/HTTPS, telefonla QR tarama ve NFC etiket yazma testi |
| Medya | Deneyime bağlı fotoğraf/video teslimi; güvenli medya kütüphanesi; video işleme kaynakları | Gerçek medya yükleme, worker, codec ve cihaz kabulü |
| Süper Admin | Tek yönetim paneli, rol/oturum kontrolleri | Plesk staging giriş ve yetki testi |
| Demo Engine | Deneyimlerden ayrı demo akışı ve ayarları | Gerçek cihaz/günlük içerik kabulü |
| Site Builder | Sayfalar, yayın snapshot'ı, revizyon, tema ayarları, logo/favicon | Global sosyal görsel, kalan bölüm bileşenleri, tarayıcı kabulü |
| Kullanım hakkı/ödeme | Kullanım hakkı kaydı; ödeme varsayılan kapalı | İş kuralı kesinleşmeden otomasyon/ödeme etkinleştirilmemeli |
| Veritabanı | Composer ve migration'lar MySQL/MariaDB hedefli | Önceden belirtilen PostgreSQL tercihiyle çelişki final kurulumdan önce karara bağlanmalı |
| Gerçek sunucu | Bu sürümde dağıtım yapılmadı | Plesk PHP 8.3.34, Composer, DB migration/rollback, HTTPS, yedek/geri yükleme ve kabul testi |

## Bu sürümde
0.10.56, kullanıcıyı içerik oluşturma ekranından ayrı QR/NFC menüsüne göndermeden bağlantı hazırlamasını sağlar. QR kod, bağlantı hedefi ve NFC adresi aynı kalıcı URL'yi kullanır. NFC etiketinin fiziksel olarak yazılması uygulama tarafından otomatik yapılmaz. Composer/Laravel çalışma zamanı yoksa Feature testleri çalıştırılmış sayılmaz.
