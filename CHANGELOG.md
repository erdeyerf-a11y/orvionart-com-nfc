## [0.10.74] — Kontrollü güncelleme paketi ön denetimi

- Eklendi: Dağıtım ZIP’i için salt-okunur SHA-256, ZIP yolu/tekrar, gizli dosya/runtime içeriği, symlink, sürüm, manifest ve PHP sözdizimi ön denetimi.
- Eklendi: Geçerli ZIP, checksum hatası, traversal ve `.env` reddi regresyon testi.
- Güvenlik sınırı: Otomatik dağıtım, veritabanı/migration, canlı dosya değişikliği ve rollback yapılmaz.
- Composer/Laravel runtime ve gerçek Plesk/MariaDB doğrulaması bu ortamda yapılmadı.

## Güncel sürüm 0.10.73 — QR/NFC envanter CSV dışa aktarımı
- Süper Admin QR/NFC kayıtlarını arama ve durum filtresiyle CSV olarak indirebilir; kalıcı URL'ler etiket envanteri/basım hazırlığı için dışa aktarılır.
- UTF-8 BOM, CSV formül nötrleştirmesi, 5.000 kayıt sınırı, istek kısıtlaması, no-store/nosniff yanıt başlıkları ve denetim kaydı eklendi.
- İki Laravel Feature test kaynağı eklendi; Composer/Laravel runtime olmadığı için çalıştırılmadı. Yeni migration yoktur ve canlı sunucuya dağıtım yapılmadı.

## Güncel sürüm 0.10.72 — Geri yükleme sonrası staging şema doğrulaması
- `scripts/verify-restored-staging.sh` eklendi: açık staging DB onayı, DB kimliği, sunucu portu, `read_only` durumu, temel ORVIONART tabloları ve dolu migration geçmişi salt-okunur sorgularla kontrol edilir.
- Regresyon betiği geçerli şemayı ve yanlış DB kimliği, salt-okunur DB, boş şema, boş migration geçmişi, eksik temel tablo ve yanlış hedef onayı durumlarını test eder.
- Geri yükleme veya migration çalıştırılmaz; bu denetim HTTP, medya veya gerçek uygulama kabul testinin yerine geçmez.
- Composer/Laravel runtime ve gerçek Plesk/MariaDB geri yükleme tatbikatı bu ortamda yapılmadı.

## 0.10.71 — Staging geri yükleme ön kontrolü
- `scripts/restore-staging-preflight.sh`, yedek bütünlüğünü yeniden doğrular ve yalnızca açıkça adlandırılmış, boş `orvionart_staging_` veritabanına salt-okunur bağlantı testi yapar.
- Hedef veritabanı adı için ikinci onay, kaynak/hedef ayrımı, uygulama ve yedek dizini dışındaki staging hedef yolu ve boş hedef dizin kontrolleri eklendi.
- DB parolası komut satırına yazılmaz; izinleri `600` olan geçici istemci dosyası kullanılır ve işlem sonunda silinir.
- Betik SQL içe aktarmaz, medya arşivini çıkarmaz, dizin oluşturmaz ve uygulama verisini değiştirmez. Bu, gerçek geri yükleme tatbikatı değildir.
- Sahte DB istemcisi ve geçici yedek fixture'larıyla 8 güvenlik regresyon senaryosu çalıştırıldı; yeni migration yoktur.

## 0.10.70 — Yedek manifesti şeması ve doğrulama sertleştirmesi
- Yeni staging yedekleri `backup_format=2` alanını ve `contains_vendor_directory=no` beyanını içerir; gerçek `.env` ve vendor bağımlılıkları yedek paketine eklenmez.
- `verify-backup.sh` eski manifest biçimlerini geriye dönük destekler; yeni biçimde zorunlu alanları, yinelenen/bilinmeyen alanları, timestamp/engine/restore policy değerlerini ve vendor beyanını kontrol eder.
- Doğrulama SQL'i çalıştırmaz, arşivi çıkarmaz ve DB host/adı gibi metadata değerlerini yazdırmaz.
- Eski ve yeni biçim, checksum/path traversal/symlink ve hatalı manifest vakaları için regresyon testi genişletildi. Yeni migration yoktur.
- Shell regresyon testi bu ortamda çalıştırıldı ve geçti; gerçek staging DB geri yüklemesi yapılmadı.

## 0.10.69 — Kurulum hazırlık denetimi: upload, queue ve video ikilileri
- `orvionart:release-check` PHP `upload_max_filesize` ve `post_max_size` sınırlarını kontrol ederek parçalı video yükleme için düşük değerleri uyarı olarak raporlar.
- `QUEUE_CONNECTION=sync` veya boş sürücü arka plan işlerinin beklenen biçimde çalışmayabileceğini bildirir.
- Video dönüştürme etkinse FFmpeg ve FFprobe ikililerinin bulunabilirliği güvenli biçimde kontrol edilir; sunucu yolları rapora yazılmaz.
- Denetim salt okunurdur; yapılandırma, dosya veya veritabanı değiştirmez. Yeni migration yoktur.
- Regresyon testi kaynağı, Release Gate kontrolü ve teknik belge eklendi. Composer/Laravel runtime ve gerçek Plesk/video kabul testleri bu ortamda çalıştırılmadı.

## 0.10.68 — Site Builder bölüm öğesi ve bağlantı doğrulaması
- Site Builder bölüm içeriklerinde en fazla 100 dolu öğe kabul edilir; bu sınır yeni kayıt ve revizyon geri yükleme kurallarıyla uyumlu tutulur.
- Öğe başlığı/açıklaması ile slider düğme metni ve adres uzunlukları doğrulanır.
- Bölüm ve slider bağlantıları ortak güvenli bağlantı doğrulamasından geçer; geçersiz HTTPS adresleri, kullanıcı bilgisi içeren URL'ler, kontrol karakterleri ve tehlikeli şemalar reddedilir.
- Üç regresyon Feature test kaynağı ve Release Gate kontrolü eklendi. Yeni migration yoktur.
- Composer/Laravel runtime ve gerçek Plesk/MariaDB kabul testleri bu ortamda çalıştırılmadı.

## 0.10.67 — Site Builder müşteri görüşleri ve istatistik bölümleri
- Site Builder bölüm türlerine Müşteri Görüşleri (`testimonials`) ve İstatistikler (`stats`) eklendi.
- Yönetim formu, müşteri görüşü için `Görüş metni | İsim`, istatistik için `Değer | Açıklama` satır biçimlerini açıklar.
- Yayınlanan sayfalarda yeni bölümler mobil uyumlu grid yapısında, semantik HTML ile gösterilir; içerik Blade escaped output ile render edilir.
- İki Feature test kaynağı ve Release Gate kontrolü eklendi. Yeni migration yoktur.
- Composer/Laravel runtime, gerçek veritabanı ve Plesk testleri bu ortamda çalıştırılmadı.

## 0.10.66 — Site Builder navigasyon bağlantısı düzenleme
- Mevcut header/footer bağlantıları artık aynı ekrandan düzenlenebilir: görünen ad, konum, hedef ve sıralama değiştirilebilir.
- Sayfa hedefi ve haricî HTTPS URL doğrulanır; yayınlanmamış sayfa ve ana menüye uygun olmayan sayfa hedefleri reddedilir.
- Düzenleme kilitli transaction ve atomik audit kaydı kullanır; audit yazımı başarısızsa değişiklik geri alınır.
- Üç Feature test kaynağı ve Release Gate kontrolü eklendi; yeni migration yoktur.
- Composer/Laravel runtime, gerçek DB ve Plesk kabul testleri bu ortamda çalıştırılmadı.

## 0.10.65 — Site Builder iletişim alanları ve footer bağlantıları
- Site Builder `contact` bölümünde genel ayarlardaki iletişim e-postası ve telefonunu tıklanabilir `mailto:` / `tel:` bağlantıları olarak göster.
- Footer'da mevcut iletişim telefonu artık görünür; telefon bağlantısının hedefi yalnızca rakamlar ve `+` işaretinden oluşturulur.
- İletişim bağlantıları mobilde sarılabilen, erişilebilir ve dokunma alanı en az 44 px olan görsel kontroller kullanır.
- Regresyon testi kaynağı, Release Gate kontrolü ve V1.5 gereksinim matrisi güncellendi. Veritabanı migration'ı yoktur.
- Laravel Feature testi bu kaynak ortamında çalıştırılmadı; runtime doğrulaması kurulu Laravel bağımlılıkları olan ortamda yapılmalıdır.

## 0.10.64 — Site Builder varsayılan sosyal paylaşım görseli

- Genel Site Builder ayarlarına varsayılan sosyal paylaşım görseli yükleme, görüntüleme ve kaldırma eklendi.
- Sayfaya özel görsel yoksa yayınlanmış sayfalar Open Graph ve Twitter büyük görsel meta etiketlerinde bu varsayılan görseli kullanır; sayfaya özel görsel önceliğini korur.
- Görseller özel depolamada tutulur; yalnızca PNG/JPEG/WebP kabul edilir, 5 MB sınırı uygulanır ve güvenli marka varlığı rotası kullanılır.
- Dosya değişimi/kaldırma, ayar ve denetim kaydı transaction düzenini ve eski logo/favicon ayar uyumluluğunu korur.
- İki Feature test kaynağı ve Release Gate kontrolü eklendi. Bu ortamda Composer/Laravel runtime bulunmadığından Feature testleri çalıştırılmış sayılmaz.

