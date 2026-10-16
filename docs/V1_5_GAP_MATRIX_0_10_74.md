# V1.5 gereksinim matrisi — 0.10.74

## Bu sürümde yapılan geliştirme
- Güncelleme paketleri için salt-okunur ön denetim aracı eklendi: SHA-256, ZIP bütünlüğü/yol güvenliği, sır/runtime dosyası reddi, symlink reddi, sürüm ve manifest doğrulaması, PHP lint. Bu araç dağıtım veya rollback yapmaz.
- Ön denetim regresyon paketi geçerli ZIP, hatalı checksum, path traversal ve `.env` reddini kapsar.
- QR/NFC kayıtları için Süper Admin korumalı CSV dışa aktarımı eklendi. Liste ekranındaki arama ve etkin/devre dışı filtreleri dışa aktarıma uygulanır.
- CSV, etiket adı, deneyim, hedef durumu, bağlantı durumu, kalıcı QR/NFC URL'si, tarama sayısı ve tarihleri içerir. UTF-8 BOM ve formül hücresi nötrleştirme uygulanır; 5.000 kayıt sınırı, istek kısıtlaması ve denetim kaydı vardır.
- İki Laravel Feature test kaynağı eklendi. Yeni migration yoktur. Composer/Laravel runtime bu ortamda bulunmadığından Feature testleri çalıştırılmış sayılmaz.
- 0.10.72 ve önceki sürümlerde eklenen güvenli staging geri yükleme/şema denetimi, Site Builder ve medya özellikleri korunmuştur.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya/video işleme ve QR/NFC ziyaretçi akışı.
- QR SVG önizleme/indirme, yazdırma ekranı ve NFC için aynı kalıcı adresi kopyalama.
- Site Builder sayfa/genel sosyal görseli, gezinme, tema, iletişim, müşteri görüşleri ve istatistik bölümleri.
- Demo Engine, kullanım hakları, video worker ve platform bütünlük denetimleri.
- QR/NFC envanter CSV dışa aktarımı kaynakta mevcut; gerçek Laravel runtime ve tarayıcı kabulü bekleniyor.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Gerçek yazıcı veya hedef etiket boyutunda QR baskı testi; CSV'den alınan adreslerin alan adı/HTTPS kontrolü.
6. Yedekleme ve geri alma denemesi.
7. Güvenli Süper Admin sürüm güncelleme/geri alma modülü: ön denetim temeli eklendi; kontrollü staging dağıtımı, yedek/geri alma, işlem günlüğü ve yetki arayüzü henüz tamamlanmadı.
8. Site Builder'ın V1.5 bileşen kapsamı ve masaüstü/mobil kabulü.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Gerçek sunucu, yazıcı ve telefon testleri yapılmadan uygulama “çalışır üretim sistemi” olarak ilan edilmemelidir.
