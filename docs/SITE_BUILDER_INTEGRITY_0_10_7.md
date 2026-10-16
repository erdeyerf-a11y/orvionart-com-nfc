# ORVIONART 0.10.7 — Site Builder bütünlük denetimi

## Amaç

`orvionart:inspect-site-builder`, Site Builder sayfalarının yayın anlık görüntülerini ve etkin menü hedeflerini salt okunur olarak kontrol eder. Bu komut otomatik düzeltme yapmaz ve hiçbir sayfayı yayımlamaz/yayından kaldırmaz.

## Çalıştırma

```bash
php artisan orvionart:inspect-site-builder
php artisan orvionart:inspect-site-builder --json
```

Komut, uyarı bulunmazsa `0`, bütünlük uyarısı bulunursa `1` çıkış kodu verir. JSON raporu sürüm ve rapor zamanının yanında sayım, kayıt kimlikleri, uyarı kodları ve sınırlamaları içerir. QR/NFC token'ları, parolalar veya medya dosyalarının mutlak yolları rapora eklenmez.

## Kontrol edilenler

- `published` durumundaki sayfanın yayın tarihi, yayın slug'ı ve yayın içerik anlık görüntüsü bulunması.
- Aynı yayın slug'ının birden fazla sayfada kullanılması.
- Etkin menü öğesinin tam olarak bir hedefe sahip olması (bir sayfa veya bir haricî bağlantı).
- Haricî menü hedefinin HTTPS olması.
- Sayfa menü hedefinin halen herkese açık olması.
- Üst menüdeki sayfanın `published_show_in_menu` kuralına uyması.
- Yayında işaretli sayfanın yayın zamanının gelecekte olmaması.

## Sınırlar

Bu komut gerçek HTTP yanıtını, tarayıcı görünümünü, mobil erişilebilirliği veya kullanıcı yetkilerini test etmez. Medya dosyası ve bölüm medya referansları için ayrıca `orvionart:inspect-platform` kullanılmalıdır. Gerçek Laravel testleri, Composer bağımlılıkları kurulmuş ve test veritabanı yapılandırılmış ortamda çalıştırılmalıdır.