## 0.10.63 — 2026-10-16

- Süper Admin QR/NFC kayıt listesine etiket veya bağlı deneyim adına göre arama eklendi.
- Etkin/devre dışı bağlantı filtresi eklendi; arama ve durum filtreleri birlikte kullanılabilir.
- Sayfalama sırasında arama/filtre parametreleri korunur; mobil görünümde filtre formu tek sütuna iner.
- Arama, durum filtresi ve sonuç izolasyonu için Laravel Feature test kaynağı eklendi.
- Yeni migration eklenmedi; Composer/Laravel runtime, gerçek veritabanı ve Plesk kabul testleri bu ortamda çalıştırılmadı.

## 0.10.62 — QR/NFC kullanılamayan içerik ekranı
- Bilinen bir QR/NFC bağlantısı devre dışı bırakılmışsa veya hedef içerik yayında değilse ziyaretçiye standart sunucu hata sayfası yerine mobil uyumlu ORVIONART açıklama ekranı gösterilir.
- Erişim bitiş zamanı geçmiş içerik için HTTP 410 (Gone) ve süre sonunu açıklayan sayfa kullanılır; devre dışı/yayında olmayan hedefler içerik ayrıntısı açığa çıkarmadan 404 yanıtı verir.
- Başarısız veya kapalı bağlantılar `scan_count` ve `last_scanned_at` değerlerini artırmaz. Başarılı yönlendirmede mevcut kalıcı `/go/{token}` akışı korunur.
- Yanıtlar no-store, noindex ve no-referrer başlıklarını korur; yeni migration eklenmedi.
- Feature test kaynakları ve Release Gate kontrolü güncellendi. Bu ortamda Composer/Laravel runtime yoksa Feature testleri çalıştırılmış sayılmaz; gerçek Plesk/telefon testi ayrıca gereklidir.

# Changelog

## 0.10.61 — Markalı QR ürün etiketi yazdırma
- QR önizleme ekranı artık yalnızca kodu değil, ORVIONART marka adı, deneyim başlığı ve ziyaretçiye kısa yönlendirme içeren yazdırılabilir ürün etiketi düzenini gösterir.
- Yazdırma CSS'i yalnızca etiket alanını basar; yönetim kontrolleri, NFC kopyalama alanı ve durum metinleri kağıda çıkmaz.
- Etiket düzeni mobil önizlemeye uyumlu; QR'nin beyaz güvenli boşluğu korunur.
- QR önizleme Feature test kaynağı etiket başlığı ve yönlendirme metnini de doğrular. Laravel runtime bu ortamda mevcut olmadığından Feature test çalıştırılmış sayılmaz.
- Ayrıntı: `docs/QR_PRINTABLE_PRODUCT_LABEL_0_10_61.md`.

## 0.10.60 — Deneyim erişim süresi

- Süper Admin deneyim formuna isteğe bağlı erişim bitiş zamanı eklendi.
- Bitiş zamanı geldiğinde doğrudan deneyim URL'si ve QR/NFC yönlendirmesi sunucu tarafında kapatılır; QR tokeni değişmez.
- Süresi dolmuş QR okutması tarama sayacını artırmaz.
- Zamanlanmış deneyimde bitiş zamanı başlangıçtan sonra doğrulanır.
- Nullable migration eski deneyimleri etkilemez; eski form istemcileri alanı hiç göndermezse mevcut tarih korunur.
- Feature test kaynakları ve Release Gate kontrolü eklendi. Laravel runtime ve gerçek Plesk kabulü ayrı doğrulama gerektirir.

## 0.10.59 — QR/NFC önizleme ve baskı kolaylığı
- QR/NFC yönetimine, QR kodunu indirmeden önce ekranda görmeyi sağlayan yönetici önizleme sayfası eklendi.
- Önizleme sayfasında gerçek QR SVG'si satır içi sunulur; yazdırma görünümü ve mobil uyumlu NFC adresi kopyalama alanı bulunur.
- QR liste ekranına “QR önizle” ve “NFC adresini kopyala” işlemleri eklendi; deneyim düzenleme ekranından da önizlemeye ulaşılabilir.
- Önizleme ve indirme aynı kalıcı `/go/{token}` hedefini kullanır. Her görsel isteğinde bağlantının etkinliği ve deneyimin yayın erişimi yeniden doğrulanır.
- İki Laravel Feature test kaynağı eklendi. Bu ortamda Composer/Laravel runtime bulunmadığından testler çalıştırılmış sayılmaz; Plesk ve gerçek telefon/baskı kabulü Manus tarafından yapılmalıdır.

## 0.10.58 — QR/NFC akışı tutarlılık düzeltmesi
- Deneyim listesi ekranındaki eski ve yanıltıcı “QR/NFC ve medya henüz etkin değil” metni kaldırıldı; gerçek yönetim akışıyla uyumlu açıklama eklendi.
- Deneyim listesinin QR/NFC ve medya akışını doğru anlattığını doğrulayan Laravel Feature test kaynağı eklendi.
- QR/NFC mimarisi statik olarak incelendi: yönetim paneli 40 karakterli kalıcı token üretir, QR gerçek `/go/{token}` URL'sini SVG olarak kodlar, NFC için aynı URL kopyalanır ve yönlendirme mevcut deneyim adresine gider.
- Bu değişiklik yalnızca yönetim arayüzündeki tutarsız metni ve regresyon kapsamını düzeltir. Composer/Laravel testleri, gerçek sunucu ve gerçek telefon kabulü bu ortamda çalıştırılmadı.

## 0.10.57 — Basit Süper Admin içerik akışı
- Dashboard, gerçek kayıt sayılarını ve yönetici için beş adımlı içerik → medya → yayın → QR/NFC kullanım yolunu gösterir.
- Deneyim düzenleme ekranına doğrudan medya ekleme kısayolu eklendi.
- Medya yükleme sayfası deneyim düzenleme ekranından açıldığında deneyim hem standart hem parçalı video yükleme alanında önceden seçilir.
- Deneyime bağlı standart medya ve büyük video yüklemesi, işlemden sonra mümkün olduğunda ilgili deneyim düzenleme ekranına döner.
- Üç Laravel Feature test kaynağı eklendi. Composer/Laravel runtime yoksa bunlar çalıştırılmış sayılmaz. Yeni migration eklenmedi; Plesk ve gerçek cihaz kabul testleri yapılmadı.

## 0.10.56 — Deneyimden doğrudan QR/NFC oluşturma
- Deneyim düzenleme ekranına, deneyim yayımlandıktan veya zamanlandıktan sonra doğrudan QR/NFC bağlantısı oluşturma alanı eklendi.
- Oluşturulan bağlantı aynı ekranda listelenir; kalıcı URL görünür, kopyalanabilir ve NFC etiketine yazılmak üzere kullanılabilir.
- Aynı ekrandan QR SVG indirme, bağlantı durumunu ve okutma sayısını görme sağlandı. QR/NFC yönetimi için ayrı menüye gitme zorunluluğu azaltıldı.
- QR bağlantısı oluşturulduktan sonra kullanıcı ilgili deneyim ekranına döner. Taslak/arşiv içerikler için bağlantı oluşturma formu gösterilmez; sunucu tarafı kontrolü korunur.
- Laravel Feature test kaynakları eklendi. Composer/Laravel runtime yoksa testler çalıştırılmış sayılmaz; Plesk ve gerçek telefonla QR/NFC kabulü yapılmış değildir.

## 0.10.55 — Site Builder logo ve favicon yönetimi
- Genel Site Builder ayarlarına logo ve favicon yükleme/değiştirme/kaldırma eklendi. PNG, JPEG ve WebP kabul edilir; logo 4 MB, favicon 2 MB ile sınırlıdır. SVG ve onaysız türler reddedilir.
- Marka dosyaları özel yerel depolamada tutulur. Görseller yalnızca `logo`/`favicon` sabit uçlarından, veritabanındaki dosya yolu özel klasör ve oluşturulan dosya adı desenini geçerse sunulur; `nosniff` ve `no-store` başlıkları uygulanır.
- Ayar kaydı ve denetim olayı transaction içinde tutulur. Yeni dosyalar DB/audit hatasında temizlenir; eski dosya yalnızca yeni referans kaydedildikten sonra ve artık başka marka alanı tarafından kullanılmıyorsa silinir. Eski form istemcileri logo/favicon referanslarını korur.
- Yayındaki Site Builder sayfaları logo görselini başlıkta, faviconu `<link rel="icon">` ile kullanır; logo yoksa marka metni korunur.
- Upload/serve/replace/remove ve SVG reddi için Laravel Feature test kaynakları eklendi. Composer/Laravel runtime mevcut olmadığı için Feature testleri çalıştırılmadı; Plesk, gerçek DB ve tarayıcı kabulü yapılmadı.
- Ayrıntı: `docs/SITE_BUILDER_BRAND_ASSETS_0_10_55.md`.

