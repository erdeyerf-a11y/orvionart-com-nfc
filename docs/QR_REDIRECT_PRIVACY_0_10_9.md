# QR/NFC yönlendirme gizliliği — 0.10.9

## Değişiklik

Başarılı `/go/{token}` yönlendirmeleri artık açıkça `private, no-store, no-cache, must-revalidate` ve `Pragma: no-cache` başlıkları gönderir. Yönlendirme yanıtına ayrıca `X-Robots-Tag: noindex, nofollow, noarchive`, `Referrer-Policy: no-referrer` ve `X-Content-Type-Options: nosniff` eklenmiştir.

Bu ayarlar QR/NFC kısa yönlendirme uç noktasının tarayıcı/proxy önbelleğine alınmasını ve arama motorları tarafından indekslenmesini azaltmayı amaçlar. Bunlar erişim denetiminin yerine geçmez. Hedef deneyim kamuya açık değilse mevcut davranış korunur: 404 yanıtı verilir ve tarama sayacı artırılmaz.

## Stabil hedef

QR/NFC token'ı deneyim slug'ından bağımsızdır. Deneyimin okunabilir slug'ı değiştiğinde aynı token güncel kamu URL'sine yönlendirmeye devam eder.

## Eklenen Feature test senaryoları

- Başarılı yönlendirme gizlilik ve noindex başlıklarını döndürür.
- Deneyim slug'ı değişse de token aynı kalır ve yeni URL'ye yönlendirir.
- Devre dışı bağlantı veya yayında olmayan hedef 404 döndürür ve sayacı artırmaz (önceden var olan testler).

## Doğrulama sınırı

PHP sözdizimi ve kaynak kontrolleri paket hazırlığında çalıştırılır. Laravel Feature testleri Composer/vendor ve test veritabanı olmadan çalıştırılamaz; staging sunucusunda ayrıca yürütülmelidir.
