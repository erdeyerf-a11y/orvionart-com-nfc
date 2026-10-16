# Platform Integrity: zamanlanmış QR/NFC hedeflerini doğru sınıflandırma (0.10.40)

## Sorun

Bütünleşik platform denetimi, gelecekte yayımlanması planlanan bir deneyime bağlı etkin QR/NFC bağlantısını genel `active_qr_target_unavailable` uyarısına katıyordu. Bu bağlantı şu anda ziyaretçiye açılmıyor olsa da zamanlanmış yayın akışında beklemesi tasarım gereğidir; kırık bağlantıyla aynı kategoriye konması yanlış pozitif üretebilir.

## Değişiklik

- Etkin QR/NFC hedefi silinmiş veya arşivlenmiş/kullanılamayan durumdaysa mevcut uyarı korunur.
- `scheduled` durumunda ve `scheduled_at` gelecekteyse bağlantı `qr_links_waiting_for_scheduled_experience` sayacında ve `active_qr_link_ids_waiting_for_scheduled_experience` listesinde raporlanır.
- Bu bekleme durumu tek başına denetim komutunun hata çıkış koduna neden olmaz.
- Zamanı gelmiş ve herkese açık olması gereken hedefler, geçersiz yayın bilgileri varsa hâlâ kullanılamayan hedef olarak raporlanır.
- Komut salt okunur kalır; QR etkinliğini değiştirmez, tarama sayısını artırmaz ve token değerini rapora yazmaz.

## Doğrulama

`PlatformIntegrityCommandTest` içinde gelecekte planlanan bir deneyimin QR bağlantısının bekleme kategorisine girdiğini, kırık hedef sayılmadığını ve veritabanı kaydının değişmediğini doğrulayan regresyon testi eklendi. Bu kaynak kod ortamında PHP sözdizimi ve statik Release Gate kontrolleri çalıştırılabilir; Composer/Laravel runtime mevcut değilse Feature testin çalıştırıldığı iddia edilmez.