# Changelog

## 0.10.54 — 2026-10-15

- Site Builder genel ayarlarına güvenli izinli tema kontrolleri eklendi: sayfa arka planı, bölüm yüzeyi, ayrı başlık/gövde yazı tipi, buton köşe stili, sticky/normal üst menü ve yan yana/alt alta footer düzeni.
- CSS değerleri serbest metin olarak kabul edilmiyor; renkler altı haneli HEX, yazı tipi ve düzen seçenekleri izin listeleriyle doğrulanıyor. Render katmanında renk değerleri tekrar güvenli biçimde filtreleniyor.
- Ayar kaydı varsayılanlar ve mevcut ayarlarla birleştiriliyor; eski form istemcileri veya eski ayar satırları yeni tema alanlarını sessizce silmiyor.
- Site Builder için iki Feature test kaynağı ve Release Gate kontrolü eklendi. Composer/Laravel runtime yoksa bu test kaynakları çalıştırılmış sayılmaz.
- Ödeme ve Demo Engine iş kuralları değiştirilmedi; canlı sunucuya dağıtım yapılmadı.


## 0.10.53 — 2026-10-15

- Video kuyruğu FFmpeg ile H.264/yuv420p + AAC MP4 türevi üretir; orijinal yükleme korunur.
- MP4 çıktısı FFprobe ile yeniden doğrulanır; JPEG küçük resim oluşturulur ve yalnızca hazır durumunda admin/kamu video sunumunda türev dosya kullanılır.
- Türetilmiş dosyalar için özel `processed_path` / `thumbnail_path` alanları ve migration eklendi.
- Video işleme işi 300 saniyelik timeout bütçesiyle güncellendi; DB queue `retry_after` varsayılanı 360 saniyeye çıkarıldı.
- Kesilmiş worker işlerinden kalabilecek eşleşen geçici MP4/JPEG dosyalarını ve 24 saatten eski, veritabanında referansı olmayan türetilmiş dosyaları temizleyen zamanlanmış komut eklendi.
- Statik kontroller güncellendi; Laravel runtime, FFmpeg/FFprobe ve gerçek sunucu testleri bu sürümde çalıştırılmış sayılmaz.

## 0.10.52 — 2026-10-15
- Video doğrulama kuyruğuna medya kaydı başına `WithoutOverlapping` kilidi eklendi; aynı videonun yinelenen kuyruk teslimleri eşzamanlı FFprobe çalıştırmıyor. Kilit süresi, iş zaman aşımının üzerinde güvenlik payıyla ayarlandı.
- FFprobe çağrısı `file,pipe` protokolleriyle sınırlandı; yüklenen dosyanın harici ağ protokollerini tetiklemesi engelleniyor.
- Kilit ve protokol kısıtlamaları için Feature test kaynakları ve Release Gate regresyon kontrolleri eklendi.
- Bu sürüm FFmpeg dönüştürme/thumbnail üretimini henüz içermez. Laravel runtime, gerçek FFprobe, worker, Plesk ve cihaz testleri çalıştırılmış sayılmaz.

## 0.10.51 — 2026-10-15
- Video parçalarında istemci tarafından hesaplanan `X-Chunk-SHA256` değeri sunucuda yeniden hesaplanan SHA-256 ile karşılaştırılıyor; uyumsuz parça kaydedilmiyor.
- Tamamlama yanıtı kaybolduğunda istemci tamamlanmış oturumu algılıyor ve aynı videodan ikinci bir medya kaydı oluşturmuyor.
- Veritabanı commit'inden sonraki geçici dizin temizliği hatası artık tamamlanmış medya dosyasını silmiyor; temizlik hatası uyarı olarak loglanıyor.
- Video teknik doğrulama arka plan kuyruğu eklendi: FFprobe metadata kontrolü, gerçek işleme durumları, güvenli hata kaydı ve medya kütüphanesinden yeniden deneme.
- Doğrulanmamış videolar kamuya açık medya endpoint'inde sunulmaz; FFmpeg dönüştürme/thumbnail bu sürümde hâlâ yok.
- Video upload regresyon testi ve release gate kontrolleri güncellendi.
- Laravel runtime/Feature testleri, gerçek Plesk ve mobil ağ kesintisi kabul testleri bu ortamda çalıştırılmadı.

## 0.10.50 — Parçalı ve devam edebilen video yükleme

- Süper Admin için 4 MiB parçalara dayalı video yükleme oturumu, parça takibi, tekrar deneme ve tarayıcıdan devam etme arayüzü eklendi.
- Birleştirme sırasında toplam boyut ve gerçek MIME doğrulaması; başarılı kayıt için medya/audit transaction'ı eklendi.
- 512 MiB oturum sınırı, 24 saatlik süre, kullanıcı sahipliği ve geçici dosya temizleme komutu eklendi.
- Kabul edilen video türleri MP4, WebM ve OGG ile sınırlandı. Video dönüştürme/transcoding bu sürümde yoktur.
- Ayrıntı: `docs/RESUMABLE_VIDEO_UPLOAD_0_10_50.md`.

## 0.10.49 — Staging sayfalarında indeksleme koruması
- Site Builder kamu sayfaları üretim dışı ortamlarda, yayın snapshot'ının SEO bayrağından bağımsız olarak `X-Robots-Tag: noindex, nofollow, noarchive` döndürür.
- Üretimde `published_noindex` bayrağının mevcut davranışı korunur.
- Staging'de `published_noindex=false` olan sayfanın da indekslenmemesi için Feature test kaynağı ve belge eklendi.
- Composer/runtime bulunmadığından Laravel Feature testleri çalıştırılmadı; gerçek staging HTTP kabulü bekliyor.
- Ayrıntı: `docs/STAGING_PUBLIC_SITE_NOINDEX_0_10_49.md`.

# ORVIONART 0.10.48 — 2026-10-15

## Arama motoru indekslemesi için açık izin
- `APP_ENV=production` tek başına arama motoru indekslemesini açmaz; `ORVIONART_PUBLIC_INDEXING_ENABLED` ayrıca `true` olmalıdır.
- Varsayılan `false` ile üretim modunda çalışan staging kopyaları `robots.txt` üzerinden taramayı kapalı tutar ve sitemap boş URL listesi döndürür.
- Üretim sitemap regresyon testleri açık indeksleme ayarıyla çalışacak şekilde güncellendi; kapalı/açık davranış için test kaynağı eklendi.
- Bu ayar erişim kontrolü değildir; staging ayrıca parola/erişim koruması kullanmalıdır.
- Ayrıntı: `docs/SEARCH_INDEXING_EXPLICIT_OPT_IN_0_10_48.md`.

# ORVIONART 0.10.47 — 2026-10-15

## Site Builder kamu sayfalarında önbellek yaşam döngüsü
- Ana sayfa ve yayımlanmış Site Builder sayfaları artık `Cache-Control: no-store, private` gönderir.
- Yayından kaldırma, taslağa alma veya arşivleme sonrasında tarayıcı/paylaşımlı önbelleğin önceki kamu sayfasını tutmaya devam etmesi riski azaltıldı.
- Ana sayfa, standart sayfa ve deneyim listesi için Feature test kaynakları güncellendi; Composer/Laravel runtime olmadığından bu testler çalıştırılmış sayılmaz.
- Ayrıntı: `docs/SITE_BUILDER_PUBLIC_CACHE_LIFECYCLE_0_10_47.md`.

# ORVIONART 0.10.46 — 2026-10-15

## QR/NFC görsel üretiminde güncel yaşam döngüsü doğrulaması
- Yönetici QR SVG indirme uç noktası, route-model binding sırasında yüklenmiş eski etkinlik durumuna güvenmek yerine deneyim ve QR kaydını veritabanı transaction'ında yeniden okur ve kilitler.
- Deneyim -> QR bağlantısı tutarlı kilit sırasını kullanır; devre dışı QR veya taslak/arşiv hedefi için QR görseli üretimi reddedilir.
- Gelecekte yayımlanması planlanan etkin deneyimler için QR görseli önceden indirilebilir; bu, baskı ve NFC hazırlığını engellemez.
- Devre dışı bağlantı, taslak hedef ve gelecekte zamanlanmış hedef için üç regresyon test kaynağı eklendi.
- Ayrıntı: `docs/QR_IMAGE_LIFECYCLE_GUARD_0_10_46.md`.

## Doğrulama sınırı
- PHP/Shell sözdizimi, statik Release Gate, migration statik denetimi ve yardımcı regresyon betikleri çalıştırılır.
- Composer/`vendor/autoload.php` bulunmadığında Laravel Feature testleri çalıştırılmış sayılmaz.
- Gerçek veritabanı eşzamanlılık, staging ve Plesk kabul testleri ayrıca gereklidir.

---

# ORVIONART 0.10.45 — 2026-10-15

