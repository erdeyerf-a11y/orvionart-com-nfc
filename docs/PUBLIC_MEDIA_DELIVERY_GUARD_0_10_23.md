# Public Media Delivery Guard — 0.10.23

## Amaç

Yayınlanmış deneyim veya Site Builder sayfasına bağlı medya, ziyaretçilere yalnızca kontrollü özel yerel depolama üzerinden sunulur. Veritabanındaki eski veya bozulmuş medya kaydı tek başına başka depolama disklerini veya keyfî dosya yollarını ziyaretçiye açmamalıdır.

## Uygulanan korumalar

- Medya kaydının diski tam olarak `local` değilse yanıt `404` olur.
- Dosya yolu yalnızca `orvionart-media/` veya `orvionart-site-media/` yönetilen öneklerinden biri altında olabilir.
- Mutlak yollar, Windows sürücü yolları, ters eğik çizgi ve `.` / `..` / boş yol segmentleri reddedilir.
- Dosya gerçek yolu çözülür; sembolik bağlantı üzerinden özel depolama kökünün dışına çıkan dosya sunulmaz.
- Yayın durumu, medya MIME türü ve dosyanın varlığı kontrolleri korunur.
- Denetim, dosya veya veritabanı kaydını değiştirmez.

## Test kaynakları

`tests/Feature/QrAndMediaTest.php` içinde yayınlanmış deneyim medyasının normal sunulması, `public` diskteki eski kaydın reddi, güvensiz yolun reddi ve sembolik bağlantı senaryoları için regresyon testleri eklendi.

## Sınırlar

Bu sürümde Composer/Laravel çalışma zamanı mevcut olmadığından Feature testleri yürütülememiştir. Bu belge testlerin geçtiğini iddia etmez. Sunucu dosya izinleri, gerçek disk kökü ve web sunucusunun özel depolama klasörünü yayımlamaması staging ortamında ayrıca doğrulanmalıdır.
