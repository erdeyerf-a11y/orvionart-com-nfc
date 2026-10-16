## Güncel sürüm 0.10.74 — kontrollü güncelleme paketi ön denetimi

`bash scripts/update-package-preflight.sh /path/to/release.zip <beklenen-SHA256>` komutu, dağıtım ZIP’ini güvenli yollar, sır/runtime dosyaları, SHA-256, sürüm, manifest ve PHP sözdizimi açısından geçici dizinde doğrular. Dosya dağıtmaz ve veritabanına dokunmaz. Regresyon: `bash scripts/test-update-package-preflight.sh`. Ayrıntı: `docs/UPDATE_PACKAGE_PREFLIGHT_0_10_74.md`.

## Güncel sürüm 0.10.73 — QR/NFC envanter CSV dışa aktarımı

`bash scripts/verify-restored-staging.sh` komutu, harici olarak geri yüklenmiş bir staging veritabanının kimliğini, salt-okunur modunu, temel ORVIONART tablolarını ve migration geçmişini salt-okunur sorgularla denetler. Hedef `orvionart_staging_` öneki ve ikinci hedef onayı zorunludur. Komut SQL çalıştırmaz, migration uygulamaz ve veri değiştirmez. Regresyon testi: `bash scripts/test-verify-restored-staging.sh`. Bu kontrol uygulamanın HTTP üzerinden açıldığını, medya dosyalarının geri geldiğini veya canlıya geçişin güvenli olduğunu kanıtlamaz.

## Güncel sürüm 0.10.71 — Geri yükleme için salt-okunur staging ön kontrolü

`bash scripts/restore-staging-preflight.sh /yedek/dizini` komutu; yedek SHA-256/manifest/arşiv yollarını doğrular, hedef veritabanı için `orvionart_staging_` önekini ve ikinci onayı zorunlu tutar, kaynak veritabanıyla aynı hedefi reddeder ve hedef DB'nin boş olduğunu salt-okunur sorguyla kontrol eder. `RESTORE_STAGING_ROOT` uygulama ve yedek dizinlerinin dışında olmalı; hedef mevcutsa boş olmalıdır. Gerekli ayarlar `RESTORE_TARGET_DB`, `RESTORE_CONFIRM_TARGET_DB`, `RESTORE_STAGING_ROOT`, `RESTORE_DB_USER`, `RESTORE_DB_PASSWORD` ve isteğe bağlı `RESTORE_DB_HOST`/`RESTORE_DB_PORT` değişkenleridir. Parolayı komut argümanına yazmayın. Regresyon testi: `bash scripts/test-restore-staging-preflight.sh`.

Bu betik **yalnızca ön kontroldür**: SQL içe aktarmaz, medya arşivini çıkarmaz, dizin oluşturmaz ve gerçek geri yükleme tatbikatının yerine geçmez. Ayrıntı: `docs/RESTORE_STAGING_PREFLIGHT_0_10_71.md`.

## Güncel sürüm 0.10.70 — Yedek manifesti doğrulama sertleştirmesi

`php artisan orvionart:release-check` raporu PHP yükleme sınırlarını, kuyruk sürücüsünü ve video dönüştürme açıksa FFmpeg/FFprobe bulunabilirliğini de gösterir. Bu kontroller salt okunurdur; eksik ayarlar uyarı üretir ve gerçek queue worker/video işleme kabul testinin yerine geçmez. Ayrıntı: `docs/RELEASE_READINESS_VIDEO_RUNTIME_0_10_69.md`.

## Güncel sürüm 0.10.68 — Site Builder bölüm öğesi ve bağlantı doğrulaması

Site Builder bölümlerinde en fazla 100 öğeye izin verilir; yeni taslak kaydı da eski sürüm geri yükleme sınırıyla aynı kurala uyar. Öğe başlığı, açıklaması ve slider düğme alanları boyutları doğrulanır. Bölüm/slider bağlantıları yalnızca geçerli HTTPS, güvenli site içi `/` veya `#` adresi olabilir; `javascript:` ve bozuk `https://` gibi adresler reddedilir. Yeni regresyon test kaynakları eklendi; bu ortamda Laravel runtime testleri çalıştırılmış sayılmaz.

## Güncel sürüm 0.10.67 — Site Builder müşteri görüşleri ve istatistikler

Site Builder sayfa düzenleyicisine Müşteri Görüşleri ve İstatistikler bölüm türleri eklendi. Her satır bir görüş metni ve isim veya bir istatistik değeri ve açıklaması olarak girilir. Kamu sayfasında içerik semantik ve mobil uyumlu kartlar halinde gösterilir; HTML içerik olarak çalıştırılmaz. Feature test kaynakları ve Release Gate kontrolü eklendi. Bu ortamda Laravel runtime ve gerçek sunucu kabul testleri çalıştırılmadı.

## Güncel sürüm 0.10.66 — Site Builder navigasyon bağlantısı düzenleme

Süper Admin, Menü ve navigasyon ekranındaki mevcut header/footer bağlantılarını düzenleyebilir: ad, konum, hedef sayfa veya HTTPS URL ve sıra. Hedef sayfa kaydetme transaction'ında kilitlenip yeniden doğrulanır; yayınlanmamış veya ana menüye uygun olmayan sayfa hedefleri reddedilir. Değişiklik ve audit kaydı atomiktir. Test kaynakları eklendi, ancak Composer/Laravel runtime bu ortamda bulunmadığından Feature testleri çalıştırılmış sayılmaz; Plesk/DB kabul testi bekliyor.

## Güncel sürüm 0.10.65 — Site Builder iletişim alanları ve footer bağlantıları

