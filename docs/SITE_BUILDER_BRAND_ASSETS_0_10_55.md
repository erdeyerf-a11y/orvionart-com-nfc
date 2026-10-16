# Site Builder logo/favicon yönetimi — 0.10.55

## Kapsam
- Genel Site Builder ayarları ekranından logo ve favicon yükleme, değiştirme ve kaldırma.
- Kabul edilen biçimler: PNG, JPEG, WebP. Logo en fazla 4 MiB; favicon en fazla 2 MiB. SVG kasıtlı olarak kabul edilmez.
- Dosyalar `local` disk üzerindeki `orvionart-brand/` özel klasöründe rastgele oluşturulmuş dosya adlarıyla saklanır. Dosya yolu ayar değerinden okunur; kamu uç noktası yalnızca `logo` veya `favicon` türünü ve sabit dosya adı desenini kabul eder.
- Kamu uçları `/brand/logo` ve `/brand/favicon`; yanıtlar `X-Content-Type-Options: nosniff` ve `Cache-Control: no-store, private` başlıkları taşır.
- Logo, Site Builder sayfalarının üst alanında görünür. Logo ayarlanmamışsa marka metni gösterilir. Favicon yalnızca tanımlıysa sayfa başlığına eklenir.

## Tutarlılık ve güvenlik
- Ayar değerleri ve `site_settings.updated` denetim kaydı tek DB transaction'ında kaydedilir.
- Dosya yükleme DB transaction'ından önce yapılır. DB veya denetim kaydı başarısız olursa yeni yüklenen dosyalar temizlenir; eski dosya ve ayar referansları korunur.
- Eski dosyalar yalnızca yeni ayarlar commit edildikten sonra ve hâlâ başka logo/favicon alanı tarafından referans edilmiyorsa silinir.
- Önceki sürümün formları yeni logo/favicon alanlarını göndermediğinde mevcut referanslar korunur.
- Dosya uzantısı kullanıcı dosya adından alınmaz; doğrulanan MIME türü üzerinden belirlenir. SVG/HTML içeriklerinin doğrudan çalıştırılması engellenir.

## Doğrulama sınırı
Feature test kaynakları logo/fav upload, kamuya sunum, kaldırma ve SVG reddini kapsar. Bu ortamda Composer ve Laravel runtime bulunmadığı için testler çalıştırılmadı. Plesk üzerinde gerçek dosya izinleri, veritabanı transaction davranışı, CDN/tarayıcı önbelleği ve mobil görünüm henüz kabul edilmedi. Canlıya aktarım veya Manus teslimi yapılmadı.
