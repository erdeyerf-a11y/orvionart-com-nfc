# Site Builder bölüm öğesi ve bağlantı doğrulaması — 0.10.68

## Amaç

Yeni sayfa kaydı ve güncelleme sırasında üretilen bölüm öğelerinin, revizyon geri yükleme sırasında uygulanan veri sınırlarıyla uyumlu olmasını ve ziyaretçiye gösterilecek bağlantıların aynı güvenlik kuralından geçmesini sağlamak.

## Kurallar

- Her bölümde en fazla 100 dolu öğe satırı bulunabilir.
- Öğenin başlığı en fazla 180 karakter; açıklaması en fazla 12.000 karakterdir.
- Hero Slider düğme metni en fazla 80, düğme adresi en fazla 500 karakterdir.
- Bölüm CTA ve slider adresleri ortak `isSafeSnapshotLink` kontrolünü kullanır.
- Geçerli HTTPS URL'leri, tek `/` ile başlayan site içi yollar ve `#` bağlantıları kabul edilir.
- `javascript:`, `data:`, `vbscript:`, bozuk HTTPS URL'leri, kullanıcı adı/parola içeren HTTPS URL'leri, kontrol karakterleri ve ters eğik çizgi reddedilir.
- Eski yayınlanmış sayfa anlık görüntüsü bu değişiklik nedeniyle değiştirilmez; yeni taslak kaydı doğrulama kurallarını uygular.
- Yeni migration yoktur.

## Test durumu

`SiteBuilderTest` içinde 100 öğe sınırı, uzun başlık/tehlikeli slider URL'si ve bozuk HTTPS CTA URL'si için regresyon test kaynakları eklendi. Bu geliştirme ortamında Composer/Laravel runtime mevcut olmadığından testler çalıştırılmış kabul edilmez. Plesk, gerçek MariaDB ve tarayıcı kabulü beklemektedir.