Site Builder genel ayarlarından varsayılan sosyal paylaşım görseli yüklenebilir, değiştirilebilir veya kaldırılabilir. Sayfaya özel sosyal görsel yoksa yayınlanmış sayfalar Open Graph/Twitter büyük görsel etiketlerinde bu görseli kullanır; sayfaya özel seçim önceliklidir. Görsel özel depolamada tutulur; PNG/JPEG/WebP, 5 MB sınırı ve güvenli varlık rotası uygulanır. Feature test kaynakları eklendi, ancak Composer/Laravel runtime bu ortamda bulunmadığından testler çalıştırılmış sayılmaz.

## Sürüm 0.10.63 — QR/NFC kayıtlarında arama ve filtreleme

Süper Admin, QR/NFC bağlantılarını etiket veya bağlı deneyim adına göre arayabilir; etkin/devre dışı durumuna göre filtreleyebilir. Filtreler sayfalar arasında korunur. Laravel Feature test kaynağı eklendi; Composer/runtime bulunmadığından testler çalıştırılmış sayılmaz.

## Önceki sürüm 0.10.62 — QR/NFC kullanılamayan içerik ekranı

Devre dışı veya yayında olmayan QR/NFC hedeflerinde ziyaretçiye anlaşılır, mobil uyumlu bir sayfa gösterilir. Süresi dolan hedef HTTP 410 döndürür; başarısız erişimler tarama sayacını artırmaz. Gerçek sunucu kabulü ayrıca gereklidir.

## Güncel sürüm 0.10.61 — Markalı QR ürün etiketi

QR önizleme ekranında ORVIONART marka adı, deneyim başlığı, ziyaretçiye kısa yönlendirme ve QR kodu bir arada yazdırılabilir. Yazdırma görünümünde yalnızca etiket basılır; yönetim kontrolleri ve NFC kopyalama alanı gizlenir. Bu kaynak değişikliği henüz gerçek yazıcı/telefon kabul testi değildir. Ayrıntı: `docs/QR_PRINTABLE_PRODUCT_LABEL_0_10_61.md`.

## Önceki sürüm 0.10.60 — Deneyim erişim bitiş zamanı

Süper Admin deneyim formunda isteğe bağlı bitiş zamanı belirlenebilir. Süre dolunca QR/NFC bağlantısı sabit kalır ancak ziyaretçiye içerik açılmaz; geçersiz erişim okutma sayacını artırmaz. Eski deneyimler varsayılan olarak süre sınırı olmadan kalır. Ayrıntı: `docs/EXPERIENCE_ACCESS_EXPIRY_0_10_60.md`.

## Güncel sürüm 0.10.59 — QR/NFC önizleme

QR/NFC yönetiminde QR kodunu ekranda önizleme, yazdırma görünümü ve NFC için kalıcı adresi kopyalama bulunur. Bu özellik kaynak koduna eklendi; gerçek sunucu/telefon testi henüz yapılmadı. Ayrıntı: `docs/QR_NFC_PREVIEW_PRINT_0_10_59.md`.

## 0.10.58 — QR/NFC akışı ve doğrulama sınırı

Deneyim listesi artık QR/NFC ve medya özelliklerini yanlışlıkla “henüz etkin değil” olarak göstermiyor. QR/NFC akışı kaynak kodunda şu şekilde tasarlanmıştır: deneyim yayımlanır, deneyim ekranından bağlantı kaydı oluşturulur, 40 karakterli kalıcı `/go/{token}` adresi SVG QR'ye kodlanır; aynı adres NFC etiketi için kopyalanır; yönlendirme geçerli deneyimin güncel ziyaretçi sayfasına gider. Bu, kaynak kod incelemesidir; gerçek sunucuda çalıştığına dair kabul kanıtı değildir. Composer/Laravel Feature testleri ve gerçek cihaz taraması Manus tarafından gerçek ortamda yapılmalıdır.

# ORVIONART — Kontrollü Platform Geliştirme Paketi

## Sürüm 0.10.55 — Site Builder logo ve favicon yönetimi

Site Builder genel ayarları artık logo/favicon yükleme, değiştirme ve kaldırma akışlarını içerir. Dosyalar özel depolamada tutulur; SVG kabul edilmez ve kamuya açık dosya uçları yalnızca doğrulanmış marka dosyalarını sunar. Yeni dosya, ayar ve denetim kaydı akışı için Feature test kaynakları eklendi ancak Laravel runtime çalıştırılmadığından testler PASS kabul edilmemelidir.

**Durum:** Statik kontroller; Laravel runtime, gerçek DB, Plesk ve gerçek tarayıcı/mobil kabulü bekliyor.

## Sürüm 0.10.54 — Site Builder tema kontrolleri

Site Builder genel ayarları; sayfa arka planı, bölüm yüzeyi, ayrı başlık/gövde fontu, buton şekli, sticky/normal üst menü ve footer yerleşimini güvenli izin listeleriyle yönetebilir. Eski ayarlar ve eski form istemcileri geriye dönük korunur. Tema kontrolleri için Feature test kaynakları eklendi ancak Composer/Laravel runtime mevcut değilse bunlar çalıştırılmış sayılmaz. Ayrıntı: `docs/SITE_BUILDER_SAFE_THEME_CONTROLS_0_10_54.md`.

**Durum:** Statik kontroller; Laravel runtime, veritabanı ve gerçek tarayıcı/mobil kabulü bekliyor.

## Sürüm 0.10.53 — Video dönüştürme ve küçük resim

