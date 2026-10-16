# ORVIONART geliştirme durumu

Son kaynak sürüm: 0.10.70.

## 0.10.70 — Yedek manifesti doğrulama sertleştirmesi
- Yeni staging yedek manifestleri format 2 kullanır; `.env` ve vendor dizininin dahil edilmediğini açıkça bildirir.
- Doğrulayıcı eski biçimleri desteklemeyi sürdürürken alan tekrarlarını, bilinmeyen alanları, hatalı timestamp/engine ve eksik güvenlik beyanlarını reddeder.
- Geçerli ve bozuk manifest vakalarının yalıtılmış shell regresyon testi bu ortamda çalıştırıldı. Gerçek DB geri yüklemesi yapılmadı.

## 0.10.69 — Kurulum hazırlık denetimi: upload, queue ve video ikilileri
- `orvionart:release-check` raporu PHP `upload_max_filesize` / `post_max_size`, queue sürücüsü ve video dönüştürme etkinse FFmpeg/FFprobe ikililerinin bulunabilirliğini raporlar.
- Operasyonel sorunlar gizli sunucu yolları veya yapılandırma sırları gösterilmeden `warn` olarak sunulur.
- Denetim salt okunurdur; dosya, PHP ayarı, `.env` ve veritabanı değiştirilmez.
- Regresyon test kaynağı ve Release Gate kontrolü eklendi. Composer/Laravel runtime ve gerçek Plesk/queue/video kabul testleri bu ortamda çalıştırılmadı.

Son kaynak sürüm: 0.10.68.

## 0.10.68 — Site Builder bölüm öğesi ve bağlantı doğrulaması
- Yeni bölüm kaydı/güncellemesi her bölümde en fazla 100 dolu öğe kabul eder; öğe başlığı/açıklaması ve slider buton alanları boyut kontrolünden geçer.
- Bölüm CTA ve slider adresleri ortak güvenli URL doğrulamasını kullanır.
- Üç Feature test kaynağı ve Release Gate statik kontrolü eklendi; yeni migration yoktur.
- Composer/Laravel runtime, gerçek DB, Plesk ve tarayıcı kabul testleri bu ortamda çalıştırılmadı.



## 0.10.54 — Site Builder güvenli tema ayarları
- Genel Site Builder ayarları arka plan/yüzey renkleri, başlık/gövde yazı tipleri, buton köşe stili, üst menü davranışı ve footer yerleşimini yönetebilir.
- Renkler HEX biçiminde, diğer görsel seçenekler sabit allowlist ile doğrulanır; görünümde renkler tekrar doğrulanıp varsayılanla güvenli şekilde değiştirilir.
- Ayar güncellemesi mevcut değerleri koruyup varsayılanlarla birleştirir; eski istemciler yeni alanları kaybetmez.
- İki Feature test kaynağı ve Release Gate kontrolü eklendi. Composer/vendor bulunmadığından testler çalıştırılmadı.
- Bekleyenler: Laravel runtime, gerçek DB, tarayıcı/mobil responsive kabulü ve tüm V1.5 Site Builder menüleri (logo/favicon, sosyal medya varsayılan görseli, daha kapsamlı header/footer ayarları, sayfa bileşeni kapsamı) doğrulanmalı.


Bu paket, modüler ORVIONART platformunun geliştirme kaynağıdır; gerçek sunucuya kurulduğu veya kabul testlerini geçtiği anlamına gelmez.

## 0.10.2
- `scripts/release-manifest.php create|verify` kaynak teslimat manifesti oluşturur ve SHA-256 ile doğrular; değişmiş/eksik dosyaları ve manifestte olmayan ek dosyaları raporlar.
- Manifest `.env`, runtime verisi, `vendor/`, tarihsel ZIP arşivleri ve kendisini kapsam dışı tutar.
- Yeni ZIP paketlerine eski ZIP'leri tekrar gömme durduruldu; tarihsel teslimatlar ayrı tutulur. Böylece iç içe arşivlerin dosya boyutunu katlayarak büyütmesi önlenir.
- Manifest ve ZIP bütünlük kontrolü yapıldı. Bu kontrol Composer/Laravel runtime veya gerçek sunucu kabul testi değildir.

## 0.10.1
- `scripts/backup-staging.sh` ile operatör tarafından açıkça başlatılan MySQL/MariaDB ve medya yedeği alma aracı eklendi. Yedek yolu uygulama kökünün dışında olmalı; betik web uygulamasından otomatik çalışmaz.
- `scripts/verify-backup.sh` SHA-256 ve medya arşivi bütünlüğünü kontrol eder; DB'ye bağlanmaz, SQL çalıştırmaz ve geri yükleme yapmaz.
- Staging geri yükleme tatbikatı adımları `docs/BACKUP_RESTORE_0_10_1.md` içine yazıldı. Gerçek sunucu yedeği veya geri yükleme tatbikatı yapılmadı.
- Shell betikleri sözdizimi açısından kontrol edildi; Composer/Laravel runtime, DB migration ve feature testleri hâlâ çalıştırılmadı.

