# ORVIONART 0.10.15 — Medya referans bütünlüğü denetimi

## Amaç

`orvionart:inspect-media-references`, `MediaAsset` ve `DemoContent` kayıtlarının local disk üzerindeki medya yollarını salt okunur biçimde inceler. Yönetilen klasörler:

- `orvionart-media/`
- `orvionart-site-media/`
- `orvionart-demo/`

## Kullanım

```bash
php artisan orvionart:inspect-media-references
php artisan orvionart:inspect-media-references --json
```

## Denetimler

- Boş, mutlak, sürücü öneki içeren veya `.` / `..` yol segmentleri içeren güvensiz yollar.
- Beklenen yönetilen klasörlerin dışındaki yollar (insan incelemesi için raporlanır; otomatik olarak geçersiz ilan edilmez).
- Veritabanı kaydı bulunan ancak dosyası bulunamayan medya referansları.
- Depolama sorgusunun tamamlanamaması.

Rapor kayıt kimliklerini ve sorun kodlarını içerir; ham dosya içeriği, token veya gizli bilgi içermez. En fazla 100 sorun örneği raporlanır; sayımlar tüm incelenen kayıtlara dayanır.

## Güvenlik sınırları

Komut salt okunurdur. Dosya veya veritabanı kaydı silmez, değiştirmez ya da taşımaz. Otomatik düzeltme yapmaz. Yalnızca `local` disk üzerindeki medya referanslarını kontrol eder. Yönetilen klasör dışındaki yollar, uygulamada başka bir geçerli kullanım olup olmadığının belirlenmesi için insan incelemesine bırakılır.

Bu komutun Feature test kaynakları eklendi; Composer/Laravel bağımlılıkları olmayan geliştirme ortamında gerçek Laravel testleri çalıştırılamadı. Bu statik kaynak kontrolü gerçek sunucu veya yedek geri yükleme kabul testi değildir.
