# V1.5 gereksinim matrisi — 0.10.60

## Bu sürümde tamamlanan kaynak kodu işi
- Deneyim düzenleme ekranına isteğe bağlı erişim bitiş zamanı eklendi.
- Bitiş zamanı, deneyim sorgusu ve modelin kamu erişim kuralında sunucu tarafında uygulanır.
- Süresi dolmuş deneyimin doğrudan adresi ve QR/NFC yönlendirmesi kapatılır.
- Süresi dolmuş QR okutması sayaç artırmaz.
- Zamanlanmış deneyimde bitiş zamanı yayın başlangıcından sonra doğrulanır.
- `ends_at` boş bırakılırsa mevcut sınırsız erişim davranışı korunur; alanı göndermeyen eski form istemcileri mevcut değeri korur.
- Migration eski deneyimlerin tarihlerini değiştirmeden nullable kolon ekler.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya ve QR/NFC akışı.
- QR SVG önizleme/yazdırma ve NFC için aynı kalıcı adresi kopyalama.
- Süre sonu kuralının gerçek MariaDB ve telefonla QR taramasında uçtan uca doğrulanması.
- Site Builder, Demo Engine, kullanım hakları ve video işleme.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Yedekleme ve geri alma denemesi.
6. Güvenli Süper Admin sürüm güncelleme modülü: ayrıca tamamlanmalı ve test edilmeli.
7. Site Builder'ın V1.5 bileşen kapsamı ve masaüstü/mobil kabulü.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Gerçek sunucu ve telefon testleri yapılmadan uygulama “çalışır üretim sistemi” olarak ilan edilmemelidir.