## Güvenlik başlıklarında route-specific CSP koruması
- Global `SecurityHeaders` middleware'i, yanıt üzerinde zaten tanımlanmış `Content-Security-Policy` başlığını ezmez.
- Bu düzeltme, yönetici medya önizlemesindeki `default-src 'none'; sandbox` politikasının genel CSP ile değiştirilip zayıflatılmasını önler.
- Medya önizlemesinde özel CSP'nin korunmasını ve normal yanıtların varsayılan CSP almasını doğrulayan iki Feature test beklentisi eklendi.
- Ayrıntı: `docs/SECURITY_HEADERS_ROUTE_CSP_PRESERVATION_0_10_45.md`.

## Doğrulama sınırı
- PHP/Shell sözdizimi ve statik Release Gate kontrolleri çalıştırılır.
- Composer/`vendor/autoload.php` yoksa Laravel Feature testleri çalıştırılmış sayılmaz.
- Tarayıcı ve gerçek sunucu kabul testleri ayrıca gereklidir.

---

# ORVIONART 0.10.44 — 2026-10-15

## Site Builder medya referanslarında yarış koşulunu azaltma
- Taslak güncelleme, yayınlama ve geçmiş sürüme geri alma işlemleri sayfaya ait medya kayıtlarını deterministik ID sırasıyla kilitleyip ardından sayfa kaydını kilitler.
- Taslak güncelleme, transaction içindeki kilitler alındıktan sonra bölüm ve sosyal paylaşım görseli referanslarını yeniden doğrular; eski ön doğrulama sonucuna tek başına güvenmez.
- Yayınlama ve geri alma, medya satırları kilitliyken görsel referanslarını tekrar doğrular. Bu, medya silme ile içerik kaydetme/yayınlama arasındaki yarış riskini azaltır.
- Eksik medya referansıyla yayınlamayı reddeden Feature test kaynağı ve Release Gate kilit sırası kontrolü eklendi.
- Ayrıntı: `docs/SITE_BUILDER_MEDIA_REFERENCE_LOCK_ORDER_0_10_44.md`.

## Doğrulama sınırı
- PHP/Shell sözdizimi ve statik Release Gate kontrolleri çalıştırılır.
- Composer/`vendor/autoload.php` bulunmadığında Laravel Feature testleri çalıştırılmış sayılmaz.
- Gerçek MySQL/MariaDB eşzamanlılık, staging ve Plesk kabul testleri ayrıca gereklidir.

---

# ORVIONART 0.10.43 — 2026-10-15

## Kurulum ön kontrolünde veritabanı yer tutucularını engelleme
- `scripts/preflight.php`, `--check-db` verilmemiş olsa bile veritabanı sürücüsünü, bağlantı alanlarının boş/yer tutucu olup olmadığını ve port aralığını kontrol eder.
- MySQL/MariaDB odaklı mevcut kaynak paketiyle uyumsuz `DB_CONNECTION` bloklanır.
- Eksik veya örnek DB host/name/user/password değerleri güvenli biçimde raporlanır; gerçek değerler rapora yazılmaz.
- Preflight regresyon testi, örnek kullanıcı/parola değerlerinin gerçek bağlantı testi istenmeden önce engellendiğini ve değerlerin rapora sızmadığını doğrular.
- Bu kontroller bağlantının gerçekten başarılı olduğunu kanıtlamaz; bunun için `php scripts/preflight.php --check-db` gerekir.

## Doğrulama sınırı
- PHP/Shell sözdizimi, preflight regresyonu, migration statik denetimi ve yedek doğrulama regresyonları çalıştırılır.
- Composer ve `vendor/autoload.php` bulunmadığında Laravel Feature testleri çalıştırılmış sayılmaz.
- Gerçek staging/DB/Plesk kabul testleri ayrıca gereklidir.

---

# ORVIONART 0.10.42 — 2026-10-15

## Kurulum yapılandırması ve dağıtım engeli düzeltmesi
- Güvenli, sırlardan arındırılmış `.env.example` şablonu eklendi; uygulama anahtarı ve veritabanı parolası boş/yer tutucu olarak bırakıldı.
- Ödeme özelliği kapalı, debug kapalı ve HTTPS oturum çerezi güvenli varsayılan olarak ayarlandı.
- İlk kurulumda cache/session tablosu bağımlılığını azaltmak için şablonda dosya tabanlı cache ve session ile senkron queue seçildi.
- README'ye ortam dosyası kopyalama, uygulama anahtarı üretme ve HTTPS yapılandırma adımları eklendi.
- Release Gate, `.env.example` varlığını ve güvenli temel ayarlarını doğrulayacak şekilde genişletildi.
- Kaynak manifesti yeniden oluşturuldu; gerçek `.env`, Composer bağımlılıkları ve üretim sırları pakete dahil edilmedi.

## Doğrulama sınırı
- PHP ve shell statik kontrolleri çalıştırılır.
- Composer ve `vendor/autoload.php` bulunmadığında Laravel çalışma zamanı/Feature testleri çalıştırılamaz.
- Gerçek veritabanı, staging ve Plesk kabul testleri ayrıca gereklidir.

## 0.10.41 — Platform bütünlük denetiminde geçmiş sürüm medya referansları

- Salt okunur `orvionart:inspect-platform` komutu, saklanan Site Builder revision snapshot'larındaki bölüm, öğe ve sosyal görsel referanslarını da tarar.
- Eksik medya kaydı, yanlış sayfa sahipliği, desteklenmeyen MIME ve kayıp dosya sorunları revision ve sayfa kimlikleriyle raporlanır.
- Revision geçmişi 100 kayıtlık gruplarla taranır; otomatik veri düzeltme veya dosya değiştirme yapılmaz.
- İki regresyon Feature testi ve teknik belge eklendi. Composer/Laravel runtime bulunmadığından bu testlerin çalıştırıldığı iddia edilmez.
- Ayrıntı: `docs/PLATFORM_INTEGRITY_HISTORICAL_MEDIA_0_10_41.md`.

## 0.10.40 — Zamanlanmış QR/NFC hedeflerini doğru sınıflandırma

- Bütünleşik platform denetimi, gelecekte yayımlanacak deneyimlere bağlı etkin QR/NFC bağlantılarını kırık hedef uyarısından ayırır.
- Bekleyen bağlantılar sayaç ve ID listesiyle raporlanır; denetim salt okunur kalır.
- Yeni regresyon Feature testi ve teknik belge eklendi.
- Ayrıntı: `docs/PLATFORM_INTEGRITY_SCHEDULED_QR_CLASSIFICATION_0_10_40.md`.

## 0.10.39 — Site Builder geçmiş sürümlerindeki medya referanslarını koruma
- Medya silme güvenlik kontrolü artık yalnızca mevcut taslak/yayın snapshot'ını değil, Site Builder'ın saklanan geçmiş sürümlerini de inceler.
- Eski bir bölüm görseli veya sosyal paylaşım görseli geçmiş snapshot'ta kullanılıyorsa medya kaydı ve dosyası silinmeye uygun sayılmaz; böylece sürüm geri alma yeteneğinin sessizce bozulması önlenir.
- Snapshot taraması sayfaya ait revision kayıtlarını 100 kayıtlık parçalarla okur; ilk referans bulunduğunda taramayı durdurur.
- İki regresyon Feature test kaynağı eklendi. Composer/Laravel runtime bu ortamda bulunmadığından Feature testleri çalıştırılmış sayılmaz.
- Ayrıntı: `docs/MEDIA_HISTORICAL_REVISION_REFERENCE_GUARD_0_10_39.md`.

## 0.10.38 — Site Builder kilitli sayfa geçişleri
- Taslak güncelleme, yayınlama, yayından kaldırma ve geçmiş sürüm geri alma işlemleri route-bound eski model yerine transaction içinde kilit altında yeniden okunan güncel sayfa satırını kullanır.
- Yayın slug çakışması ile bölüm/sosyal görsel referansları yayın transaction'ı içinde tekrar doğrulanır; yayın snapshot'ı, yönlendirme, revision ve audit aynı transaction içinde tutulur.
- Sürüm geçişi ve audit metadata'sı için bir Feature test kaynağı eklendi. Composer/Laravel runtime bulunmadığı durumda test çalıştırılmış sayılmaz.
- Ayrıntı: `docs/SITE_BUILDER_LOCKED_PAGE_TRANSITIONS_0_10_38.md`.

## 0.10.37 — Site Builder menü bağlantısı etkinleştirme koruması
- Menü öğesi etkinleştirilirken sayfa hedefi satır kilidi altında yeniden doğrulanır; taslak/yayından kalkmış hedefler etkinleştirilemez.
- Header menüsünde yayımlanmış menü uygunluğu yeniden kontrol edilir; haricî hedeflerde HTTPS biçimi tekrar doğrulanır.
- Uygun olmayan hedefte değişiklik ve audit olayı yazılmaz; devre dışı bırakma işlemi kullanılabilir kalır.
- Bir Laravel Feature test kaynağı ve teknik belge eklendi. Composer/runtime olmadığı için Feature test çalıştırılmadı.
- Ayrıntı: `docs/SITE_BUILDER_NAVIGATION_ACTIVATION_GUARD_0_10_37.md`.

