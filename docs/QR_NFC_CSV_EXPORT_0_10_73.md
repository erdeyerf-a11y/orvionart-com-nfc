# QR/NFC kayıtlarını CSV dışa aktarma — 0.10.73

## Amaç
Süper Admin, QR/NFC kayıtlarını mevcut arama ve etkin/devre dışı filtreleriyle CSV olarak dışa aktarabilir. Dosya, etiket envanteri ve basım hazırlığında kullanılabilir; kalıcı QR/NFC URL'sini içerir.

## Güvenlik ve sınırlar
- Rota, ortak `auth`, `superadmin` ve `admin.session` middleware grubundadır; dışa aktarım ayrıca dakikada 10 istekle sınırlandırılır.
- En fazla 5.000 kayıt dışa aktarılır; limit aşılırsa indirme yerine filtreleme ekranına hata ile dönülür.
- CSV UTF-8 BOM içerir; formül olarak yorumlanabilecek hücreler apostrofla nötrleştirilir.
- Yanıt `private, no-store` ve `nosniff` başlıkları taşır.
- Dışa aktarım `qr_link.export_prepared` denetim kaydı oluşturur; ziyaretçi IP/tarayıcı takibi eklemez.
- Filtre araması `%`, `_` ve ters eğik çizgiyi LIKE sorgusunda kaçırır.

## Test durumu
İki Laravel Feature test kaynağı eklendi: filtreleme/formül nötrleştirme/audit kaydı ve normal kullanıcının erişiminin reddedilmesi. Bu çalışma ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel testleri çalıştırılmış sayılmaz. PHP sözdizimi ve statik Release Gate kontrolü ayrı çalıştırılır.

## Bilinen sınırlar
CSV dışa aktarımı gerçek yazıcı/etiket baskısı testi değildir. Sunucudaki gerçek alan adı ve HTTPS doğrulanmadan URL'ler basılı materyale aktarılmamalıdır. Bu geliştirme canlı sunucuya dağıtım yapmaz.
