# V1.5 gereksinim matrisi — 0.10.70

## Bu sürümde yapılan geliştirme
- Staging yedek manifesti yeni format 2 alanlarını içerir; `.env` ve `vendor` yedek dışında tutulduğunu açıkça bildirir.
- Yedek doğrulayıcı, mevcut eski manifestleri geriye dönük desteklerken manifest alanlarının eksik/tekrar/bilinmeyen olmasını reddeder.
- Manifestte sürüm, UTC zaman damgası, veritabanı motoru, metadata uzunluğu ve restore politikası doğrulanır; veritabanı adı/host bilgisi çıktıya yazılmaz.
- Format 2 manifestinin `contains_vendor_directory=no` beyanı zorunludur. SQL ve medya arşivi yine çalıştırılmaz veya çıkarılmaz.
- Geçerli/eski format, geçerli yeni format ve bozuk metadata durumları için yalıtılmış shell regresyon testleri eklendi. Yeni migration yoktur.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya ve QR/NFC akışı.
- QR SVG önizleme/ürün etiketi yazdırma ve NFC için aynı kalıcı adresi kopyalama.
- Site Builder sayfa bazlı sosyal görseli ve genel varsayılan sosyal görsel fallback'i.
- Site Builder, Demo Engine, kullanım hakları ve video işleme entegrasyonları.
- Yeni preflight kontrolleri yalnızca yapılandırma göstergeleridir; gerçek kuyruk worker'ı ve FFmpeg/FFprobe işlemi uçtan uca doğrulanmış değildir.
- Yedek doğrulama manifest şemasını ve arşiv yol güvenliğini kontrol eder; gerçek DB geri yükleme tatbikatı yapmaz.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Gerçek yazıcı veya hedef etiket boyutunda QR baskı testi.
6. Yedek dosyasının gerçek staging DB'ye geri yüklenmesi ve uygulama kabul testleri.
7. Güvenli Süper Admin sürüm güncelleme modülü: ayrıca tamamlanmalı ve test edilmeli.
8. Site Builder'ın V1.5 bileşen kapsamı ve masaüstü/mobil kabulü.
9. Büyük video dosyasının gerçek PHP sınırları, queue worker ve FFmpeg/FFprobe ile staging'de uçtan uca işlenmesi.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Gerçek sunucu, yazıcı ve telefon testleri yapılmadan uygulama “çalışır üretim sistemi” olarak ilan edilmemelidir.
