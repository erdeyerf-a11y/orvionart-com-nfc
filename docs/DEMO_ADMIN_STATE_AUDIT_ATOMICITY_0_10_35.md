# Demo Engine yönetici durum değişikliklerinde atomiklik — 0.10.35

## Kapsam

Bu sürüm Demo Engine yönetimindeki iki durum değişikliğini kapsar:

- Genel Demo Engine ayarlarının kaydedilmesi ve etkin/pasif durumunun değiştirilmesi.
- Bir demo içeriğinin etkinleştirilmesi veya devre dışı bırakılması.

## Önceki risk

Ayar/içerik modeli önce kaydedilip audit olayı daha sonra ayrı bir işlemde yazılırsa, audit kaydı oluşturulamadığında gerçek durum değişikliği kayıtsız kalabilir. İçerik toggle işlemi ayrıca route üzerinden bağlanan eski modelin durumuna dayanırsa eşzamanlı isteklerde güncel olmayan durumu tersine çevirebilir.

## Uygulanan koruma

- Ayar değişikliği transaction içinde yapılır; varsayılan ayar satırı işlem içinde bulunur ve `lockForUpdate` ile yeniden okunur.
- Ayar kaydı ve audit olayı aynı transaction içindedir.
- İçerik toggle işlemi kaydı transaction içinde kilitleyip yeniden okur; yeni durum bu güncel kayda göre belirlenir.
- İçerik kaydı ve audit olayı aynı transaction içindedir.
- Hata olursa işlem bütünüyle rollback edilir; başarılı cevap yalnızca transaction tamamlandıktan sonra üretilir.

## Doğrulama

`tests/Feature/DemoEngineTest.php` içinde ayar değişikliği ile audit kaydının eşleşmesini ve içerik toggle işleminin güncel durumla uyumlu audit olayı oluşturmasını kontrol eden iki test kaynağı eklendi.

Bu çalışma ortamında Composer ve `vendor/autoload.php` yoksa Laravel Feature testleri çalıştırılamaz. PHP sözdizimi ve statik release gate sonucu runtime testi olarak değerlendirilmemelidir. Staging ortamında ayrıca audit insert hatası altında rollback, eşzamanlı toggle ve gerçek veritabanı kilit davranışı doğrulanmalıdır.
