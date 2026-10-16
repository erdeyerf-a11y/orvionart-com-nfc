# Deneyim ve QR/NFC yaşam döngüsü — 0.10.30

## Amaç
Deneyim taslağa alınırken, arşivlenirken veya soft-delete yapılırken daha önce oluşturulmuş QR/NFC bağlantılarının `is_active=true` kalıp yönetim panelinde etkin görünmesini önlemek. QR token ve tarama geçmişi silinmez.

## Davranış
- Deneyim `draft` veya `archived` durumuna geçirilirse aynı transaction içinde etkin QR bağlantıları devre dışı bırakılır.
- Deneyim soft-delete yapılırken etkin QR bağlantıları devre dışı bırakılır; QR kayıtları/tokenları ve tarama geçmişi korunur.
- Otomatik devre dışı bırakılan her QR bağlantısı için `qr_link.auto_deactivated` denetim kaydı oluşturulur. Durum değişikliği ve denetim kayıtları aynı transaction içindedir.
- Devre dışı bırakılmış QR bağlantısı yalnızca hedef deneyim `published` veya `scheduled` durumundayken yeniden etkinleştirilebilir. Deneyimin gerçekten herkese açık olup olmadığını yönlendirme katmanı ayrıca denetler.
- QR oluşturma, etkinleştirme/devre dışı bırakma ve ziyaretçi yönlendirmesi kilitleri `experience -> qr_link` sırasıyla alır. Amaç, eşzamanlı yayın/durum değişikliklerinde ters kilit sırasından doğabilecek deadlock riskini azaltmaktır.
- QR hedefi taslak, arşivlenmiş veya soft-delete edilmişse yönlendirme `404` döndürür ve tarama sayacını artırmaz.

## Test kaynakları
`tests/Feature/ExperienceManagementTest.php` ve `tests/Feature/QrAndMediaTest.php` içinde durum geçişi, soft-delete, denetim kaydı ve taslak hedef için yeniden etkinleştirme reddi senaryoları bulunur.

## Doğrulama sınırı
Bu sürümde PHP/shell sözdizimi ve statik release gate kontrolleri çalıştırılabilir. Composer ve `vendor/autoload.php` bulunmadığı için Laravel Feature testleri çalıştırılmış sayılmaz. Gerçek MySQL eşzamanlılık/deadlock testi staging ortamında yapılmalıdır.