Video işleme kuyruğu, kaynak dosyayı koruyarak FFmpeg ile H.264/yuv420p + AAC MP4 türevi ve JPEG küçük resim üretir. Türetilmiş yollar yalnızca doğrulama tamamlandıktan sonra medya kaydına yazılır; yönetici önizlemesi ve kamu sunumu hazır MP4 türevini kullanır. Eski geçici türev dosyaları günlük görevle temizlenir. Sentetik 2 saniyelik örnekle FFmpeg→FFprobe→JPEG küçük resim CLI smoke testi geçti; bu, Laravel kuyruğunun gerçek yükleme üzerinde çalıştığı veya sunucu kabul testlerinin geçtiği anlamına gelmez. Ayrıntı: `docs/VIDEO_TRANSCODING_AND_THUMBNAILS_0_10_53.md`.

**Durum:** Statik kontroller; Laravel runtime, gerçek FFmpeg/FFprobe ve sunucu kabulü bekliyor.

## Sürüm 0.10.52 — Video işleme eşzamanlılık ve protokol güvenliği

Video doğrulama kuyruğu her medya kaydı için eşzamanlı çalışma kilidi kullanır. FFprobe yalnızca `file` ve `pipe` protokollerine izin verecek şekilde çağrılır; bu, yüklenen dosyanın işçi üzerinden harici ağ kaynaklarına erişmesini sınırlar. İlgili regresyon test kaynakları ve Release Gate kontrolleri eklendi. Bu sürüm FFmpeg dönüştürme/thumbnail üretimini tamamlamaz. Laravel runtime, gerçek FFprobe, queue worker ve Plesk kabul testleri bu ortamda yapılmadı.

**Durum:** Statik kontroller; Laravel runtime ve gerçek sunucu kabulü bekliyor.

## Sürüm 0.10.50 — Parçalı video yükleme

Medya Kütüphanesi, 4 MiB parçalarla 512 MiB'a kadar MP4/WebM/OGG video yüklemeyi ve aynı dosya yeniden seçildiğinde tarayıcıda devam etmeyi destekler. Parçalar SHA-256 ile doğrulanır; oturumlar 24 saat geçerlidir ve `orvionart:prune-video-uploads` günlük scheduler göreviyle temizlenir. Video transcoding/codec işleme bu sürümde uygulanmamıştır. Gerçek sunucuda PHP yükleme limitleri ve büyük dosya kabul testi ayrıca yapılmalıdır. Ayrıntı: `docs/RESUMABLE_VIDEO_UPLOAD_0_10_50.md`.

**Durum:** Statik kontroller geçti; Laravel runtime, staging ve gerçek sunucu kabulü bekliyor.

## Sürüm 0.10.49 — Staging indeksleme koruması

Site Builder kamu sayfaları üretim dışı ortamlarda `X-Robots-Tag: noindex, nofollow, noarchive` döndürür; böylece sayfanın SEO bayrağı yanlışlıkla indekslemeye izin verse bile staging yanıtı indekslenebilir olmaz. Bu başlık parola korumasının yerine geçmez. İlgili Feature test kaynağı eklendi, ancak Composer/runtime yoksa çalıştırılmış sayılmaz. Ayrıntı: `docs/STAGING_PUBLIC_SITE_NOINDEX_0_10_49.md`.

**Durum:** Statik kontroller; Laravel runtime, staging ve gerçek sunucu kabulü bekliyor.


## Arama motoru indekslemesi

İndeksleme güvenli varsayılan olarak kapalıdır. `ORVIONART_PUBLIC_INDEXING_ENABLED=false` ayarı staging dahil kurulumlarda `robots.txt` için `Disallow: /` üretir ve sitemap'i boş tutar. Yalnızca doğrulanmış, herkese açık üretim alanında `APP_URL` ve canonical adresler kontrol edildikten sonra `ORVIONART_PUBLIC_INDEXING_ENABLED=true` yapılmalıdır. Robots kuralı parola korumasının yerine geçmez. Ayrıntı: `docs/SEARCH_INDEXING_EXPLICIT_OPT_IN_0_10_48.md`.

## Site Builder kamu sayfalarında önbellek yaşam döngüsü — 0.10.47

Ana sayfa ve Site Builder'ın yayımlanmış sayfaları `Cache-Control: no-store, private` kullanır; böylece yayın durumu değiştikten sonra eski sayfa yanıtlarının tarayıcı/paylaşımlı önbellekte kalma riski azaltılır. İlgili Feature test kaynakları eklendi; Composer/runtime yoksa çalıştırılmış sayılmaz. Ayrıntı: `docs/SITE_BUILDER_PUBLIC_CACHE_LIFECYCLE_0_10_47.md`.

**Sürüm:** 0.10.47  
**Durum:** Statik kontroller; Laravel runtime ve gerçek sunucu kabulü bekliyor.

## QR/NFC görsel yaşam döngüsü — 0.10.46

Yönetici QR SVG indirme uç noktası güncel QR ve deneyim durumunu transaction içinde yeniden doğrular. Devre dışı bağlantı veya taslak/arşiv hedefi için görsel üretimi reddedilir; gelecekte zamanlanmış deneyimler için QR önceden indirilebilir. Üç Feature test kaynağı eklidir; Composer/runtime yoksa çalıştırılmış sayılmaz. Ayrıntı: `docs/QR_IMAGE_LIFECYCLE_GUARD_0_10_46.md`.

**Sürüm:** 0.10.46  




## Güvenlik başlıkları — 0.10.45

