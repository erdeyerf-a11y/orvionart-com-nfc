# ORVIONART 0.10.12 — Medya depolama bütünlük denetimi

## Amaç

`orvionart:inspect-media-storage` komutu, `local` disk üzerindeki ORVIONART tarafından yönetilen medya klasörlerini veritabanı referanslarıyla karşılaştırır:

- `orvionart-media/`
- `orvionart-site-media/`
- `orvionart-demo/`

Komut, dosya bulup silmek için değil; veritabanı kaydıyla eşleşmeyen dosyaları inceleme kuyruğuna çıkarmak için tasarlanmıştır.

## Kullanım

```bash
php artisan orvionart:inspect-media-storage
php artisan orvionart:inspect-media-storage --json
```

JSON çıktısı raporlama ve daha sonra kontrollü bakım iş akışlarına bağlanabilir.

## Güvenlik davranışı

- Salt okunurdur; dosya veya veritabanı kaydı değiştirmez.
- Yalnızca tanımlı üç yönetilen klasörü tarar; `storage/app` içindeki diğer dosyaları incelemez.
- Medya kayıtlarını ve Demo Engine içerik kayıtlarını referans olarak değerlendirir.
- Sahipsiz görünen dosyaları otomatik silmez. Dosya yükleme/DB yazma işlemlerinin yarıda kesilmesi, eski yedeklerden dönülmesi veya henüz tamamlanmamış işlemler yanlış pozitif oluşturabilir.
- Yalnızca `local` diski kontrol eder. S3 uyumlu depolama eklendiğinde ayrı disk taraması ve sayfalı/nesne-listesi tabanlı denetim gerekir.

## Test durumu

İki Laravel Feature test kaynağı eklendi: sahipsiz dosyanın raporlanması ve veritabanında referansı olan dosyanın sahipsiz sayılmaması. Composer/Laravel çalışma zamanı yoksa bu testler yalnızca kaynak olarak mevcuttur; çalıştırılmış kabul edilmemelidir.
