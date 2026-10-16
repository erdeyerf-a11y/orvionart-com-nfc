# Site Builder iletişim bağlantıları — 0.10.65

## Değişiklik
- `contact` bölümü, genel site ayarlarında iletişim e-postası veya telefonu varsa bunları açık etiketli tıklanabilir bağlantılar olarak sunar.
- E-posta `mailto:` ile açılır.
- Telefonun `tel:` hedefi yalnızca rakamlar ve `+` işareti içerir. Ekrandaki telefon metni ise ayarlarda girildiği biçimde kalır.
- Footer, mevcut iletişim telefonu ayarını da görünür kılar; e-posta/telefon alanları boşsa boş bağlantı üretilmez.
- CSS bağlantıları mobil genişlikte sarar, görünür odak ve en az 44 px yüksekliğe sahip dokunma alanı sağlar.
- Veritabanı şeması değişmez.

## Kontrol sınırları
`tests/Feature/SiteBuilderTest.php` içinde regresyon testi kaynağı eklendi. Laravel/Composer çalışma zamanı bu kaynak ortamında bulunmadığından test çalıştırılmış sayılmaz. Release Gate ve PHP sözdizimi statik kontrolleri çalıştırılmalıdır. Gerçek tarayıcı ve mobil cihaz testi ayrıca gereklidir.
