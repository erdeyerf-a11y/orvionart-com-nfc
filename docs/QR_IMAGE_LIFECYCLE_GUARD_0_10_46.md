# QR/NFC görsel yaşam döngüsü koruması — 0.10.46

## Risk
Yönetici QR SVG uç noktası, route-model binding sırasında yüklenen `QrLink` nesnesindeki `is_active` değerini doğrudan kullanıyordu. Eşzamanlı bir istek bağlantıyı devre dışı bıraktığında, eski model örneğiyle kısa bir yarış penceresinde görsel üretimi devam edebilirdi. Benzer şekilde, tutarsız bir eski veri kaydı taslak deneyime bağlı olduğu hâlde SVG üretilebilirdi.

## Değişiklik
- Görsel üretiminden önce QR bağlantısı ve bağlı deneyim güncel veritabanı durumundan yeniden okunur.
- QR yaşam döngüsü ve halka açık yönlendirmeyle aynı `experience -> QR` kilit sırası uygulanır.
- Etkin bağlantı ve hedefin kullanılabilirliği doğrulanır.
- Gelecekteki bir zamana zamanlanmış deneyim için QR SVG önceden üretilebilir; gerçek ziyaretçi yönlendirmesi yayın zamanı gelene kadar kapalı kalır.
- SVG indirme yanıtında `nosniff` ve `private, no-store` başlıkları korunur.

## Test kapsamı
`QrAndMediaTest` içinde devre dışı bağlantının ve taslak hedefin reddedilmesi ile gelecekte zamanlanmış hedefin QR görselinin üretilebilmesi için Feature test kaynakları bulunur. Bu ortamda Composer ve `vendor/autoload.php` yoksa bu testler çalıştırılmış sayılmaz.

## Doğrulama sınırı
Statik PHP/Shell sözdizimi, manifest, Release Gate ve ZIP bütünlüğü kontrolü gerçek Laravel çalışma zamanı veya sunucu kabul testinin yerine geçmez. Gerçek veritabanı eşzamanlılık ve Plesk/staging testleri ayrıca gereklidir.
