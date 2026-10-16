# QR/NFC yaşam döngüsü denetimi — 0.10.14

## Komut

```bash
php artisan orvionart:inspect-qr-lifecycle
php artisan orvionart:inspect-qr-lifecycle --json
```

Komut QR/NFC kayıtlarını salt okunur biçimde inceler. Token biçimini, token tekrarlarını, etkin bağlantıların hedef deneyim erişimini, tarama sayacı/son tarama zamanı tutarlılığını ve şüpheli zaman damgalarını kontrol eder.

## Gizlilik ve güvenlik

- Ham token değerleri terminale veya JSON raporuna yazılmaz.
- Rapor yalnızca kayıt kimliklerini örnekler.
- Komut kayıtları güncellemez, bağlantıları etkinleştirmez/devre dışı bırakmaz ve tarama sayacını değiştirmez.
- Bulgular otomatik düzeltilmez; yetkili operatör kayıtların kaynağını inceler.
- Bu komut gerçek QR görüntüsü, fiziksel NFC etiketi, mobil cihaz veya tarayıcı kabul testi değildir.

## Kontroller

- 40 karakterli alfasayısal token biçimi
- Tekrarlanan token kayıtları (benzersizlik indeksi olsa da eski/harici veri için ek denetim)
- Etkin olup hedef deneyimi herkese açık olmayan bağlantılar
- Pozitif tarama sayacı olup `last_scanned_at` alanı boş kayıtlar
- Sıfır tarama sayacı olup son tarama zamanı dolu kayıtlar
- Gelecekteki son tarama zamanları
- QR kaydı oluşturulmadan önce gerçekleşmiş görünen tarama zamanları

Laravel Feature test kaynakları eklendi. Composer/Laravel bağımlılıkları kurulu değilse testler çalıştırılmış sayılmaz.
