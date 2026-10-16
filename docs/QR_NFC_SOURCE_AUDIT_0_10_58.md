# ORVIONART 0.10.58 — QR/NFC kaynak akışı denetimi

## Kaynakta doğrulanan tasarım

1. Süper Admin deneyim kaydı oluşturur ve yayımlar (`ExperienceController`).
2. Deneyim düzenleme ekranında QR/NFC oluşturma formu bulunur (`resources/views/admin/experiences/form.blade.php`).
3. `QrLink` oluşturulurken kriptografik rastgele 40 karakterli token üretilir; veritabanı migration'ında token benzersizdir.
4. QR indirme endpoint'i `Endroid QR Code` ile gerçek yönlendirme URL'sini SVG olarak üretir (`Admin/QrLinkController::image`).
5. NFC için aynı kalıcı URL panelde gösterilir ve kopyalanabilir. Fiziksel NFC yazma uygulama tarafından yapıldığı iddia edilmez.
6. `/go/{token}` yönlendirmesi bağlantının ve deneyimin güncel durumunu doğrular, tarama sayısını kaydeder ve deneyimin güncel public URL'sine yönlendirir. Slug değişikliği token'ı değiştirmez.
7. Taslak/pasif/arşiv içerik için ziyaretçi erişimi kapalıdır; QR bağlantısı yaşam döngüsü kontrolleri kaynakta bulunur.

## Bu sürümde düzeltilen görünür hata

Deneyim listesi sayfasında eski bir metin QR/NFC ve medya işlevlerinin henüz etkin olmadığını söylüyordu. Bu, deneyim düzenleme ekranındaki gerçek kaynak işlevleriyle çelişiyordu. Metin düzeltildi ve `QrAndMediaTest` içine bunu koruyan bir Feature test kaynağı eklendi.

## Henüz kanıtlanmayanlar

- Composer bağımlılıklarının başarılı çözülmesi/kurulması (`composer.lock` kaynak paketinde mevcut değil).
- Laravel Feature testlerinin gerçekten çalıştırılması.
- Gerçek MySQL/MariaDB bağlantısı ve migration sonucu.
- `APP_URL` değerinin gerçek HTTPS alan adı olması ve QR içine doğru URL'nin gömülmesi.
- Gerçek Android/iOS telefonla QR tarama ve ziyaretçi içeriğinin açılması.
- Fiziksel NFC etikete URL yazma; yapılmadıysa `NOT RUN` raporlanmalıdır.
- Yedekten geri dönüş ve sunucu dağıtım kabulü.

## Manus için kritik sıra

Önce kaynak paketi ve V1.5 ana talimatını incele; hedef Plesk/PHP/Composer/MySQL-MariaDB/HTTPS koşullarını doğrula; güvenli staging ve yedekleme adımlarını uygula; bağımlılıkları kurup lock dosyası üret; migration ve Feature testlerini çalıştır; ardından deneyim oluştur → yayımla → QR üret/indir → gerçek telefondan okut → slug değiştir → aynı QR'yi yeniden okut akışını kanıtla. Kaynak kontrolü veya ZIP açılması tek başına “çalışıyor” demek değildir.