## 0.10.0
- `orvionart:release-check` salt okunur kurulum/staging hazırlık komutu eklendi. PHP sürümü/eklenti gereksinimleri, Composer dosyaları, app key/debug/URL, güvenli oturum çerezleri, yazma izinleri, DB bağlantısı ve migration tutarlılığını denetler.
- JSON raporu ve PASS / REVIEW_REQUIRED / BLOCKED durumu eklendi; gerçek sunucuda doğrulanması gereken erişim kısıtlaması, document root, yedekten geri dönüş ve tarayıcı kabul testleri manuel maddeler olarak görünür.
- Ödeme özelliği bayrağı `ORVIONART_PAYMENTS_ENABLED=false` varsayılanıyla tanımlandı; ödeme entegrasyonu uygulanmadı.
- Test kaynakları eklendi ancak Composer bulunmadığından çalıştırılmadı.

## 0.9.9
- `orvionart:inspect-platform` salt okunur çapraz modül denetimi eklendi. Deneyim/QR-NFC/medya/Site Builder/kullanım hakkı kayıt sayılarını ve belirli veri bütünlüğü sorunlarını raporlar.
- Aktif QR bağlantılarının erişilemeyen hedefleri, eksik medya dosyaları, birden fazla sahibi olan medya, yayındaki içeriklerde geçersiz medya referansları ve erişilemeyen yönlendirme hedefleri uyarı üretir.
- JSON raporu token, gizli yapılandırma ve dosya yolu içermez. Komut veri düzeltmez veya silmez.
- Test kaynakları eklendi; Composer olmadığı için Laravel testleri çalıştırılmadı.

## 0.9.8
- `orvionart:inspect-demo` salt okunur komutu eklendi; varsayılan ayar, içerik/oturum/olay sayıları, pasif içerik atamaları ve medya dosyası erişilebilirliği raporlanır.
- `--json` çıktı seçeneği eklendi. Raporda token, gizli yapılandırma veya dosya yolu yazdırılmaz.
- Uyarı bulunursa komut `1` çıkış kodu verir; bu, monitoring/CLI kontrolüne yardımcı olur fakat runtime kabul testi yerine geçmez.
- Demo yönetim ekranındaki saklama açıklaması düzeltildi. Test kaynakları eklendi ancak çalıştırılmadı.

## 0.9.7
- Demo medya uç noktası geçerli ve süresi dolmamış cihaz yolculuğu ile bugünün içerik atamasını doğrular.
- Medya yanıtlarına no-store/noindex/no-referrer/nosniff başlıkları ve hız sınırı eklendi; genel güvenlik middleware’i rota bazındaki daha sıkı Referrer-Policy değerini korur.
- Yolculuk yeniden başlatılırken eski oturum sonlandırılır; olay ve yeni oturum kayıtları transaction içinde oluşturulur.
- Demo Engine aktif olsa bile aktif içerik yoksa boş oturum açılmaz.
- İlgili Feature test kaynakları eklendi; çalıştırılamadı.

## 0.9.6
- Deneyime bağlı medya dosyasını silmeden merkezi kütüphaneye taşıma akışı eklendi.
- Bağlantı kaldırma ve silme işlemleri audit kayıtlarına yazılır.
- Site Builder taslak/yayın referansları bulunan dosyalar silme işleminden korunur.
- Medya yaşam döngüsü test senaryoları eklendi; çalıştırılmadı.

## 0.9.5
- QR/NFC hedef durumu ile deneyim yayın durumu yönetim ekranında ayrıştırıldı.
- Deneyim oynatıcısında ortak `publiclyAvailable()` sorgu kapsamı kullanılıyor.
- Erişilemeyen QR hedeflerinde sayaç artmaması ve bakım modu davranışı için test kaynakları eklendi; çalıştırılmadı.

## 0.9.4
- Demo Engine için veri saklama temizleme komutu eklendi.
- `retention_days` değeri eski demo olayları ve sona ermiş oturumların saklama süresinde kullanılıyor.
- `--dry-run` ile silme öncesi sayım yapılabiliyor.
- Laravel Scheduler günlük 03:20 çalışacak şekilde tanımlandı; sunucuda `schedule:run` cron girdisi ayrıca gerekir.
- Otomatik test kaynakları eklendi, bu ortamda çalıştırılamadı.

