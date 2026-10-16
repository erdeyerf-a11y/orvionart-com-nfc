# V1.5 gereksinim matrisi — 0.10.59

## Bu sürümde tamamlanan kaynak kodu işi
- QR/NFC yönetiminde SVG QR önizleme sayfası.
- Yazdırma için sade görünüm.
- QR yönetim listesinde kalıcı NFC adresi kopyalama.
- Deneyim düzenleme ekranından QR önizlemeye geçiş.
- Önizleme ve indirme uçlarında aynı yaşam döngüsü kontrollerinin korunması.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama ve medya yükleme akışı.
- Kalıcı `/go/{token}` yönlendirmesi ve okutma sayacı.
- QR SVG üretimi ve NFC için aynı URL'nin kopyalanması.
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
