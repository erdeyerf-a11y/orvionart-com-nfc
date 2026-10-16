# ORVIONART 0.10.17 — teslimat özeti

## Bu sürümde eklenenler

- `orvionart:inspect-site-redirects` komutu eklendi ve `bootstrap/app.php` üzerinden kaydedildi.
- Eski/yeni slug biçimi, self-redirect, 301/302 dışındaki durum kodları, erişilemeyen yayın hedefleri, düzenlenebilir slug ile çakışan kaynaklar ve büyük/küçük harf farkıyla yinelenen kaynaklar salt okunur denetlenir.
- JSON raporu ve dört Laravel Feature test kaynağı eklendi.
- Release gate, komutun, test dosyasının ve açıklama belgesinin teslimatta bulunmasını doğrular.

## Yerel statik kontrol sonucu

- PHP kaynak sözdizimi: başarılı.
- Shell sözdizimi: başarılı.
- `composer.json` JSON biçimi: geçerli.
- Migration statik denetimi: 13 dosyada bloklayıcı hata yok.
- Migration denetimi regresyon testi: başarılı.
- Kaynak manifesti: 188 dosya.
- Release gate: 24 PASS, 2 WARN, 0 FAIL.
- ZIP bütünlük kontrolü ve SHA-256: paket oluşturulduktan sonra doğrulanır.

## Açık testler ve sınırlar

Composer ve `vendor/` bulunmadığından `composer validate` ve Laravel Feature testleri bu ortamda çalıştırılamadı. Gerçek DB migration/rollback, Plesk staging, HTTPS, tarayıcı/mobil, gerçek QR/NFC donanımı, yedekten geri yükleme ve canlı dağıtım testleri yapılmadı. Bu paket canlıya çıkış onayı değildir.

## Teslimat ilkeleri

- Gerçek `.env` ve üretim sırları pakete konmaz.
- Ödeme entegrasyonu varsayılan olarak kapalı kalır.
- Kullanım hakkı iş kuralları ayrıca kararlaştırılmadan otomatik ücretlendirme eklenmez.
- Manus'a gönderim ve gerçek sunucuya dağıtım bu sürüm kapsamında yapılmadı.