## 0.10.36 — Site Builder ayar ve menü işlemlerinde atomiklik
- Genel site ayarlarının güncellenmesi ve `site_settings.updated` audit kaydı aynı transaction içine alındı; ayar satırı kilit altında yeniden okunur.
- Menü oluşturma, toggle ve silme işlemleri audit olayıyla atomik hale getirildi. Sayfa bağlantısı oluşturulurken yayın ve header menü uygunluğu işlem içinde kilitli hedef üzerinden yeniden doğrulanır.
- Menü toggle, route-bound eski durum yerine kilit altında yeniden okunan mevcut değeri tersine çevirir; sıralama işlemi kilitli kardeş kayıtları kullanmaya devam eder.
- Ayar audit hatasında rollback, menü toggle denetim değerinin eşleşmesi ve silme audit hatasında rollback için üç Feature regresyon testi eklendi. Composer/Laravel runtime bulunmadığından testler çalıştırılmadı.
- Ayrıntı: `docs/SITE_BUILDER_NAVIGATION_SETTINGS_ATOMICITY_0_10_36.md`.

## 0.10.35 — Demo Engine yönetici durum değişikliklerinde atomiklik
- Demo Engine genel ayar değişikliği artık ayar satırını kilitleyen bir veritabanı transaction'ı içinde kaydedilir; audit kaydı başarısız olursa ayar değişikliği de geri alınır.
- Demo içerik etkinleştirme/devre dışı bırakma işlemi, route-bound eski model yerine kilit altında yeniden okunan güncel kayıt üzerinden yapılır.
- İçerik durumu ve audit olayı aynı transaction içinde yazılır; eşzamanlı isteklerde eski durumdan toggle yapılması riski azaltılır.
- İki regresyon Feature testi ve doğrulama sınırlarını açıklayan belge eklendi. Composer/Laravel runtime bu ortamda yoksa Feature testleri çalıştırılmış sayılmaz; gerçek staging veritabanı kabulü gereklidir.

## 0.10.35 — Site Builder medya yüklemesinde atomiklik
- Site Builder medya yüklemesinde `MediaAsset` kaydı ile `site_page.media_uploaded` denetim kaydı aynı veritabanı transaction'ına alındı. Denetim kaydı başarısız olursa medya satırı rollback edilir ve yüklenen dosya temizlenmeye çalışılır.
- Başarısız denetim kaydı ve başarılı yükleme akışları için iki Feature regresyon testi eklendi.
- Composer/Laravel çalışma zamanı bu ortamda bulunmadığından Feature testleri çalıştırılmadı; gerçek staging kabulü ayrıca gereklidir.
- Ayrıntı: `docs/SITE_BUILDER_MEDIA_UPLOAD_ATOMICITY_0_10_35.md`.

## 0.10.34 — Genel medya sunumunda normal dosya hedefi zorunluluğu
- Genel medya teslimi, özel disk kökü içinde çözümlenen yolun ayrıca normal bir dosya (`is_file`) olmasını şart koşar; yönetilen klasör altında görünse bile dizin veya normal dosya olmayan hedefler `404` ile reddedilir.
- Dizin olarak oluşturulmuş ancak görsel uzantısı taşıyan bir medya yolu için regresyon Feature testi eklendi.
- Bu testin kaynak kodu eklendi; Composer/Laravel çalışma zamanı mevcut olmadığı için bu ortamda çalıştırılmadı. Gerçek staging kabulü ayrıca gereklidir.
- Ayrıntı: `docs/PUBLIC_MEDIA_FILE_TARGET_0_10_34.md`.

## 0.10.33 — Medya referanslarında dosya sistemi hedef doğrulaması
- Medya referans denetimi `realpath` ile gerçek dosya hedefini kontrol eder; özel `local` disk kökü dışına çıkan sembolik bağlantıları ve normal dosya olmayan hedefleri salt okunur biçimde raporlar.
- JSON ve metin özetine `unsafe_filesystem_targets` sayacı eklendi.
- Dış hedefe yönelen sembolik bağlantı için regresyon Feature test kaynağı eklendi; Composer/Laravel runtime bulunmadığından bu ortamda çalıştırılmadı.
- Ayrıntı: `docs/MEDIA_REFERENCE_INSPECTION_0_10_33.md`.

## 0.10.31 — Demo Engine içerik silme ve atama atomikliği
- Demo içeriği silinmeden önce kayıt transaction içinde kilitlenir ve günlük atama geçmişi yeniden kontrol edilir.
- Medya silme işlemi `local` diski ve `orvionart-demo/` altındaki güvenli göreli yollarla sınırlandırılır; diğer disk/yollar korunarak 409 döndürür.
- İçerik kaydı ve denetim kaydı aynı transaction içinde; dosya silme ise commit sonrasında yapılır.
- Genel demo akışında günlük atama kararı ve `content_view` olayı tek transaction içinde, oturum ve içerik satırı kilitleriyle yazılır.
- Üç regresyon Feature test kaynağı ve doğrulama sınırlarını açıklayan belge eklendi. Composer/Laravel runtime bulunmadığından Feature testleri bu ortamda çalıştırılmadı.

## 0.10.29 — QR/NFC yönlendirme atomikliği ve hedef doğrulaması
- QR yönlendirmesi etkin bağlantıyı ve hedef deneyimi işlem içinde yeniden okur; desteklenen veritabanlarında satır kilitleriyle eşzamanlı devre dışı bırakma/yayın değişikliği yarışlarını azaltır.
- Geçerli bir public URL üretilemeden tarama sayacı ve zaman damgası güncellenmez; `public_id` eksik eski/bozuk hedef `404` döndürür.
- Eksik `public_id` senaryosu için regresyon Feature testi ve doğrulama sınırlarını açıklayan belge eklendi.
- Composer/Laravel runtime bu ortamda bulunmadığından Feature testi çalıştırılmadı; gerçek staging veritabanında kilit davranışı ayrıca doğrulanmalı.

## 0.10.27 — Site Builder geçmiş sürüm geri alma bağlantı doğrulaması
- Geçmiş sürüm geri alma işlemi artık kayıtlı SEO alanlarını ve bölüm verilerini tekrar doğrular; bozuk snapshot verisi mevcut taslağa uygulanmaz.
- Bölüm ve slider bağlantılarında yalnızca HTTPS, site içi tek eğik çizgili yollar ve sayfa içi `#` bağlantıları kabul edilir; `javascript:`, `data:`, `vbscript:` ve protokol-relative bağlantılar reddedilir.
- Canonical adres, menü/indeksleme bayrakları, bölüm türü, metin uzunlukları, görsel kimlikleri ve öğe yapısı geri alma sırasında denetlenir.
- İki Laravel Feature test kaynağı eklendi: güvensiz slider URL'si ve geçersiz canonical adresinin reddedilmesi; hata durumunda mevcut taslağın değişmediği doğrulanır.
- Composer/Laravel runtime bu ortamda bulunmadığından Feature testleri çalıştırılmadı. Ayrıntı: `docs/SITE_BUILDER_RESTORE_SNAPSHOT_VALIDATION_0_10_27.md`.

## 0.10.26 — Sitemap köken ve tekrar koruması
- Sitemap yalnızca `APP_URL` ile aynı şema/host/etkin port üzerindeki mutlak URL'leri içerir; harici canonical URL'ler dışarıda bırakılır.
- Tekrarlanan canonical URL'ler tek kayda indirilir; en yeni herkese açık sürüm tarihi tutulur.
- Zamanlanmış deneyimlerde `lastmod` için `scheduled_at` kullanılır; eski `published_at` değeri yanlış tarih üretmez.
- Üç regresyon senaryosu için Feature test kaynağı eklendi. Composer/Laravel runtime bulunmadığından testler bu ortamda çalıştırılmadı.
- Ayrıntı: `docs/SITEMAP_ORIGIN_AND_DEDUP_0_10_26.md`.

## 0.10.25 — Site haritasında yayın sürümü tarihleri
- XML sitemap `lastmod` alanı, Site Builder taslaklarının daha sonra düzenlenmesinden etkilenmemesi için sayfanın son yayın zamanını kullanır.
- Deneyim sitemap kayıtları da genel `updated_at` yerine yayın zamanı; zamanlanmış yayınlarda zamanlanmış yayın tarihi kullanır.
- Üretim sitemap davranışı için iki Feature test kaynağı eklendi; Composer/Laravel çalışma zamanı mevcut olmadığından testler bu ortamda çalıştırılamadı.
- Ayrıntı: `docs/SITEMAP_PUBLICATION_LASTMOD_0_10_25.md`.

## 0.10.24 — Yönetici medya önizleme güvenliği
- Yönetici medya önizlemesi artık yalnızca özel `local` disk üzerindeki ORVIONART tarafından yönetilen medya yollarını sunar.
- Dizin geçişi, mutlak/Windows yolları ve özel disk kökü dışına çıkan sembolik bağlantılar reddedilir.
- Üç Feature test kaynağı ve release-gate kontrolleri eklendi. Composer/runtime mevcut olmadığından Laravel testleri bu ortamda çalıştırılmadı.
- Ayrıntı: `docs/ADMIN_MEDIA_PREVIEW_GUARD_0_10_24.md`.