Global güvenlik middleware'i, bir route daha sıkı bir `Content-Security-Policy` tanımladıysa bu başlığı korur. Özellikle yönetici medya önizlemesindeki `default-src 'none'; sandbox` politikası genel CSP tarafından ezilmez. İlgili Feature test kaynakları eklidir; Composer/runtime yoksa çalıştırılmış sayılmaz. Ayrıntı: `docs/SECURITY_HEADERS_ROUTE_CSP_PRESERVATION_0_10_45.md`.

**Sürüm:** 0.10.45  
**Durum:** Geliştirme / statik kontroller; gerçek sunucu kabulü bekliyor.

ORVIONART, Laravel tabanlı; Süper Admin, deneyimler, medya, QR/NFC, bağımsız Demo Engine, Site Builder ve kullanım hakkı kayıt defteri bileşenleri bulunan bir platform olarak geliştirilmektedir.

## Ortam yapılandırması (.env)

1. `.env.example` dosyasını `.env` adıyla kopyalayın.
2. `APP_URL`, MySQL veritabanı adı/kullanıcısı/parolası ve ortamınıza ait diğer değerleri değiştirin. Gerçek parolaları ZIP veya kaynak kod deposuna koymayın.
3. Uygulama kök dizininde `php artisan key:generate` komutuyla benzersiz `APP_KEY` üretin.
4. HTTPS kullanılan staging/production ortamlarında `SESSION_SECURE_COOKIE=true` kalsın. Yalnızca yerel, HTTPS olmayan geliştirme ortamında gerekirse `false` yapın.
5. `ORVIONART_PAYMENTS_ENABLED=false` varsayılanını ödeme entegrasyonu ayrıca tasarlanıp test edilene kadar değiştirmeyin.

Şablon MySQL/MariaDB bağlantısını temel alır; mevcut `composer.json` `ext-pdo_mysql` gerektirir. PostgreSQL yapılandırması bu sürümde doğrulanmış değildir.

## Bu paketin amacı

Bu paket, kaynak kodunu ve geliştirme belgelerini bir arada tutan birikimli teslimattır. Gerçek sunucuya kurulmuş veya üretim ortamında doğrulanmış olduğu anlamına gelmez. Manus'a gönderim ve canlı sunucuya dağıtım ayrıca yetkilendirilmiş son aşamadır.

## Temel gereksinimler

- PHP 8.3 veya üzeri
- Laravel bağımlılıkları için Composer
- `composer.json` içinde tanımlanan veritabanı sürücüsü ve PHP uzantıları
- Ayrı ve izole staging uygulaması/veritabanı
- HTTPS, staging erişim koruması ve arama motoru indekslemesinin engellenmesi

Kurulum öncesi gerçek sunucunun PHP uzantıları, Composer sürümü, veritabanı erişimi ve dosya izinleri ayrıca kontrol edilmelidir. Bu ZIP gerçek `.env` sırları veya `vendor/` bağımlılık dizini içermez. Kurulum için `.env.example` şablon olarak sunulur; gerçek `.env` dosyasını kendiniz oluşturmalısınız.

## Dağıtım ortamı şablonu (0.10.42)

`.env.example`, güvenli dağıtım şablonudur; gerçek sır içermez. Release Gate artık bu dosyanın varlığını, MySQL sürücüsünü, boş `APP_KEY`, kapalı debug/ödeme varsayılanlarını ve HTTPS oturum çerezi ayarını kontrol eder. Şablondaki yer tutucu veritabanı kimlik bilgileri kuruluma başlamadan önce ortamınıza göre değiştirilmelidir.

## Veritabanı yapılandırma ön kontrolü (0.10.43)

`php scripts/preflight.php`, veritabanına bağlanmadan önce `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` ve `DB_PASSWORD` alanlarını kontrol eder. Boş veya `replace_with_*` gibi yer tutucular varsa kontrol BLOCKER üretir; değerleri ekrana yazdırmaz. `--check-db` seçeneği ayrıca salt okunur `SELECT 1` bağlantı kontrolü yapar; migration çalıştırmaz ve veri değiştirmez.

```bash
php scripts/preflight.php
php scripts/preflight.php --check-db
bash scripts/test-preflight.sh
```

Ayrıntı: `docs/DEPLOYMENT_PREFLIGHT_DATABASE_CONFIG_0_10_43.md`.

## Güvenli statik kontrol

Proje kökünde:

```bash
bash scripts/release-gate.sh
```

Bu komut PHP/Shell sözdizimini, kaynak manifestini, migration statik denetimini, önemli denetim komutlarının kaynaklarını, `.env` sırlarının bulunmamasını ve ödeme entegrasyonunun varsayılan kapalı olmasını kontrol eder. Composer veya `vendor/` yoksa çalışma zamanı testleri atlanır ve uyarı gösterilir.

Kurulum bağımlılıkları hazır olduğunda daha sıkı kontrol:

```bash
bash scripts/release-gate.sh --strict
```

Strict modda Composer ve Laravel test ortamı eksikse kontrol başarısız olur. Gerçek DB migration/rollback, tarayıcı, mobil, HTTPS, yedekten geri yükleme ve Plesk kabul testleri ayrıca yapılmalıdır.

## Platform bütünlük denetimi: geçmiş sürüm medya referansları (0.10.41)

`orvionart:inspect-platform --json` artık Site Builder'ın saklanan revision snapshot'larındaki bölüm/öğe/sosyal görsel referanslarını da denetler. Eksik medya kaydı, yanlış sayfa sahipliği, desteklenmeyen MIME veya kayıp dosya `historical_revision_media_reference_invalid` uyarısı ve ayrıntılı referans listesi olarak raporlanır. Revision kayıtları 100'lük gruplarla okunur; komut salt okunur kalır. İki Feature test kaynağı eklendi, ancak Composer/Laravel runtime bu ortamda yoksa çalıştırılmış sayılmaz. Ayrıntı: `docs/PLATFORM_INTEGRITY_HISTORICAL_MEDIA_0_10_41.md`.

