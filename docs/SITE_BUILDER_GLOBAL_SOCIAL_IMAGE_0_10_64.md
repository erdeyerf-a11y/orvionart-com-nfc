# Site Builder varsayılan sosyal paylaşım görseli — 0.10.64

## Amaç
Sayfa başına sosyal paylaşım görseli seçilmemişse genel Site Builder ayarlarında tanımlanan marka görseli kullanılabilir. Sayfaya özel seçilmiş/yayınlanmış görsel daima önceliklidir.

## Yönetim ve güvenlik
- `social_image_file` yalnızca PNG, JPEG veya WebP; maksimum 5 MB.
- SVG ve diğer MIME türleri kabul edilmez.
- Dosya `local` özel depolamasında rastgele üretilen adla tutulur.
- Kamuya açık sunum yalnızca `brand.asset` adı verilen `/brand/social-image` rotası üzerinden, beklenen dosya adı biçimi ve dosya varlığı kontrol edilerek yapılır.
- Değiştirme/kaldırma, genel ayar satırı ve audit kaydı transaction'ı ile uyumlu çalışır; eski dosya ancak yeni ayar commit edildikten sonra ve başka yerde referanslı değilse silinir.
- Preview taslağına genel görseli otomatik eklemez; fallback yalnızca yayınlanmış sayfalarda uygulanır.

## SEO etiketleri
Varsayılan veya sayfaya özel görsel bulunduğunda `og:image`, `og:image:alt`, `twitter:image` ve `twitter:card=summary_large_image` üretilir. Görsel yoksa Twitter `summary` kullanılır.

## Test durumu
`tests/Feature/SiteBuilderTest.php` içine yükleme/sunum ve SVG reddi test kaynakları eklendi. Bu geliştirme ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmadı. Plesk ve gerçek sosyal platform paylaşım önizlemeleri ayrıca doğrulanmalıdır.
