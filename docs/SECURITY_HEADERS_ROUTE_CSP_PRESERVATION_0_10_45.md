# Güvenlik başlıklarında route-specific CSP koruması — 0.10.45

## Sorun
`SecurityHeaders` middleware'i her yanıtın `Content-Security-Policy` başlığını koşulsuz olarak genel politika ile değiştiriyordu. Yönetici medya önizlemesi ise `default-src 'none'; sandbox` kullanarak daha sıkı bir politika belirliyordu. Middleware sırası nedeniyle özel politika yanıt kullanıcıya ulaşmadan ezilebiliyordu.

## Düzeltme
Global middleware yalnızca yanıt henüz CSP tanımlamamışsa varsayılan CSP ekler. Route/controller tarafından belirlenmiş özel CSP aynen korunur. Diğer genel güvenlik başlıkları ve yönetici önbellek politikası değişmedi.

## Regresyon kapsamı
- Medya önizlemesi `default-src 'none'; sandbox` başlığını korumalı.
- CSP belirlemeyen genel yanıt, varsayılan CSP almalı.

Bu test beklentileri `tests/Feature/MediaPreviewSafetyTest.php` içinde kaynak olarak bulunur. Composer ve `vendor/autoload.php` mevcut değilse Laravel Feature testleri çalıştırılmış sayılmaz. Statik kontrol gerçek tarayıcı veya Plesk kabul testi yerine geçmez.