## Yedek doğrulama güvenliği


0.10.70 ile yeni staging yedekleri `backup_format=2` ve `contains_vendor_directory=no` alanlarını taşır. `scripts/verify-backup.sh` eski manifestleri geriye dönük kabul eder; yeni formatta eksik, yinelenen veya bilinmeyen alanları reddeder. Kontrol checksum, metadata ve medya arşivi güvenliğini doğrular; SQL'i çalıştırmaz, arşivi çıkartmaz ve gerçek geri yüklemenin yerine geçmez. Regresyon testi: `bash scripts/test-verify-backup.sh`.

`bash scripts/verify-backup.sh /web-koku-disinda/yedek-dizini` SHA-256 kayıtlarını beklenen dosya adlarıyla sınırlar, yedek manifestini denetler ve medya arşivinde yol geçişi/bağlantı dosyası risklerini reddeder. Arşivi çıkartmaz ve SQL'i çalıştırmaz. Regresyon testleri için `bash scripts/test-verify-backup.sh` komutunu kullanın. Ayrıntı: `docs/BACKUP_VERIFY_HARDENING_0_10_20.md`.

## Site Builder medya yükleme atomikliği (0.10.35)

Site Builder medya yüklemesinde medya veritabanı kaydı ve `site_page.media_uploaded` denetim kaydı aynı veritabanı transaction'ı içinde oluşturulur. Denetim kaydı yazılamazsa medya satırı rollback edilir ve yüklenen dosya temizlenmeye çalışılır; böylece eksik dosyaya işaret eden bir kayıt bırakma riski azaltılır. İki regresyon Feature testi `tests/Feature/SiteBuilderTest.php` içindedir. Composer/Laravel runtime bu ortamda bulunmadığından testler çalıştırılmadı. Ayrıntı: `docs/SITE_BUILDER_MEDIA_UPLOAD_ATOMICITY_0_10_35.md`.

## Demo Engine yönetici durum değişikliği atomikliği (0.10.35)

Demo Engine ayarlarının ve içerik etkinleştirme/devre dışı bırakma işlemlerinin veritabanı durumu ile denetim kaydı aynı transaction içinde tutulur. İçerik durum değişikliği satırı kilitleyip güncel kaydı tekrar okur. İlgili regresyon kaynakları `tests/Feature/DemoEngineTest.php` içindedir. Composer/Laravel çalışma zamanı bulunmadığında bu testler çalıştırılmış sayılmaz; gerçek staging veritabanında eşzamanlılık ve rollback kabul testleri ayrıca yapılmalıdır. Ayrıntı: `docs/DEMO_ADMIN_STATE_AUDIT_ATOMICITY_0_10_35.md`.

## Genel medya tesliminde normal dosya doğrulaması (0.10.34)

Genel medya teslimi, dosya yolunun özel `local` disk kökü içinde olmasına ek olarak gerçek hedefin normal bir dosya (`is_file`) olmasını şart koşar. Yönetilen klasör altında bulunan bir dizin ya da normal dosya olmayan hedef `404` ile reddedilir. Regresyon test kaynağı `tests/Feature/QrAndMediaTest.php` içindedir; Laravel çalışma zamanı bu ortamda bulunmadığından test henüz çalıştırılmamıştır. Ayrıntı: `docs/PUBLIC_MEDIA_FILE_TARGET_0_10_34.md`.

## Medya dosya sistemi hedef denetimi (0.10.33)

`orvionart:inspect-media-references` artık yönetilen klasör altında görünen bir yolun gerçek dosya sistemi hedefini de çözümler. Özel `local` disk kökü dışına çıkan sembolik bağlantılar ve normal dosya olmayan hedefler `unsafe_filesystem_target` olarak raporlanır. Denetim salt okunurdur; otomatik düzeltme yapmaz. Ayrıntı: `docs/MEDIA_REFERENCE_INSPECTION_0_10_33.md`.

## Medya silme güvenliği

Silme işlemi önce dosyayı private `orvionart-trash/` karantinasına taşır; veritabanı işlemi başarısız olursa asıl konuma geri taşımayı dener. Dosyası eksik kayıtlar ve yönetilen klasör dışındaki yollar otomatik silinmez. Karantinada kalan dosyaları `php artisan orvionart:inspect-media-storage --json` salt okunur raporlar; otomatik temizleme yapmaz. Ayrıntı: `docs/MEDIA_DELETE_QUARANTINE_0_10_19.md`.

`MediaAsset::canBeDeletedSafely()` aynı disk ve dosya yolunu kullanan diğer medya kayıtlarını da kontrol eder. Birden fazla kayıt aynı fiziksel dosyayı temsil ediyorsa dosyanın silinmesi engellenir. Bu koruma statik kaynak kontrolüdür; eşzamanlı istekler için gerçek veritabanı kabul testi ayrıca yapılmalıdır.

## Salt okunur uygulama denetimleri

Bağımlılıklar kurulmuş ve `.env` staging veritabanına göre yapılandırılmışsa:

```bash
php artisan orvionart:inspect-platform
php artisan orvionart:inspect-platform --json
php artisan orvionart:inspect-site-builder --json
php artisan orvionart:inspect-site-redirects --json
php artisan orvionart:inspect-demo --json
php artisan orvionart:inspect-demo-lifecycle --json
php artisan orvionart:inspect-media-references --json
php artisan orvionart:inspect-qr-lifecycle --json
```

