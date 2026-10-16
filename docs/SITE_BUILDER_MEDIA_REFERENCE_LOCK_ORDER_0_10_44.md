# Site Builder medya referansı kilit sırası — 0.10.44

## Risk

Medya silme işlemi bir `MediaAsset` satırını kilitleyip taslak, yayımlanmış içerik ve saklanan revision snapshot'larında referans arar. Site Builder aynı anda bir medya kimliğini sayfaya ekleyip yayınlayabilirse, kilitlenmeyen referans doğrulaması iki işlem arasında yarış durumuna girebilir.

## Değişiklik

- Sayfa güncelleme, yayınlama ve revision geri alma işlemleri önce sayfaya ait medya kayıtlarını `id` sırasıyla `lockForUpdate()` ile kilitler; ardından sayfa satırını kilitler.
- Medya silme de ilgili medya satırını önce kilitlediğinden, sayfaya ait bir dosya silinirken içerik geçişi aynı satır için bekler.
- Güncelleme sırasında, işlem kilitleri alındıktan sonra bölüm ve sosyal görsel referansları yeniden doğrulanır. Ön doğrulama ile transaction arasındaki zaman aralığında silinmiş bir medya kaydı kaydedilemez.
- Yayınlama ve revision geri alma, kilitli medya satırları tutulurken son taslak/snapshot referanslarını tekrar doğrular.
- Çoklu medya kilitleri deterministik `id` sırasıyla alınır.
- Eksik medya referansı bulunan taslağın yayınlanmasını ve canlı snapshot'ın değişmesini engelleyen bir Feature test kaynağı eklendi. Release Gate, üç geçişin de ortak kilit yardımcısını kullandığını kontrol eder.

## Sınırlar

Bu değişiklik dosya sistemiyle veritabanı arasında tam bir dağıtık transaction sağlamaz. Gerçek MySQL/MariaDB üzerinde eşzamanlı iki oturumla silme-yayınlama yarışı ve deadlock davranışı staging ortamında test edilmelidir. Bu ortamda Composer/`vendor/autoload.php` bulunmadığından Laravel Feature testi çalıştırıldığı iddia edilmez.