## Doğrulama sınırları
- PHP sözdizimi kontrolleri ve Composer JSON doğrulaması bu paket hazırlanırken çalıştırılır.
- Composer bağımlılık kurulumu, Laravel runtime, veritabanı migration'ları, feature testleri, tarayıcı ve gerçek cihaz testleri bu ortamda tamamlanmış değildir.
- Gerçek sunucuya bağlantı/deploy yapılmamıştır.
- Ödeme sistemi devre dışı ve uygulanmamış durumdadır.

## Sonraki adımlar
1. Gerçek staging HTTPS adresinde `bash scripts/smoke-staging.sh https://...` çalıştır; tüm HTTP kontrollerini kaydet.
2. Composer bağımlılıklarını kurup Laravel testlerini ve migration kontrollerini staging veritabanında çalıştır.
3. Yedekten staging geri yükleme tatbikatını kontrollü yürüt.
4. Demo retention komutunu staging veritabanında doğrula.
5. QR/NFC, deneyim ve medya erişim zincirlerini bütünleşik test et; 0.9.6 medya ayırma/silme testlerini çalıştır.
6. Site Builder yayınlama ve bakım modu testlerini gerçek Laravel ortamında çalıştır.
7. Kullanım hakları için bekleyen ticari kararları kesinleştir; kurallar belirlenmeden otomatik erişim/ücret davranışı ekleme.
8. Kontrollü staging kurulumu ve yedek/geri dönüş tatbikatını tamamla.


## 0.10.12 status — 2026-10-15
- `orvionart:inspect-media-storage` salt okunur komutu eklendi. `local` disk üzerindeki `orvionart-media/`, `orvionart-site-media/` ve `orvionart-demo/` dosyalarını MediaAsset/DemoContent referanslarıyla karşılaştırır.
- Veritabanı kaydıyla eşleşmeyen dosyaları raporlar; otomatik silme veya veri düzeltme yapmaz.
- İki Laravel Feature test kaynağı eklendi: sahipsiz dosya raporlama ve referanslı dosyanın korunması.
- Release gate bu komutun kaydı, test kaynağı ve belgesini kontrol eder. Statik release gate sonucu PASS=19, WARN=2, FAIL=0.
- Bekleyenler: Composer/Laravel runtime testleri, gerçek DB migration/rollback, Plesk staging, HTTP/tarayıcı/mobil kabul testleri ve yedekten geri yükleme tatbikatı.

## 0.10.5 status — 2026-10-15
- Deployment preflight expanded to validate APP_KEY format, staging/production environment, HTTPS APP_URL, APP_DEBUG=false, secure/HttpOnly/SameSite session cookies, disabled payments, and `.env` permissions.
- Isolated preflight regression script added for secure and insecure fixtures.
- Pending: Composer install/validation, Laravel feature tests, migrations on disposable DB, real Plesk staging, HTTP smoke test, browser/mobile acceptance, and backup/restore drill.


## 0.10.6 status — 2026-10-15
- Migration dosyaları için Laravel bootstrap/veritabanı olmadan çalışan statik denetim eklendi: adlandırma, zaman damgası çakışması, `up()`/`down()` metotları ve migration sınıfı biçimi.
- Olası yıkıcı ham SQL insan incelemesi için `REVIEW` olarak raporlanır.
- Geçerli/eksik `down()` fixture testleri geçici dizinlerde çalışır; gerçek migration veya rollback çalıştırmaz.
- `scripts/release-gate.sh` bu denetimi ve regresyon testini kapsar.
- Bekleyenler: Composer/Laravel runtime, gerçek DB migration/rollback, staging, tarayıcı/mobil kabul testleri ve geri yükleme tatbikatı.

## 0.10.7 status — 2026-10-15
- Site Builder yayın anlık görüntüleri ve etkin navigasyon hedefleri için `orvionart:inspect-site-builder` salt okunur denetimi eklendi.
- Eksik yayın snapshot'ı, yinelenen yayın slug'ı, geçersiz menü hedefi, HTTPS dışı dış bağlantı, üst menü kuralı uyumsuzluğu ve gelecekteki yayın zamanı uyarıları raporlanır.
- Temiz durum, taslak hedef ve eksik snapshot için Feature test kaynakları eklendi.
- Bekleyenler: Composer/Laravel test runtime, gerçek DB/staging doğrulaması, tarayıcı/mobil kabul testi ve geri yükleme tatbikatı.


## 0.10.8 status — 2026-10-15
- Site Builder publish öncesi slug çakışma kontrolü eklendi; başka bir sayfanın canlı adresi kullanılıyorsa yayınlama açıklayıcı 422 ile engellenir.
- Taslak önizlemesine açık noindex/no-store/no-referrer başlıkları eklendi.
- İki Feature test kaynağı eklendi; Composer/vendor ve test DB olmadığından Laravel testleri çalıştırılmadı.
- Bekleyenler: Composer bağımlılık kurulumu, Laravel Feature testleri, gerçek DB migration/rollback, Plesk staging, HTTP/tarayıcı/mobil kabul testleri ve geri yükleme tatbikatı.


