# Medya silmede özel disk koruması — 0.10.22

## Sorun

Medya silme akışı, dosyayı `orvionart-trash/` yoluna taşırken kayıtta yazan diski kullanıyordu. Mevcut kurulumda `local` özel diskin yanında `public` diski de tanımlı. Eski veya elle oluşturulmuş bir medya kaydı `public` diskini gösterirse, karantina klasörünün web tarafından sunulabildiği bir yapılandırmada taşınan dosya karantinada erişilebilir kalabilirdi. Bu, “özel karantina” varsayımını garanti etmiyordu.

## Değişiklik

- Silme işlemi kilitli medya kaydındaki `disk` değerini kontrol eder.
- Yalnızca `local` diskinde devam eder.
- `public` veya bilinmeyen disk için HTTP 409 döner; medya kaydı, dosya ve referanslar otomatik değiştirilmez.
- Yükleme akışı zaten dosyaları `local` diskine yazar; normal akış değişmez.

## Kabul testi kaynağı

`tests/Feature/QrAndMediaTest.php` içindeki `test_media_on_non_private_disk_is_not_moved_to_quarantine_or_deleted` senaryosu `public` diskteki dosyanın ve DB kaydının korunduğunu doğrular.

## Sınırlar

Bu ortamda Composer ve Laravel `vendor/` bağımlılıkları bulunmadığından Feature testi çalıştırılmadı. PHP sözdizimi ve statik sürüm kontrolleri çalıştırılabilir; gerçek depolama adaptörü davranışı staging ortamında ayrıca doğrulanmalıdır. Bu değişiklik canlı sunucuya uygulanmadı.
