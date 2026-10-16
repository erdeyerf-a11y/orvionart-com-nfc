# Deneyim erişim bitiş zamanı — 0.10.60

## Amaç
Süper Admin, deneyimin ne zaman erişime açılacağını mevcut yayın durumu/zamanlama kurallarıyla yönetebilir; artık isteğe bağlı bir bitiş zamanı da belirleyebilir. Bitiş zamanı geldiğinde QR/NFC tokeni ve basılı etiket değişmez, fakat ziyaretçi içeriğe erişemez.

## Davranış
- `ends_at` boşsa mevcut yayınlama davranışı korunur ve bitiş sınırsızdır.
- `ends_at` doluysa yayınlanmış veya zamanı gelmiş zamanlanmış deneyim yalnızca `ends_at > now()` iken kamuya açıktır.
- Zamanlanmış deneyimin bitişi planlanan yayın zamanından sonra olmalıdır.
- Yeni yayınlanan deneyimde girilen bitiş zamanı geçmişte olamaz.
- QR yönlendirme, hedef sayfa ve herkese açık deneyim sorgusu aynı model erişim kuralını kullanır.
- Süresi dolmuş bir QR taraması yönlendirme yapmaz ve okutma sayacını artırmaz.
- Eski form istemcisi `ends_at` alanını hiç göndermiyorsa mevcut bitiş zamanı korunur; alanın boş gönderilmesi ise bitiş zamanını temizler.
- Geçmiş veriler için alan nullable olduğu için migration eski deneyimleri sınırsız bırakır.

## Güvenlik ve geri dönüş
Migration yalnızca nullable `ends_at` kolonu ve indeksi ekler; mevcut satırlar değişmez. `down()` indeksi ve kolonu kaldırır. Geri alma sırasında girilmiş bitiş tarihleri kaybolacağından migration rollback öncesi veritabanı yedeği gerekir.

## Doğrulama sınırı
PHP lint ve statik Release Gate bu sürümde çalıştırılır. Laravel Feature testleri ancak Composer bağımlılıkları kurulu bir ortamda `php artisan test` ile çalıştırılmış sayılır. Plesk/MariaDB ve gerçek QR/NFC telefon testi bu kaynak paketinden tek başına doğrulanamaz.