Bu komutlar mevcut kayıtları, Demo Engine ve QR/NFC zaman tutarlılığını ve medya dosyalarının varlığını inceler; otomatik düzeltme veya dosya silme yapmaz. Uyarı bulunmaması son kabul testi anlamına gelmez.

## Medya silme güvenliği

Medya kütüphanesindeki bir dosya silinmeden önce deneyim ve Site Builder referanslarına ek olarak Demo Engine içeriklerinin `media_disk` + `media_path` referansı da kontrol edilir. Aynı disk ve yol Demo Engine tarafından kullanılıyorsa silme engellenir. İlgili Feature test kaynakları `tests/Feature/MediaAssetDeletionSafetyTest.php` içindedir; bu ortamda Laravel runtime testleri çalıştırılmamıştır.

## Site haritası köken ve tekrar koruması (0.10.26)

Üretim sitemap'i yalnızca `APP_URL` ile aynı şema, host ve etkin port üzerindeki mutlak URL'leri içerir. Harici/başka kökene yönelen canonical URL'ler dışarıda bırakılır; aynı canonical URL birden fazla sayfada kullanılmışsa tek kayıt tutulur ve en yeni yayın sürümü tarihi korunur. Zamanlanmış deneyimler `lastmod` için `scheduled_at` kullanır. İlgili regresyon testleri `tests/Feature/SiteBuilderTest.php` içindedir; bu ortamda Composer/Laravel runtime bulunmadığından çalıştırılmamıştır. Ayrıntı: `docs/SITEMAP_ORIGIN_AND_DEDUP_0_10_26.md`.

## Genel medya sunumunda disk ve yol koruması (0.10.25)

Yayınlanmış medya yalnızca `local` özel diskinden ve yönetilen medya klasörlerinden sunulur. Mutlak/traversal yollar ve özel depolama kökü dışına çözümlenen sembolik bağlantılar `404` ile reddedilir. Ayrıntı: `docs/PUBLIC_MEDIA_DELIVERY_GUARD_0_10_23.md`.

## Medya silmede özel disk koruması (0.10.22)

Medya silme işlemi dosyayı karantinaya taşımadan önce kaydın `local` özel diskinde olduğunu doğrular. `public` veya tanınmayan başka bir diskteki eski kayıtlar HTTP 409 ile durdurulur; veritabanı satırı ve dosya otomatik silinmez. Bu kontrol, karantina klasörünün yanlış yapılandırma nedeniyle web üzerinden erişilebilir olması halinde hassas dosyaların yanlışlıkla açığa çıkmasını önlemeye yardımcı olur. Ayrıntı: `docs/MEDIA_PRIVATE_QUARANTINE_GUARD_0_10_22.md`.

## Denetim kaydı hijyeni (0.10.21)

Bağımlılıklar kurulduktan sonra denetim kayıtlarını salt okunur incelemek için:

```bash
php artisan orvionart:inspect-audit-logs
php artisan orvionart:inspect-audit-logs --json
```

Komut gelecekte görünen zaman damgalarını, boş action alanlarını, geçersiz IP biçimlerini, hassas bilgi çağrışımlı metadata anahtarlarını ve eksik aktör referanslarını raporlar. Metadata değerlerini yazdırmaz; kayıtları değiştirmez veya silmez. Bu, kriptografik audit log değişmezliği garantisi değildir. Ayrıntı: `docs/AUDIT_LOG_HYGIENE_0_10_21.md`.

## Önemli güvenlik sınırları

- Ödeme entegrasyonu varsayılan olarak kapalı tutulur.
- Gerçek `.env` dosyası, API anahtarları, parolalar veya üretim verileri ZIP'e konmamalıdır.
- İlk kurulum izole staging ortamında yapılmalıdır; canlı siteye doğrudan üzerine yazma yapılmamalıdır.
- Kullanım hakları kayıt defteri henüz tek başına erişim yetkisi veya otomatik ücretlendirme uygulamaz.
- Süre bitiminde otomatik ücret alma davranışı eklenmemiştir.
- Gerçek sunucuda çalıştırılmamış testler tamamlanmış gibi raporlanmamalıdır.

## Kaynak doğrulaması

`RELEASE_MANIFEST.json`, dağıtılabilir kaynak dosyalarının boyut ve SHA-256 özetlerini içerir. Kontrol etmek için:

```bash
php scripts/release-manifest.php verify
```

ZIP'in kendisi için teslimat yanında verilen `.sha256` dosyası kullanılmalıdır.

## Belgeler

- `docs/ORVIONART_ANA_TALIMAT_V1_5.txt` — bağlayıcı proje talimatı
- `docs/RELEASE_GATE_0_10_3.md` — release gate kullanımı
- `docs/QR_LIFECYCLE_INSPECTION_0_10_14.md` — QR/NFC yaşam döngüsü denetimi
- `docs/EXPERIENCE_QR_LIFECYCLE_0_10_30.md` — deneyim durumları ile QR/NFC etkinlik tutarlılığı
- `docs/DEMO_CONTENT_DELETE_ATOMICITY_0_10_31.md` — Demo Engine içerik silme, özel medya yolu ve atama yarışı koruması
- `docs/MEDIA_REFERENCE_INSPECTION_0_10_15.md` — medya referans yolu ve dosya varlığı denetimi
- `docs/SITE_REDIRECT_INSPECTION_0_10_17.md` — Site Builder eski adres yönlendirmelerinin salt okunur denetimi
- `docs/RELEASE_HANDOFF_0_10_17.md` — 0.10.17 sürüm kapsamı, statik kontroller ve açık testler
- `docs/RELEASE_HANDOFF_0_10_10.md` — teslimat kapsamı ve sınırları
- `docs/REVENUE_MODEL.md` — gelir modeli çalışma belgesi