## 0.10.23 — Genel medya sunumunda disk ve dosya yolu koruması
- Yayınlanmış deneyim/Site Builder medyası yalnızca `local` özel diskinden sunulur; eski `public` veya tanınmayan disk kayıtları `404` ile reddedilir.
- Medya yolu yalnızca yönetilen medya klasörleri altında kabul edilir; mutlak yol, traversal ve özel depolama kökü dışına çıkan sembolik bağlantı engellenir.
- Normal yayınlanmış medya sunumu, disk kısıtlaması, güvensiz yol ve symlink davranışları için Feature test kaynakları eklendi.
- Composer/Laravel runtime bulunmadığından Feature testleri çalıştırılmadı; canlı sunucu doğrulaması yapılmadı.
- Ayrıntı: `docs/PUBLIC_MEDIA_DELIVERY_GUARD_0_10_23.md`.

# Changelog

## 0.10.22 — Medya karantinasında özel disk zorunluluğu
- Medya silme işlemi, yalnızca `local` özel diskindeki dosyaları karantinaya taşıyabilir. `public` veya tanınmayan başka bir diskteki eski kayıtlar HTTP 409 ile durdurulur; dosya ve veritabanı kaydı korunur.
- Böylece `orvionart-trash/` klasörünün web tarafından sunulabilmesi ihtimalinde, herkese açık diskten taşınan bir dosyanın karantinada yanlışlıkla erişilebilir kalması önlenir.
- Eski `public` disk kaydının dosyasını ve veritabanı satırını koruduğunu doğrulayan Feature test kaynağı eklendi. Laravel runtime testleri bu ortamda çalıştırılamadı.
- Ayrıntı: `docs/MEDIA_PRIVATE_QUARANTINE_GUARD_0_10_22.md`.

## 0.10.21 — Denetim kaydı hijyeni
- `orvionart:inspect-audit-logs` salt okunur komutu eklendi: gelecek zaman damgaları, boş action, geçersiz IP, metadata içindeki hassas anahtar adları ve artık bulunmayan aktör referanslarını denetler.
- JSON raporu metadata değerlerini göstermez; yalnızca kontrol sayıları ve sınırlı örnek kayıt ID'leri verir.
- Üç Laravel Feature test senaryosu eklendi. Composer/Laravel çalışma zamanı bu ortamda bulunmadığından testler çalıştırılmadı.
- Ayrıntı: `docs/AUDIT_LOG_HYGIENE_0_10_21.md`.

## 0.10.20 — Yedek arşivi doğrulama güvenliği
- `scripts/verify-backup.sh`, `SHA256SUMS` dosyasını yalnızca beklenen üç dosya adına ve benzersiz kayıtlara sınırlar; checksum dosyasıyla dış yol okuma girişimlerini reddeder.
- Yedek manifestinde ORVIONART, sürüm, `.env` dahil değil beyanı ve staging-only geri yükleme politikası doğrulanır.
- Medya arşivinde mutlak/üst dizin yolları, beklenmeyen kökler, sembolik bağlar, hard link'ler ve özel dosya türleri reddedilir.
- `scripts/test-verify-backup.sh` geçerli yedek, güvensiz checksum listesi, yol geçişi, sembolik bağ ve güvensiz manifest regresyonlarını geçici fixture'larla kontrol eder.
- Doğrulama arşivi çıkartmaz ve SQL'i çalıştırmaz; gerçek geri yükleme testi değildir.

## 0.10.19 — Medya silme karantinası ve geri alma güvenliği
- Medya silme akışı, DB kaydı kaldırılmadan önce dosyayı aynı private diskteki `orvionart-trash/` alanına taşır; işlem başarısız olursa asıl konuma geri taşımayı dener.
- Dosyası eksik medya kaydı otomatik silinmez; güvensiz/management dışı yol biçimleri reddedilir.
- Karantina temizliği başarısız kalırsa yöneticiye uyarı verilir ve medya depolama denetimi bekleyen dosyaları salt okunur raporlar.
- Başarılı silme, eksik dosya, güvensiz yol ve karantinada kalan dosya için Laravel Feature test kaynakları eklendi; runtime testleri bu ortamda çalıştırılmadı.

## 0.10.18 — Paylaşılan medya yolu silme koruması
- `MediaAsset::canBeDeletedSafely()` aynı disk ve yolu kullanan başka bir `MediaAsset` kaydı olup olmadığını da kontrol eder; tek kaydın silinmesiyle başka bir kaydın fiziksel dosyasının kaybolması engellenir.
- Farklı disklerde aynı göreli yolun yanlışlıkla ortak referans sayılmadığını doğrulayan test kaynağı eklendi.
- İki Laravel Feature test senaryosu eklendi; Composer/Laravel bağımlılıkları bulunmadığından henüz çalıştırılmadı.
- Bu koruma uygulama düzeyindedir; eşzamanlı silme/yükleme yarışı gerçek DB kabul testinde ayrıca doğrulanmalıdır.

## 0.10.17 — Site Builder yönlendirme bütünlüğü denetimi
- `orvionart:inspect-site-redirects` salt okunur komutu eklendi: eski/yeni slug biçimi, kendi adresine yönlendirme, 301/302 dışı durum kodu, erişilemeyen hedef, düzenlenebilir slug ile çakışan kaynak ve büyük/küçük harf farkıyla yinelenen kaynakları raporlar.
- JSON raporu, kategori başına sayım ve örnek kayıt ID’leri içerir; yönlendirme kaydı değiştirmez.
- Dört Laravel Feature test senaryosu ve `docs/SITE_REDIRECT_INSPECTION_0_10_17.md` eklendi.
- Release gate yeni komutun kaydını, test kaynağını ve belgesini doğrular. Composer/Laravel runtime bu ortamda bulunmadığından Feature testleri çalıştırılmadı.

## 0.10.16 — Medya silme referans koruması
- `MediaAsset::canBeDeletedSafely()` artık Demo Engine içeriklerinin `media_disk` + `media_path` referansını da kontrol eder.
- Aynı disk ve yolu kullanan Demo Engine içeriği varsa medya silme işlemi engellenir; farklı disk tek başına eşleşme sayılmaz.
- Üç Laravel Feature test senaryosu ve `docs/MEDIA_DELETE_GUARD_0_10_16.md` eklendi.
- Laravel runtime/DB testleri Composer bağımlılıkları bu ortamda bulunmadığından çalıştırılmadı.

## 0.10.15 — Medya referans bütünlüğü denetimi
- `orvionart:inspect-media-references` salt okunur komutu eklendi: local disk medya referanslarında güvensiz yol biçimi, yönetilen klasör dışı yol ve dosyası bulunamayan referansları raporlar.
- Komut dosya veya veritabanı kaydını değiştirmez, silmez ya da taşımaz; JSON raporunda token veya dosya içeriği göstermez.
- Üç Laravel Feature test kaynağı ve `docs/MEDIA_REFERENCE_INSPECTION_0_10_15.md` belgesi eklendi.
- Release gate komut kaydı, test kaynağı ve belgeyi doğrular.
- Gerçek Laravel/DB testleri Composer bağımlılıkları bulunmadığından bu ortamda çalıştırılmadı.

## 0.10.14 — QR/NFC yaşam döngüsü denetimi
- `orvionart:inspect-qr-lifecycle` salt okunur komutu eklendi: token biçimi/tekrarı, etkin hedef erişimi, tarama sayacı ve son tarama zamanı tutarlılığı denetlenir.
- Rapor token değerlerini içermez; örnek kayıt kimlikleriyle sınırlıdır. Kayıt düzeltme veya bağlantı etkinleştirme yapmaz.
- Dört Laravel Feature test kaynağı ve `docs/QR_LIFECYCLE_INSPECTION_0_10_14.md` belgesi eklendi.
- Release gate komut kaydı, test kaynağı ve belgeyi doğrular.
- Gerçek Laravel/DB testleri Composer bağımlılıkları bulunmadığından bu ortamda çalıştırılmadı.

## 0.10.12 — Medya depolama bütünlük denetimi
- `orvionart:inspect-media-storage` salt okunur komutu eklendi; ORVIONART'ın yönetilen medya klasörlerini veritabanı referanslarıyla karşılaştırır.
- Sahipsiz görünen medya dosyaları raporlanır; hiçbir dosya otomatik silinmez.
- Komut için iki Laravel Feature test kaynağı ve kullanım/güvenlik belgesi eklendi.
- Komut release gate tarafından kontrol edilir.
- Gerçek Laravel/veritabanı testleri Composer bağımlılıkları bulunmadığından bu ortamda çalıştırılmadı.

## 0.10.9 — 2026-10-15
- Başarılı QR/NFC yönlendirmelerine açık no-store/no-cache, noindex, no-referrer ve nosniff yanıt başlıkları eklendi.
- Stabil QR token'ının deneyim slug'ı değiştiğinde güncel URL'ye yönlendirmeye devam ettiğini doğrulayan Feature test kaynağı eklendi.
- Gerçek Laravel testleri ve staging doğrulaması bu çalışma ortamında yapılmadı.


