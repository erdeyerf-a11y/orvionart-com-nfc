# QR/NFC etkinleştirme geçişi — 0.10.28

## Sorun

QR/NFC yönetim ekranındaki etkinleştir/devre dışı bırak işlemi, route-model binding ile yüklenmiş eski bir model değerini tersine çeviriyordu. Eşzamanlı isteklerde iki istek de aynı eski değeri okuyup beklenen iki ayrı geçiş yerine aynı sonuca ulaşabilirdi. Ayrıca durum değişikliği ile denetim kaydı ayrı işlemlerdi; denetim kaydı yazılamazsa durum değişikliği kayıtsız kalabilirdi.

## Düzeltme

- İşlem güncel QR kaydını yeniden sorgular ve destekleyen veritabanlarında `lockForUpdate()` ile satır kilidi alır.
- Etkinlik durumu tersine çevrilir ve denetim kaydı aynı `DB::transaction()` içinde yazılır.
- Denetim kaydı başarısız olursa veritabanı işlemi geri alınır; başarılıysa hem durum hem kayıt birlikte commit edilir.
- Başarı mesajı, kilit altında hesaplanan ve kaydedilen durumdan üretilir.

## Doğrulama sınırı

`tests/Feature/QrAndMediaTest.php` içinde etkinleştirme/devre dışı bırakma ve karşılık gelen denetim kayıtlarını doğrulayan Feature testi eklendi. PHP sözdizimi ve statik release gate kontrolleri çalıştırılabilir. Composer/Laravel runtime mevcut değilse bu Feature testi çalıştırılmış sayılmaz. Gerçek veritabanı motorunda eşzamanlılık testi staging ortamında ayrıca yapılmalıdır.
