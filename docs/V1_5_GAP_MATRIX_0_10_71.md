# V1.5 gereksinim matrisi — 0.10.71

## Bu sürümde yapılan geliştirme
- Yedek doğrulamasından sonra boş, ayrı bir staging veritabanına salt-okunur erişim kontrolü yapan `scripts/restore-staging-preflight.sh` eklendi.
- Hedef DB adı `orvionart_staging_` öneki ve birebir ikinci onay gerektirir; yedekteki kaynak DB adıyla çakışma reddedilir.
- Staging dizininin uygulama/backup dizinleri dışında olması, mutlak yol olması ve mevcutsa boş dizin olması doğrulanır.
- DB kimliği ve tablo sayısı kontrol edilir; hedef DB boş değilse işlem durur. Parola geçici, 600 izinli istemci dosyasına yazılır ve çıktı/komut satırına alınmaz.
- 8 izolasyon/regresyon senaryosu sahte mysql istemcisiyle çalıştırıldı. Betik SQL çalıştırmaz, arşiv çıkarmaz ve gerçek veriyi değiştirmez. Yeni migration yoktur.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya ve QR/NFC akışı.
- Site Builder sayfaları, SEO, menü, tema ve içerik bileşenleri.
- Demo Engine, ücretsiz kullanım hakları ve video işleme entegrasyonları.
- Yedek oluşturma/doğrulama ve yeni geri yükleme ön kontrolü; **gerçek DB/media restore henüz yapılmadı**.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Gerçek yazıcı veya hedef etiket boyutunda QR baskı testi.
6. Yalıtılmış staging DB ve ayrı staging dizininde gerçek yedek geri yükleme ve uygulama kabul testleri.
7. Güvenli Süper Admin güncelleme/rollback modülünün ayrıca tamamlanması ve test edilmesi.
8. Site Builder V1.5 kapsamı ve masaüstü/mobil kabulü.
9. Büyük video dosyasının gerçek PHP limitleri, queue worker ve FFmpeg/FFprobe ile uçtan uca işlenmesi.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Bu sürümün geri yükleme betiği ön kontrol yapar; SQL veya medya geri yüklemez. Gerçek sunucu ve staging tatbikatı yapılmadan sistem “kurulum tamamlandı” olarak işaretlenmemelidir.