## Sürüm durumu

0.10.31, Demo Engine içerik silme ve günlük atama işlemlerini kilit/transaction ile tutarlı hâle getirir ve medya silmeyi güvenli özel yolla sınırlar. 0.10.30, deneyim durumları ile QR/NFC bağlantılarının yaşam döngüsünü eşleştirir. 0.10.27, Site Builder geçmiş sürüm geri alma sırasında SEO ve bölüm bağlantılarını yeniden doğrular; 0.10.26, sitemap canonical kökenini ve tekrarlarını korur; 0.10.25, sitemap tarihlerini yayın sürümüne bağlar. 0.10.24 ve 0.10.23, medya önizleme ve sunumunu özel disk/yol kontrolleriyle sınırlar. 0.10.22, medya silme karantinasını özel diskle sınırlar. 0.10.20, yedek doğrulamasında checksum listesi, manifest ve medya arşiv yollarını sıkılaştırır. Composer, Laravel çalışma zamanı, gerçek veritabanı, staging sunucusu ve üretim kabulü bu ortamda doğrulanmış değildir.

## 0.10.31 notu

Demo Engine içerik silme işlemi, kayıt kilidi ve atama geçmişi kontrolüyle tek transaction içinde yürütülür. Yalnızca `local` diskindeki `orvionart-demo/` yönetilen yolu silinir; beklenmeyen disk veya güvensiz yol HTTP 409 ile reddedilir. Günlük demo ataması ve `content_view` olayı da transaction içinde tutarlı biçimde yazılır. Yeni Feature test kaynakları eklendi; Composer/Laravel runtime yoksa bu testler çalıştırılmış sayılmaz. Ayrıntı: `docs/DEMO_CONTENT_DELETE_ATOMICITY_0_10_31.md`.

## 0.10.11 notu

Site Builder geçmiş sürüm geri alma işlemi, snapshot içeriğini uygulamadan önce doğrular. Slug çakışması veya geçersiz eski kayıt, mevcut taslağı değiştirmeden durdurulur. Geri alma yalnızca taslağı değiştirir; canlı yayını değiştirmek için ayrıca yayınlama gerekir. Feature test kaynakları eklendi ancak Composer/Laravel runtime bulunmadığı için bu ortamda çalıştırılmadı. Ayrıntı: `docs/SITE_BUILDER_REVISION_RESTORE_0_10_11.md`.

### Medya depolama denetimi (salt okunur)

```bash
php artisan orvionart:inspect-media-storage
php artisan orvionart:inspect-media-storage --json
php artisan orvionart:inspect-media-references --json
```

Komut, yönetilen medya klasörlerindeki veritabanı referansı bulunmayan dosyaları raporlar; dosya silmez. Ayrıntı: `docs/MEDIA_STORAGE_INSPECTION_0_10_12.md`.



### Sitemap yayın tarihi
Sitemap `lastmod` alanı taslak düzenlemelerini değil, herkese açık sürümün yayın tarihini yansıtır. Ayrıntı için `docs/SITEMAP_PUBLICATION_LASTMOD_0_10_25.md` dosyasına bakın.


## Site Builder geçmiş sürüm güvenliği (0.10.27)

Geçmiş sürüm snapshot'ları uygulanmadan önce canonical URL, SEO metinleri, menü/indeksleme bayrakları, bölüm yapısı ve buton/slider bağlantıları tekrar doğrulanır. `javascript:`, `data:`, `vbscript:` ve protokol-relative URL'ler reddedilir. Hatalı snapshot taslağı değiştirmez. İlgili regresyon test kaynakları `tests/Feature/SiteBuilderTest.php` dosyasındadır; Composer/Laravel runtime bulunmadığından bu ortamda çalıştırılmamıştır. Ayrıntı: `docs/SITE_BUILDER_RESTORE_SNAPSHOT_VALIDATION_0_10_27.md`.



## Site Builder menü bağlantısı etkinleştirme koruması (0.10.37)

Menü öğesini etkinleştirirken hedef sayfa güncel satır kilidi altında yeniden kontrol edilir. Taslak veya artık yayımlanmayan sayfalara bağlantılar etkinleştirilemez; header menüsü için yayımlanmış menü uygunluğu da doğrulanır. Haricî bağlantıların HTTPS biçimi etkinleştirme anında tekrar kontrol edilir. Uygun olmayan hedefte değişiklik ve audit kaydı oluşturulmaz; bağlantıyı devre dışı bırakma işlemi her durumda kullanılabilir. Bir Laravel Feature test kaynağı eklendi ancak bu ortamda Composer/runtime olmadığından çalıştırılmadı. Ayrıntı: `docs/SITE_BUILDER_NAVIGATION_ACTIVATION_GUARD_0_10_37.md`.

## Site Builder ayar ve menü işlemlerinde atomiklik (0.10.36)

Genel site ayarlarının kaydı ile audit olayı aynı transaction içindedir; mevcut ayar satırı kilit altında yeniden okunur. Menü bağlantısı oluşturma, etkinleştirme/devre dışı bırakma ve silme işlemleri de denetim kaydıyla birlikte transaction kullanır. Sayfaya bağlı menü öğesi eklenirken hedef sayfanın yayımlanmış ve menüye uygun durumu işlem içinde kilit altında tekrar doğrulanır. Menü toggle işlemi eski route modelinden değil, güncel kilitli satırdan hareket eder. Üç regresyon Feature testi kaynak kodu eklendi; Composer/Laravel runtime bu ortamda bulunmadığından çalıştırılmış sayılmaz. Ayrıntı: `docs/SITE_BUILDER_NAVIGATION_SETTINGS_ATOMICITY_0_10_36.md`.


