# ORVIONART 0.8.0 — Site Builder başlangıç teslimatı

## Kapsam
- Tek Süper Admin içinde sayfa listesi, oluşturma/düzenleme, taslak/yayın, yayından kaldırma.
- Güvenli yapılandırılabilir bölüm türleri: hero, metin, görsel+metin, kartlar, özellikler, adımlar, SSS, CTA ve iletişim.
- Sayfa başına özel görsel yükleme ve yayınlanmış sayfaya bağlı medya sunumu.
- Genel site ayarları: marka metni, renkler, güvenli yazı tipi seçenekleri, içerik genişliği, SEO varsayılanları, iletişim ve bakım modu.
- Header/footer navigasyonu; sayfaya veya HTTPS harici URL'ye bağlantı.
- Sayfa sürüm kayıtları ve taslağa geri alma. Geri alma kendiliğinden yayına almaz.
- Yayınlanan ana sayfa `home` slug'ı ile `/` adresinde; diğer sayfalar `/s/{slug}` adresinde.
- Taslak önizlemesi sadece auth+superadmin korumalı yönetim rotasından açılır ve noindex işaretlidir.
- Site Builder işlemleri ödeme veya Demo Engine'i etkinleştirmez.
- Medya silme işleminde, Site Builder taslak/yayın bölümlerinde referanslı görsel için engelleme kontrolü eklendi.

## Kullanım
1. Önceki sürüm gibi Composer bağımlılıklarını gerçek hazırlık ortamında kurun ve `.env` ayarlarını yapın.
2. Yedekleme ve staging korumalarını doğruladıktan sonra migration'ları çalıştırın.
3. `/admin/site-builder` adresinden `home` slug'ıyla sayfa oluşturun.
4. Bölümler ekleyin ve taslağı kaydedin. Sayfa kaydedildikten sonra görselleri yükleyip bölümlerden seçin.
5. Önizleme ile kontrol edin; sonra yayınlayın. Arama motoru indekslemesi sayfanın taslak SEO ayarındaki `noindex` tercihiyle belirlenir ve varsayılan olarak kapalıdır.
6. Menü bağlantılarını ayrı menü ekranından ekleyin. Taslak sayfalar menüye eklenemez.

## Bilinen sınırlar
- Bu geliştirme ortamında Composer/Laravel çalışma zamanı ve veritabanı mevcut olmadığı için uygulama/DB/E2E testi yapılmadı.
- Hero slider, gerçek sürükle-bırak, özel medya seçici penceresi, sosyal paylaşım görseli, sitemap, slug değişimi için 301 alias tablosu, gelişmiş menü sıralama kontrolleri ve deneyim referanslı pazarlama bölümleri sonraki geliştirme kapsamındadır.
- Tema ayarları güvenli seçeneklerle sınırlıdır; kullanıcıdan keyfî CSS/JS kabul edilmez.
- Medya silme sırasında sayfa referansları için genel kütüphane silme koruması sonraki aşamada sıkılaştırılmalıdır; siteye bağlı medyalar medya genel ekranında görünse de bu sürümde kaldırma kontrolü tam CMS seviyesinde değildir.
- Bakım modu uygulaması public Site Builder sayfalarını kapsar; özel QR/deneyim/demo rotaları için merkezi bakım davranışı sonraki entegrasyon aşamasında ele alınmalıdır.
