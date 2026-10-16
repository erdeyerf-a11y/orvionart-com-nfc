# ORVIONART 0.10.24 — Yönetici medya önizleme güvenliği

## Amaç

Yönetici medya önizleme uç noktası yalnızca ORVIONART tarafından yönetilen medya dosyalarını sunmalıdır. Veritabanındaki `disk` ve `path` alanları, dosya sistemi üzerinde genel amaçlı okuma yetkisi olarak kabul edilmez.

## Koruma

- Yalnızca `local` özel disk kabul edilir.
- Yalnızca `orvionart-media/` ve `orvionart-site-media/` önekleri kabul edilir.
- Mutlak yollar, Windows sürücü yolları, ters eğik çizgi, boş yol bileşenleri, `.` ve `..` reddedilir.
- `realpath` ile çözümlenen dosyanın özel disk kökü altında kaldığı ve normal dosya olduğu doğrulanır; kök dışına çıkan sembolik bağlantılar reddedilir.
- Yanıt `nosniff`, `private, no-store` ve kısıtlı içerik güvenlik başlıklarını korur.

## Kontroller

`tests/Feature/MediaPreviewSafetyTest.php` güvenli yerel medya sunumunu, farklı disklerin reddedilmesini ve dizin geçişi yolunun reddedilmesini kapsar. Bu Feature testleri Composer/vendor çalışma zamanı bu kaynak ortamında mevcut değilse çalıştırılamaz; yalnızca PHP sözdizimi kontrolü, testlerin çalıştırıldığı anlamına gelmez.

## İşletim sınırı

Bu değişiklik canlı sunucuya uygulanmadı. Staging ortamında Laravel Feature testleri, yerel disk yapılandırması ve mevcut medya kayıtlarıyla kabul testi yapılmalıdır.
