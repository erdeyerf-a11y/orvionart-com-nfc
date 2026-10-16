# ORVIONART V1.5 — Modül Durum ve Eksik İşler Matrisi (0.10.53)

**Tarih:** 2026-10-15  
**Kaynak:** ORVIONART 0.10.50 kaynak ZIP'i, 0.10.51–0.10.53 birikimli değişiklikleri ve V1.5 ana talimatı.  
**Durum kuralı:** Kaynakta sınıf/rota/test bulunması, gerçek ortamda çalıştığının kabul edildiği anlamına gelmez. Runtime, gerçek DB, Plesk ve cihaz kabul testleri bu pakette yapılmamıştır.

| Modül / V1.5 alanı | Kaynakta görülen durum | Kabul için kalan iş |
|---|---|---|
| Süper Admin / oturum / CSRF / yetki | Yönetici giriş denetleyicisi, middleware ve feature-test kaynakları mevcut. | Composer/vendor ile Laravel testleri; gerçek staging giriş/yetki testi. |
| Deneyim Yönetimi | CRUD, yaşam döngüsü, public player ve test/denetim kaynakları mevcut. | Migration/runtime doğrulaması; gerçek tarayıcıda taslak/pasif/süresi dolmuş içerik kabulü. |
| Medya Kütüphanesi | Özel `local` disk, medya metadata'sı, önizleme ve silme/referans güvenliği kaynakları mevcut. | Gerçek dosya izinleri, depolama kapasitesi, HTTP sunumu ve staging kabul testleri. |
| Parçalı video yükleme | 4 MiB parça, 512 MiB oturum sınırı, 24 saatlik oturum, sahiplik, idempotent parça ve birleştirme hash/MIME/boyut kontrolleri mevcut. 0.10.51'de istemci-sunucu SHA-256 eşleştirmesi, tamamlanmış oturumu yeniden kullanma ve commit sonrası güvenli temizlik düzeltmeleri eklendi. 0.10.52'de medya başına kuyruk çakışma kilidi ve FFprobe protokol izin listesi eklendi; 0.10.53'te FFmpeg dönüştürme, JPEG küçük resim ve eski geçici çıktı temizliği kaynakları eklendi. | FFprobe arka plan doğrulama kuyruğu ve retry arayüzü 0.10.51'de eklendi. Sentetik örnekle FFmpeg→FFprobe→JPEG CLI smoke testi geçti; gerçek yükleme/queue entegrasyonu, desteklenen codec profili için tarayıcı kabulü, mobil ağ kesilmesi ve Plesk worker kabul testleri hâlâ eksik. |
| QR/NFC | Kalıcı yönlendirme token'ı, QR üretimi/indirme, yaşam döngüsü ve test kaynakları mevcut. NFC için aynı kalıcı URL kullanılabilir. | Gerçek QR tarama kabulü ve fiziksel NFC etiketine URL yazma testi; taslak/pasif/süre sonu sızıntı testleri. |
| Demo Engine | Yönetim/kamu akışı, medya koruması, yaşam döngüsü ve temizlik/denetim kaynakları mevcut. | Laravel runtime ve gerçek ziyaretçi akışı kabul testi. |
| Merkezi Site Builder | Sayfa, yayın/taslak, snapshot/revision, geri alma, menü, yönlendirme, SEO, medya ve noindex korumaları için kaynak/test/denetim mevcut. | Her kamu sayfası bölümünün yalnızca Süper Admin Site Builder'dan yönetildiğini gerçek tarayıcıda doğrulama; eksik görsel editör kontrolleri/boş-hata durumları varsa tamamlama; staging kabul testi. |
| Ücretsiz kullanım hakları | Kullanım hakkı modülü ve registry kaynakları mevcut. | İlk yıl/başlangıç/süre/yararlanıcı kuralları ticari karar olarak netleştirilmeli; bu belge belirsiz kuralları varsaymaz. |
| Ödeme | Varsayılan kapalı güvenlik kontrolleri mevcut. | Sağlayıcı ve ticari kararlar belirlenmeden entegrasyon açılmayacak; ödeme akışı runtime testleri yapılmadı. |
| Yedekleme / geri yükleme | Yedek oluşturma/doğrulama betikleri ve güvenlik regresyonları mevcut. | Hazırlık DB'sinde gerçek backup/restore kabul testi; canlıya geri yükleme yapılmadı. |
| Sistem sağlığı / cron / worker | Health, scheduler ve denetim komutları mevcut. | Plesk cron, worker/queue, disk ve PHP limitleri gerçek sunucuda yapılandırılıp gözlemlenmeli. |
| Sürümleme / rollback / teslimat | VERSION, release manifest, release gate ve paket kontrolleri mevcut. | Gerçek staging dağıtımı, migration rollback provası ve kontrollü geri alma testi. |
| Mobil uyum / PWA | Bazı sayfalar responsive olarak hazırlanmış olabilir; bu pakette gerçek mobil cihaz testi yok. | PWA manifest/service worker/install akışı envanteri; yatay taşma, dokunma, video ve QR akışlarının gerçek cihaz kabulü. |
| Gerçek sunucu aşama 0–1 | Bu çalışma yalnızca yerel kaynak ZIP'i üzerinde yapıldı. | Kullanıcının belirttiği Plesk/PHP 8.3.34 üzerinde sunucu envanteri, ayrı staging dizini/DB, HTTPS ve erişim kısıtlaması. Canlı sunucuya erişildiği iddia edilmez. |
| Veritabanı tercihi | Paket `pdo_mysql` ve MySQL varsayılanları kullanıyor. | Önceki Laravel + PostgreSQL tercihi ile mevcut MySQL tabanı final kurulumdan önce açık karara bağlanmalı; sessizce değiştirilmemeli. |

## 0.10.53 doğrulanmış statik sonuçlar

- PHP lint: 145 dosya PASS, 0 FAIL.
- Shell syntax: PASS.
- Inline JavaScript parser kontrolü: 1 script PASS (Blade yönergeleri yalnızca parser kontrolü için yer tutucuyla değiştirildi).
- Migration statik audit: 16 dosya, BLOCKER=0, REVIEW=0.
- Release Gate: PASS=61, WARN=2, FAIL=0.
- Inline JavaScript parser: 1 script PASS.
- Sentetik 2 saniyelik video için FFmpeg → FFprobe → JPEG CLI smoke testi: PASS (H.264/yuv420p + AAC, video/mp4, image/jpeg).
- ZIP bütünlük ve release manifest: PASS.
- Uyarılar: Composer bulunmadığı için `composer validate` çalışmadı; `vendor/autoload.php` bulunmadığı için Laravel Feature testleri çalışmadı.
- Yapılmayanlar: gerçek DB/migration runtime, Plesk/staging, gerçek mobil ağ kesintisi, gerçek yüklenen dosyayla Laravel FFprobe/FFmpeg worker/queue kabul testleri.

**Sonuç:** `STATIC_CHECKS_PASS_RUNTIME_PENDING`. Bu belge canlıya çıkış onayı değildir.
