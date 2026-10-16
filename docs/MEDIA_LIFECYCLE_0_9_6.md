# ORVIONART 0.9.6 — Medya yaşam döngüsü

## Amaç
Merkezi medya kütüphanesinde deneyime bağlı bir dosyayı güvenli biçimde ayırabilmek ve dosya gerçekten bir içerikte kullanılmıyorsa kalıcı silmeyi mümkün kılmak.

## Değişiklikler
- `PATCH /admin/media/{asset}/detach-experience` yalnızca Süper Admin grubunda kullanılabilir.
- “Kütüphaneye taşı” işlemi `experience_id` bağlantısını kaldırır; dosyanın kendisini silmez.
- İşlem `media.detached_from_experience` denetim kaydı üretir ve önceki deneyim kimliğini metaveride saklar.
- Deneyimden ayrılan dosya, artık bir deneyime bağlı olmadığı için kamuya açık medya rotasından sunulmaz.
- Medya kütüphanesi ekranında deneyime bağlı dosyalar için silme yerine “Kütüphaneye taşı” eylemi gösterilir.
- Site Builder taslağı veya yayınlanan snapshot içinde görsel/sosyal paylaşım görseli olarak kullanılan dosyalar silinemez.
- Kalıcı silme sırasında kayıt tekrar kilitlenip kullanım referansları denetlenir; dosya baytları ancak veritabanı işlemi tamamlandıktan sonra silinir.
- Dosya silme ve bağlantıyı kaldırma işlemleri denetim kaydına yazılır.

## Yönetim davranışı
1. Deneyime bağlı dosyayı ayırmak için Medya Kütüphanesi ekranında **Kütüphaneye taşı** seçilir.
2. Dosya merkezi kütüphanede tutulur; bu işlem dosyanın içeriğini silmez.
3. Dosya hiçbir deneyim veya Site Builder içeriği tarafından kullanılmıyorsa **Kalıcı sil** seçeneği gösterilir.
4. Site Builder'da kullanılan bir görsel için önce taslak ve/veya yayındaki içerik referansı kaldırılmalı, taslak kaydedilmeli ve gerekiyorsa sayfa yeniden yayımlanmalıdır.

## Güvenlik notları
- Bağlantıyı kaldırmak yayındaki deneyimden görsel/video sunumunu anında kaldırabilir; onay penceresi bu etkiyi açıklar.
- Kalıcı silme geri alınamaz. Yedekleme politikası gerçek sunucuda ayrıca doğrulanmalıdır.
- İşlem veritabanı satır kilidi kullanır. Farklı veritabanı motorlarındaki kilitleme davranışı staging ortamında doğrulanmalıdır.

## Test durumu
`tests/Feature/QrAndMediaTest.php` içine şu senaryolar eklendi:
- Deneyim medyası kütüphaneye taşındığında dosya disk üzerinde kalır ve audit izi oluşur.
- Ayrılan medya artık kamuya açık medya rotasından sunulmaz.
- Deneyime bağlı medya, bağlantı kaldırılmadan silinemez.

Test kaynakları eklendi ancak Composer bağımlılıkları bu çalışma alanında bulunmadığından PHPUnit/Laravel testleri çalıştırılmadı. Gerçek sunucuya kurulum yapılmadı.
