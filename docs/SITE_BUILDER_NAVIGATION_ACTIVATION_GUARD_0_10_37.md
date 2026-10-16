# Site Builder menü bağlantısı etkinleştirme koruması — 0.10.37

## Amaç

Menü öğesi daha önce oluşturulduktan sonra hedef sayfa taslağa alınmış, yayından kaldırılmış veya menüye uygunluğunu kaybetmiş olabilir. Böyle bir öğeyi yönetim panelinde etkinleştirmek, ziyaretçiye görünmeyen bir hedefe bağlı ve yanıltıcı bir “aktif” kayıt oluşturabilir.

## Değişiklik

- Menü öğesinin güncel durumu transaction içinde satır kilidiyle okunur.
- Etkinleştirme isteğinde sayfa hedefi varsa sayfa kilit altında yeniden okunur ve gerçekten yayımlanmış olup olmadığı kontrol edilir.
- Üst menü (`header`) öğesi için hedef sayfanın yayımlanmış `published_show_in_menu` değeri de doğrulanır.
- Haricî hedefler etkinleştirilirken HTTPS biçimi tekrar doğrulanır.
- Hedef uygun değilse işlem kaydedilmez ve `site_navigation.toggled` denetim olayı yazılmaz.
- Bir öğeyi devre dışı bırakma işlemi hedef kullanılamıyor olsa da mümkündür.
- Doğrulama başarısız olduğunda kullanıcıya açıklayıcı hata mesajı döndürülür.

## Test durumu

Bir Laravel Feature test kaynağı eklendi: taslak sayfaya bağlı pasif menü öğesinin etkinleştirilmesinin reddedilmesi, öğenin pasif kalması ve audit kaydı oluşmaması doğrulanır. Bu geliştirme ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmadı. PHP sözdizimi ve statik Release Gate kontrolleri ayrıca raporlanır.

## Sınırlar

Bu koruma menü bağlantısının durumunu ve hedef uygunluğunu denetler; sayfanın görsel kalitesini veya ziyaretçi tarayıcılarındaki son görünümünü doğrulamaz. Canlı sunucuya dağıtım yapılmamıştır.
