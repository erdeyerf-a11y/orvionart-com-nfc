# QR/NFC önizleme ve yazdırma — 0.10.59

## Amaç
V1.5 ana talimatındaki QR önizleme/indirme ve NFC için gerçek kalıcı URL gereksinimlerini, günlük kullanımda daha anlaşılır hâle getirmek.

## Kullanıcı akışı
1. Süper Admin > QR/NFC bağlantıları ekranını açar.
2. “QR önizle” ile gerçek QR SVG kodunu ekranda görür.
3. Gerekirse “QR kodunu yazdır” ile yazdırma görünümünü açar.
4. “Adresi kopyala” ile aynı kalıcı `/go/{token}` adresini NFC yazma uygulamasına aktarır.
5. QR SVG dosyasını ayrıca indirebilir.

## Güvenlik ve yaşam döngüsü
- Önizleme ve indirme ayrı uçlar olsa da aynı URL'yi kodlar.
- Her SVG isteğinde bağlantının aktif olması ve deneyimin erişilebilir/yayınlanmış veya ileri tarihli zamanlanmış durumda olması tekrar doğrulanır.
- SVG yanıtı `nosniff` ve `private, no-store` başlıklarını kullanır.
- NFC etiketi fiziksel olarak yazılmaz; arayüz bunu açıkça belirtir.
- Token, QR hedefi ve deneyim ilişkisi değişmez; yeni migration eklenmedi.

## Test durumu
- İki Laravel Feature test kaynağı eklendi: önizleme ekranı ve inline SVG/kapalı bağlantı davranışı.
- PHP sözdizimi/statik kontroller bu pakette ayrıca raporlanır.
- Composer bağımlılıkları ve `vendor/autoload.php` bulunmadığı için Laravel Feature testleri bu ortamda çalıştırılmadı.
- Gerçek Plesk, HTTPS, fiziksel baskı ve telefon kamerasıyla QR taraması henüz doğrulanmadı.