## 0.10.9 status — 2026-10-15
- Başarılı QR/NFC yönlendirmelerine `private, no-store, no-cache, must-revalidate`, `Pragma: no-cache`, `X-Robots-Tag`, `Referrer-Policy` ve `X-Content-Type-Options` başlıkları eklendi.
- QR token'ının slug değişikliğinden bağımsız kaldığını ve güncel URL'ye yönlendirdiğini test eden Feature senaryosu eklendi.
- PHP statik kontrolü yapılabilir; Laravel Feature testleri Composer/vendor ve test DB olmadığı için çalıştırılmadı.
- Bekleyenler: Composer doğrulaması, Laravel testleri, gerçek DB migration/rollback, Plesk staging, HTTP/tarayıcı/mobil kabul testi ve geri yükleme tatbikatı.


## 0.10.13 status — 2026-10-15
- `orvionart:inspect-demo-lifecycle` eklendi; oturum zamanları, oturum dışındaki günlük atamalar ve oturum başlangıcından önce kaydedilen olaylar salt okunur denetlenir.
- Rapor kayıt sayıları ve en fazla 20 örnek ID içerir; token bilgisi içermez ve hiçbir veriyi değiştirmez.
- Üç Laravel Feature test kaynağı eklendi. Bu ortamda Composer/vendor bulunmadığından çalıştırılmadı.
- Release gate sonucu: PASS=20, WARN=2, FAIL=0; `STATIC_CHECKS_PASS_RUNTIME_PENDING`.
- Bekleyenler: Composer/Laravel runtime ve Feature testleri, gerçek DB migration/rollback, Plesk staging, HTTP/tarayıcı/mobil kabul testleri ve yedekten geri yükleme tatbikatı.


## 0.10.14 status — 2026-10-15
- `orvionart:inspect-qr-lifecycle` salt okunur komutu eklendi; token biçimi/tekrarı, etkin hedef erişimi, tarama sayacı/son tarama zamanı tutarlılığı, gelecekteki veya oluşturulma öncesi tarama zamanlarını denetler.
- Token değerleri rapora yazılmaz; yalnızca kayıt kimlikleri örneklenir. Kayıtları değiştirmez.
- Dört Laravel Feature test kaynağı ve kullanım/güvenlik belgesi eklendi. Composer/vendor bulunmadığından testler çalıştırılmadı.
- Bekleyenler: Composer/Laravel runtime ve Feature testleri, gerçek DB migration/rollback, Plesk staging, HTTP/tarayıcı/mobil kabul testleri ve yedekten geri yükleme tatbikatı.


## 0.10.17
- Site Builder yönlendirmeleri için salt okunur bütünlük denetimi eklendi.
- Laravel Feature test kaynakları eklendi; bu ortamda Composer/vendor bulunmadığından çalıştırılmadı.


## 0.10.57 status — 2026-10-15
- Süper Admin ana ekranı temel kullanım yolunu ve veritabanından gerçek kayıt sayılarını gösterir.
- Deneyim düzenleme ekranından ilgili deneyime önceden bağlanmış medya yükleme ekranına geçiş eklendi.
- Standart medya ve parçalı video yüklemesi, bağlı deneyim mevcutsa işlem sonunda deneyim ekranına döner.
- Üç Feature test kaynağı eklendi; bu ortamda Composer/vendor bulunmadığından Laravel testleri çalıştırılmadı.
- Yeni migration yoktur. Canlı/staging sunucusuna dağıtım, DB migration/rollback, HTTPS QR tarama ve NFC fiziksel yazma testi yapılmadı.
- Sıradaki zorunlu adımlar: Composer bağımlılıklarını kurmak, hedef MariaDB bağlantısını ve migration'ları staging üzerinde doğrulamak, bütün Feature testlerini çalıştırmak, yedekten geri dönüş ve telefonla QR/NFC kabul testlerini tamamlamak.


## 0.10.58 status — 2026-10-16
- Deneyim listesi ekranındaki eski QR/NFC ve medya “henüz etkin değil” metni düzeltildi.
- `QrAndMediaTest` içine UI tutarlılığını koruyan Feature test kaynağı eklendi.
- QR/NFC kaynak akışı denetimi: `docs/QR_NFC_SOURCE_AUDIT_0_10_58.md`.
- Bu ortamda Composer ve `vendor/autoload.php` bulunmadığı için Laravel Feature testleri çalıştırılmadı. Gerçek sunucu, DB, HTTPS ve telefon kabulü yapılmadı.
