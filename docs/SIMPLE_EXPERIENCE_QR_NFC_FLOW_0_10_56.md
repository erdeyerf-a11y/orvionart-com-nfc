# Basit deneyim → QR/NFC akışı — 0.10.56

1. Süper Admin > Deneyimler > Yeni deneyim.
2. Başlık, kısa açıklama ve içerik metnini gir; durumu `Yayınlandı` yapıp kaydet.
3. Aynı deneyim düzenleme ekranındaki `QR / NFC bağlantısı` alanından etiket adını girip bağlantıyı oluştur.
4. `QR görselini indir` ile SVG dosyasını al. Baskıdan önce gerçek telefonla okut.
5. NFC etiketine yazmak için aynı alandaki kalıcı adresi kopyala ve NFC yazma uygulamasında etikete yaz. Sistem etiketi kendiliğinden fiziksel olarak yazmaz.
6. Ziyaretçi QR/NFC'yi okuttuğunda telefon tarayıcısı deneyim sayfasını açar. Yönetici içeriği güncelleyebilir; mevcut kalıcı adres değişmez.

Taslak/arşiv içerik ziyaretçiye açılmaz. QR/NFC hedefi oluşturmak için deneyim yayınlanmış veya zamanlanmış olmalıdır. Geleceğe zamanlanan içerik yayın saatine kadar ziyaretçiye açılmaz.

## Doğrulama sınırı

Bu belge kaynak kodun amaçlanan kullanımını anlatır. Composer/Laravel çalışma zamanı yoksa Feature testleri çalıştırılmış sayılmaz. Plesk, gerçek alan adı/HTTPS, QR baskı/tarama ve NFC yazma kabulü ayrıca yapılmalıdır.
