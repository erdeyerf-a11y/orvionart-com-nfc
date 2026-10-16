# Markalı QR ürün etiketi — 0.10.61

## Amaç
Ürün üzerindeki QR etiketi basıldığında yalnızca QR karelerinden ibaret kalmasın; ORVIONART adı, bağlı deneyimin başlığı ve ziyaretçiye QR okutma yönlendirmesi de görünsün.

## Davranış
- Yönetici QR önizlemesine girdiğinde etiketin markalı düzenini görür.
- Yazdır komutu etiket alanını basar; yönetim menüleri, hedef durumu, NFC adresi ve işlem düğmeleri çıktıya girmez.
- QR SVG'si ayrı endpoint'ten geldiği için aynı kalıcı `/go/{token}` adresini kullanır; QR içeriği değiştirilmez.
- QR çevresindeki beyaz boşluk korunur. Baskı boyutu yazıcı/etiket stoğuna göre gerçek cihazda kontrol edilmelidir.
- NFC etiketi fiziksel olarak yazdırılmaz; aynı kalıcı adres yönetim ekranından kopyalanır ve uyumlu NFC yazma uygulamasıyla yazılır.

## Doğrulama sınırı
- Kaynak ve PHP sözdizimi kontrolleri yapılabilir.
- Laravel Feature testi için Composer bağımlılıkları ve `vendor` gerekir.
- Gerçek yazıcı, QR tarama ve NFC etiket testi Plesk/telefon üzerinde yapılmalıdır; bu belge bunların yapıldığını iddia etmez.