## 0.10.6 — Migration statik denetimi
- `scripts/audit-migrations.php` eklendi: migration dosya adlarını, tekrar eden zaman damgalarını, `up()`/`down()` metotlarını ve tanınabilir Laravel Migration sınıfı yapısını statik kontrol eder. `--json` makinece okunabilir rapor üretir.
- Migration içindeki olası yıkıcı ham SQL `REVIEW` olarak raporlanır; otomatik olarak güvenli kabul edilmez.
- `scripts/test-migration-audit.sh` geçerli ve `down()` metodu eksik fixture senaryolarını geçici dizinlerde doğrular.
- `scripts/release-gate.sh` migration statik denetimini ve regresyon testini çalıştırır.
- Bu kontroller veritabanına bağlanmaz; gerçek migration/rollback testi değildir.

## 0.10.5 — Sunucu ön kontrolü güvenlik sıkılaştırması
- `scripts/preflight.php` APP_KEY biçimi, APP_ENV, HTTPS APP_URL, APP_DEBUG, oturum çerezi güvenlik ayarları, ödeme varsayılanı ve `.env` dosya izinlerini denetleyecek şekilde genişletildi.
- DB kontrolünde boş/örnek kullanıcı ve veritabanı adlarıyla bağlantı denemesi engellenir; sırlar rapora yazılmaz.
- `scripts/test-preflight.sh` geçici test projesinde güvenli ve güvensiz yapılandırma senaryolarını doğrular; test dosyaları geçici dizinde tutulur.
- Ayrıntılar: `docs/DEPLOYMENT_PREFLIGHT_HARDENING_0_10_5.md`.
- Bu sürümde gerçek Plesk/staging ortamı, Composer/Laravel runtime, migration veya veritabanı geri yükleme testi yapılmadı.

## 0.10.4 — Staging HTTP smoke test
- `scripts/smoke-staging.sh` eklendi: HTTPS üzerinden yalnızca GET istekleriyle `/health`, `/admin/login`, `robots.txt`, yönetici güvenlik başlıkları ve misafir `/admin` yönlendirmesini kontrol eder.
- Betik TLS sertifikasını doğrular; `-k` kullanmaz, giriş yapmaz, form göndermez, veri değiştirmez ve migration/deploy çalıştırmaz. Plesk HTTP Basic koruması için izinleri kısıtlı `--netrc-file` seçeneği eklendi; kimlik bilgileri komut argümanına yazılmaz.
- Kontroller ve sınırlamalar `docs/STAGING_HTTP_SMOKE_0_10_4.md` içinde açıklandı. Gerçek staging URL'si olmadığı için gerçek HTTP smoke testi bu aşamada çalıştırılmadı.


## 0.10.3 — Sürüm kapısı ve kabul kontrolü
- `scripts/release-gate.sh` eklendi: PHP/shell sözdizimi, kaynak manifesti, gerçek `.env` dosyası, iç içe ZIP ve ödeme varsayılanı kontrollerini tek komutta yürütür.
- Composer varsa `composer validate`, `vendor/` varsa Laravel testlerini çalıştırır; eksik çalışma zamanı bağımlılıklarını açıkça uyarı/blok olarak bildirir.
- `--strict` modu Composer ve Laravel test ortamını zorunlu tutar. Betik deploy/migration yapmaz ve veritabanı değiştirmez.
- Gerçek staging kabul sırası ve test sınırları `docs/RELEASE_GATE_0_10_3.md` içinde belgelendi.
- Bu sürümde gerçek sunucu, Composer/Laravel runtime, DB migration, tarayıcı veya mobil testleri yapılmadı.

## 0.10.2 — Kaynak teslimatı bütünlüğü ve paket boyutu kontrolü
- `scripts/release-manifest.php create|verify` eklendi; dağıtılabilir kaynak dosyalarının göreli yol, boyut ve SHA-256 değerlerini kaydeder ve doğrular.
- Manifest doğrulaması eksik/değişmiş dosyaları ve manifestte olmayan yeni kaynak dosyalarını raporlar; uygulama/veritabanı üzerinde değişiklik yapmaz.
- Yeni teslimat paketlerinin içine tarihsel ZIP'lerin tekrar gömülmesi durduruldu. Önceki teslimat ZIP'leri ayrı dosyalar olarak kalır; güncel kaynak ağacı ve belgeler birikimli şekilde korunur.
- Ayrıntılar: `docs/RELEASE_MANIFEST_0_10_2.md`.
- Bu sürümde Composer bağımlılık kurulumu, Laravel runtime, migration, feature, gerçek sunucu veya mobil testleri yapılmadı.

# Changelog

## 0.10.25 — Site haritasında yayın sürümü tarihleri
- XML sitemap `lastmod` alanı, Site Builder taslaklarının daha sonra düzenlenmesinden etkilenmemesi için sayfanın son yayın zamanını kullanır.
- Deneyim sitemap kayıtları da genel `updated_at` yerine yayın zamanı; zamanlanmış yayınlarda zamanlanmış yayın tarihi kullanır.
- Üretim sitemap davranışı için iki Feature test kaynağı eklendi; Composer/Laravel çalışma zamanı mevcut olmadığından testler bu ortamda çalıştırılamadı.
- Ayrıntı: `docs/SITEMAP_PUBLICATION_LASTMOD_0_10_25.md`.

## 0.10.1 — Kontrollü staging yedekleme ve doğrulama araçları
- Operatörün açıkça çalıştırdığı `scripts/backup-staging.sh` eklendi: MySQL/MariaDB SQL dökümü ve `storage/app/private`, `storage/app/public` dosyalarını uygulama kökü dışında, kısıtlı izinlerle yedekler.
- `scripts/verify-backup.sh` SHA-256 toplamlarını ve medya arşivinin okunabilirliğini kontrol eder; veritabanına bağlanmaz ve geri yükleme yapmaz.
- `docs/BACKUP_RESTORE_0_10_1.md` staging'e geri yükleme tatbikatı ve canlı veriyi koruma adımlarını belgeliyor.
- Yedekleme otomatik tetiklenmez; gerçek sunucuda çalıştırılmadı. Geri yükleme tatbikatı ve Composer/Laravel runtime testleri yapılmadı.

## 0.10.0 — 2026-10-15
- `orvionart:release-check` salt okunur kurulum/staging hazırlık komutu eklendi. PHP eklentileri, gerekli paket dosyaları, uygulama ayarları, yazma izinleri, veritabanı bağlantısı ve migration tutarlılığını denetler.
- JSON raporu, PASS / REVIEW_REQUIRED / BLOCKED sınıflandırması ve güvenli hata mesajları eklendi.
- Gerçek web sunucusu parola koruması, document root, yedekten geri dönüş ve tarayıcı kabul testi gibi komut satırından kesin doğrulanamayan maddeler manuel kontrol olarak raporlanır.
- `ORVIONART_PAYMENTS_ENABLED` yapılandırması varsayılan olarak `false` eklendi; ödeme entegrasyonu uygulanmadı ve etkinleştirilmedi.
- Test kaynakları eklendi; Composer bağımlılıkları bu ortamda bulunmadığı için çalıştırılmadı.

## 0.9.9 — Bütünleşik platform veri bütünlüğü denetimi
- `php artisan orvionart:inspect-platform` komutu Deneyim, QR/NFC, medya, Site Builder ve kullanım hakkı kayıtlarını salt okunur biçimde özetler.
- Etkin QR/NFC bağlantılarının erişilemeyen hedefleri, dosyası bulunmayan medya, birden fazla modüle bağlanmış medya kayıtları, yayındaki sayfalardaki geçersiz medya referansları ve hedefi yayımlanmamış Site Builder yönlendirmeleri raporlanır.
- `--json` çıktısı otomatik izleme/inceleme için uygundur; cihaz token'ları, gizli yapılandırma veya dosya yolları rapora eklenmez.
- Kullanım hakkı kayıtları sayılır; komut bunları erişim yetkisine dönüştürmez.
- Dört Feature test senaryosu eklendi. Composer/Laravel çalışma zamanı bu ortamda bulunmadığından testler çalıştırılmadı.

## 0.9.8 — Demo Engine salt okunur sağlık denetimi
- `php artisan orvionart:inspect-demo` komutu Demo Engine ayarlarını, içerik/oturum/olay sayılarını ve medya dosyalarının bulunabilirliğini salt okunur biçimde raporlar.
- `--json` seçeneği makine tarafından okunabilir rapor verir; raporda ham cihaz token'ı, gizli yapılandırma veya medya dosya yolu yer almaz.
- Eksik ayar, etkin içeriksiz aktif engine, erişilemeyen medya veya pasif içeriklere bağlı atamalar uyarı üretir ve komut sıfır dışı kodla biter.
- Demo yönetim ekranındaki saklama süresi açıklaması gerçek temizleme komutuyla hizalandı.
- Denetim için test kaynakları eklendi; Composer olmadığı için Laravel testleri çalıştırılmadı.

