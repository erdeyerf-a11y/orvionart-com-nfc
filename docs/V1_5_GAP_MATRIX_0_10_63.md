# V1.5 gereksinim matrisi — 0.10.63

## Bu sürümde yapılan geliştirme
- Süper Admin QR/NFC kayıt listesine etiket/deneyim adına göre arama ve etkin/devre dışı durum filtresi eklendi; filtreler sayfalama bağlantılarında korunur.
- Devre dışı, yayında olmayan veya süresi dolmuş QR/NFC hedefleri için ziyaretçiye mobil uyumlu açıklama ekranı eklendi; başarısız erişim tarama sayacını artırmaz. Süresi dolmuş içerik HTTP 410, diğer bilinen kullanılamayan hedefler 404 döndürür.

- QR önizleme sayfasına yazdırılabilir ürün etiketi düzeni eklendi: ORVIONART marka adı, bağlı deneyim başlığı, ziyaretçiye kısa yönerge ve QR kodu.
- Yazdırma görünümünde yönetim kontrolleri ve NFC adres kopyalama alanı gizlenir.
- QR'nin kalıcı `/go/{token}` hedefi değiştirilmez; NFC için aynı adres kullanılmaya devam eder.
- QR önizleme Feature test kaynağı markalı başlık ve ziyaretçi yönergesini de kontrol eder.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya ve QR/NFC akışı.
- QR SVG önizleme/ürün etiketi yazdırma ve NFC için aynı kalıcı adresi kopyalama.
- Süre sonu kuralının gerçek MariaDB ve telefonla QR taramasında uçtan uca doğrulanması.
- Site Builder, Demo Engine, kullanım hakları ve video işleme.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Gerçek yazıcı veya hedef etiket boyutunda QR baskı testi.
6. Yedekleme ve geri alma denemesi.
7. Güvenli Süper Admin sürüm güncelleme modülü: ayrıca tamamlanmalı ve test edilmeli.
8. Site Builder'ın V1.5 bileşen kapsamı ve masaüstü/mobil kabulü.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Gerçek sunucu, yazıcı ve telefon testleri yapılmadan uygulama “çalışır üretim sistemi” olarak ilan edilmemelidir.