## Site Builder kilitli sayfa geçişleri (0.10.38)

Taslak güncelleme, yayınlama, yayından kaldırma ve geçmiş sürüm geri alma işlemleri sayfa satırını transaction içinde kilitleyip güncel durum üzerinden yürütür. Yayınlamada slug çakışması ile medya referansları güncel taslak üzerinde tekrar doğrulanır; revision ve audit olayları veri değişikliğiyle aynı transaction içindedir. Ayrıntı: `docs/SITE_BUILDER_LOCKED_PAGE_TRANSITIONS_0_10_38.md`.


## Site Builder geçmiş sürümlerinde medya koruması (0.10.39)

Medya silme güvenliği artık Site Builder sayfasının mevcut taslağı ve yayındaki snapshot'ına ek olarak saklanan geçmiş revision snapshot'larını da kontrol eder. Geçmiş bir sürüm bölüm görselini veya sosyal paylaşım görselini kullanıyorsa medya dosyası silinemez; böylece sonradan sürüm geri alma işleminin medya kaybı nedeniyle başarısız olması önlenir. İki regresyon Feature testi kaynak kodu eklendi. Composer/Laravel runtime bulunmadığından bu ortamda çalıştırılmadı. Ayrıntı: `docs/MEDIA_HISTORICAL_REVISION_REFERENCE_GUARD_0_10_39.md`.


## Zamanlanmış QR/NFC hedeflerinin platform denetimi (0.10.40)

Bütünleşik platform denetimi, gelecekte yayımlanması planlanan bir deneyime bağlı etkin QR/NFC bağlantısını kırık hedef olarak işaretlemek yerine ayrı bir bekleme sayacı ve ID listesinde gösterir. Gerçekten kullanılamayan hedefler için uyarı korunur. Denetim salt okunurdur ve QR bağlantısı durumunu değiştirmez. Ayrıntı: `docs/PLATFORM_INTEGRITY_SCHEDULED_QR_CLASSIFICATION_0_10_40.md`.

## 0.10.44 — Site Builder medya referansı kilitleri

Sayfa taslağı güncelleme, yayınlama ve revision geri alma işlemleri, medya silme ile tutarlı kilit sırası kullanır ve kilitler alındıktan sonra görsel referanslarını yeniden doğrular. Ayrıntılar: `docs/SITE_BUILDER_MEDIA_REFERENCE_LOCK_ORDER_0_10_44.md`.


## 0.10.51 video yükleme notu
Video parçaları artık istemci SHA-256 değerini `X-Chunk-SHA256` başlığıyla gönderir ve sunucu değeri doğrular. Tamamlama yanıtı ağda kaybolursa istemci tamamlanmış oturumu yeniden kullanır; commit sonrası geçici dosya temizliği hatası tamamlanmış medya dosyasını silmez. Bu değişiklikler kaynak ve statik kontrollerle doğrulanır; Laravel Feature testleri ve gerçek Plesk/mobil kabul testleri ayrıca çalıştırılmalıdır.


## Video processing worker (0.10.51)
The `.env.example` defaults to `QUEUE_CONNECTION=database` and `DB_QUEUE_RETRY_AFTER=360`. After migrations, run a worker such as `php artisan queue:work database --queue=media,default --tries=3 --timeout=300`; the queue retry-after must exceed the worker timeout. Install FFmpeg/FFprobe on the worker host and set `ORVIONART_FFMPEG_BINARY` / `ORVIONART_FFPROBE_BINARY` if needed. The video job preserves the original upload and creates a verified H.264/AAC MP4 derivative plus JPEG thumbnail before marking the video ready. See `docs/VIDEO_TRANSCODING_AND_THUMBNAILS_0_10_53.md`.


## 0.10.56 — Basit QR/NFC içerik akışı
Deneyim düzenleme ekranından doğrudan QR/NFC hedefi oluşturulabilir. Aynı ekranda kalıcı adres kopyalanır (NFC yazımı için), QR SVG indirilebilir ve okutma sayısı görülebilir. Gerçek cihaz ve sunucu testi beklenmektedir.


## Süper Admin basit kullanım akışı (0.10.57)
1. Deneyim Yönetimi bölümünden içeriği oluşturun.
2. İçerik ekranındaki medya kısayoluyla fotoğraf/video ekleyin.
3. İçeriği yayımlayın ve aynı ekrandan QR/NFC bağlantısı oluşturun.
4. QR dosyasını indirin; NFC etiketine aynı kalıcı URL yazılır.
5. Gerçek sunucuda telefonla tarama kabul testi yapılmadan kurulum tamamlanmış sayılmaz.


## QR/NFC envanter dışa aktarımı (0.10.73)
Süper Admin → QR/NFC bağlantıları ekranında arama/durum filtrelerini uyguladıktan sonra **CSV dışa aktar** ile en fazla 5.000 kayıtlık UTF-8 CSV indirebilirsiniz. Dosya kalıcı QR/NFC adreslerini içerir; elektronik tablo formül enjeksiyonuna karşı metin alanları nötrleştirilir. Gerçek alan adı ve HTTPS doğrulanmadan dosyayı toplu baskıda kullanmayın. Ayrıntı: `docs/QR_NFC_CSV_EXPORT_0_10_73.md`.