## 0.9.7 — Demo medya erişimi ve yolculuk yaşam döngüsü
- Demo medyası için oturum belirteci, sona erme durumu ve bugünün içerik ataması doğrulanır; yalnızca içerik ID'sini bilmek erişim için yeterli değildir.
- Demo medya yanıtları no-store, noindex, no-referrer ve nosniff başlıklarıyla sunulur; medya rotasına `throttle:60,1` eklendi.
- Demo yolculuğunu yeniden başlatma önceki oturumu sona erdirir, `journey_restarted` olayı kaydeder ve yeni oturum/başlangıç olayını transaction içinde oluşturur.
- İçerik bulunmayan Demo Engine'de boş yolculuk oluşturulması engellendi.
- Test kaynakları eklendi; Composer olmadığı için çalıştırılmadı.
- Ayrıntı: `docs/DEMO_MEDIA_ACCESS_0_9_7.md`.


## 0.9.6 — Medya yaşam döngüsü ve güvenli ayırma
- Deneyime bağlı medya, dosyası silinmeden merkezi kütüphaneye taşınabilir.
- Bağlantıyı kaldırma işlemi transaction ve satır kilidiyle yürütülür; `media.detached_from_experience` audit olayı oluşturulur.
- Ayrılan medya deneyim ilişkisi kalmadığından herkese açık medya rotasından sunulmaz.
- Site Builder taslak/yayın snapshot veya sosyal paylaşım görselinde kullanılan dosyalar silinemez.
- Kalıcı silme öncesi kullanım referansları yeniden kontrol edilir; dosya verisi yalnızca veritabanı işlemi tamamlandıktan sonra silinir.
- Medya yaşam döngüsü ve test sınırları `docs/MEDIA_LIFECYCLE_0_9_6.md` içinde belgelenmiştir.

## 0.9.5 — QR/NFC ve deneyim yaşam döngüsü
- Kamuya açık deneyim sorgusu `publiclyAvailable()` scope ile merkezileştirildi; oynatıcı sorgusu modeldeki erişilebilirlik kuralıyla hizalandı.
- QR/NFC yönetim ekranı bağlantı durumu ile hedef deneyim durumunu ayrı gösteriyor; gelecek yayın zamanı görünür.
- Erişilemeyen hedef QR isteklerinde tarama sayacı artmıyor; bakım modu QR yönlendirmelerini de 503 ile durduruyor.
- Kanonik slug yönlendirmesine no-store ve no-referrer başlıkları eklendi.
- Ayrıntı: `docs/QR_EXPERIENCE_LIFECYCLE_0_9_5.md`.

## 0.9.4 — Demo Engine veri saklama temizliği
- `orvionart:prune-demo-data` komutu eklendi.
- `retention_days`, eski demo olayları ve süresi dolmuş oturumlar için uygulanır.
- `--dry-run` silme öncesi sayım sunar.
- Laravel Scheduler her gün 03:20 için tanımlandı; sunucu cron ayarı ayrıca gereklidir.
- Komut için dry-run, eski verinin temizlenmesi ve yeni sona ermiş oturumun korunması test kaynakları eklendi.
- Ayrıntı: `docs/DEMO_RETENTION_0_9_4.md`.

## 0.9.3
- Yönetici rotalarında hareketsizlik zaman aşımı ve oturum geçersizleştirme.

## 0.9.2
- Süper Admin için salt okunur denetim kayıtları görüntüleyicisi.

## 0.9.1
- Kullanım hakları CSV dışa aktarımı, formül enjeksiyonu nötralizasyonu ve transaction içinde iptal.

## 0.9.0
- Kullanım hakları kayıt defteri; gerçek erişim denetimine bağlı değildir.

## 0.8.x
- Site Builder, SEO, merkezi medya, bakım modu ve kurulum ön kontrolü geliştirmeleri.

## 0.10.7 — 2026-10-15
- Site Builder için `orvionart:inspect-site-builder` salt okunur bütünlük denetimi eklendi.
- Eksik yayın snapshot'ları, yinelenen yayın slug'ları, geçersiz etkin menü hedefleri, HTTPS dışı haricî hedefler, üst menü kuralı uyumsuzlukları ve gelecekteki yayın tarihleri raporlanır.
- JSON raporu, çıkış kodu ve regresyon test kaynakları eklendi; komut kayıtları otomatik düzeltmez.
- Laravel feature testleri Composer/vendor kurulumu bulunmayan bu ortamda çalıştırılmadı.


## 0.10.8 — 2026-10-15
- Site Builder yayınlama öncesinde büyük/küçük harf farkını gözetmeden başka bir sayfanın mevcut `published_slug` değeriyle çakışma kontrolü eklendi. Çakışma, belirsiz SQL hatası yerine açıklayıcı 422 yanıtıyla engellenir; DB benzersiz indeksi korunur.
- Yönetici Site Builder önizlemesine açık `X-Robots-Tag: noindex, nofollow, noarchive`, `Cache-Control: private, no-store` ve `Referrer-Policy: no-referrer` başlıkları eklendi.
- Yayın slug çakışması ve önizleme başlıkları için iki Laravel Feature test kaynağı eklendi. Composer/runtime olmadığından çalıştırılmadı.
- Ayrıntı: `docs/SITE_BUILDER_PUBLISH_GUARD_0_10_8.md`.

## 0.10.11 — Site Builder sürüm geri alma güvenliği
- Site Builder geçmiş sürüm geri alma işleminde kayıtlı snapshot verisi güvenilir kabul edilmez; başlık, slug biçimi, sayfa türü, ana sayfa/adres uyumu, ayrılmış sistem adresleri ve bölüm verisi doğrulanır.
- Geri alınacak eski slug başka bir sayfanın güncel slug alanında kullanılıyorsa işlem açıklayıcı doğrulama hatasıyla durdurulur; mevcut taslak değiştirilmez.
- Geri alma işlemi taslağı günceller; yayındaki snapshot, yayın slug'ı ve canlı sayfa içeriği açık bir yayınlama işlemi yapılana kadar korunur.
- İki Laravel Feature test senaryosu eklendi: slug çakışmasında güvenli ret ve başarılı geri almada yayının korunması. Composer/runtime olmadığı için bu testler çalıştırılmadı.
- Ayrıntı: `docs/SITE_BUILDER_REVISION_RESTORE_0_10_11.md`.

## 0.10.10 — 2026-10-15
- README tamamlandı; gereksinimler, statik kontrol, salt okunur denetim komutları ve doğrulanmamış test sınırları açıklandı.
- Release gate; `orvionart:inspect-platform`, `orvionart:inspect-demo` komutları ve bunların Feature test kaynaklarının teslimatta bulunmasını kontrol edecek şekilde genişletildi.
- Boş/eksik README ve sürüm teslimat belgesi artık release gate kontrolünü durdurur.
- Ayrıntı: `docs/RELEASE_HANDOFF_0_10_10.md`.
- Bu aşamada Composer/Laravel çalışma zamanı, gerçek DB ve sunucu kabul testleri yapılmadı.


## 0.10.13 — Demo Engine yaşam döngüsü bütünlük denetimi
- `orvionart:inspect-demo-lifecycle` salt okunur komutu eklendi. Oturum başlangıç/bitiş, son görülme/sona erme zamanlarını, oturum dışındaki günlük atamaları ve oturum başlamadan önce yazılmış olayları denetler.
- JSON raporu, uyarı başına kayıt sayısı ve en fazla 20 örnek ID verir; cihaz token bilgileri raporlanmaz.
- Denetim otomatik düzeltme/silme yapmaz ve kullanım hakkı/ödeme kurallarını değiştirmez.
- Üç Laravel Feature test senaryosu eklendi; Composer ve Laravel runtime bağımlılıkları yoksa çalıştırılamaz.
- Ayrıntı: `docs/DEMO_LIFECYCLE_INSPECTION_0_10_13.md`.


## 0.10.30 — Deneyim ve QR/NFC yaşam döngüsü tutarlılığı
- Deneyim taslak/arşiv durumuna geçirildiğinde veya soft-delete yapıldığında etkin QR/NFC bağlantıları aynı transaction içinde devre dışı bırakılır. Kalıcı tokenlar ve tarama geçmişi korunur.
- Otomatik QR devre dışı bırakmaları `qr_link.auto_deactivated` eylemiyle denetim kaydına yazılır.
- Taslak veya arşivlenmiş hedefler için QR yeniden etkinleştirme engellenir; QR oluşturma hedef durumunu transaction ve kilit altında yeniden doğrular.
- QR oluşturma, durum değiştirme ve ziyaretçi yönlendirme akışlarında kilit sırası `experience -> qr_link` olarak birleştirildi.
- Deneyim güncelleme/soft-delete ve QR yeniden etkinleştirme için Laravel Feature test kaynakları eklendi. Composer/runtime olmadığı için bu testler çalıştırılmadı.
- Ayrıntı: `docs/EXPERIENCE_QR_LIFECYCLE_0_10_30.md`.
