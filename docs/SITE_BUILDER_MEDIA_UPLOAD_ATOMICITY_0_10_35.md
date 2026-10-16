# Site Builder medya yüklemesinde atomiklik — 0.10.35

Site Builder medya yükleme akışı, dosyayı `local` özel depolamasına yazar ve ardından `media_assets` kaydı ile `site_page.media_uploaded` denetim kaydını oluşturur.

Önceki akışta medya satırı veritabanına yazıldıktan sonra denetim kaydı oluşturma başarısız olursa dosya temizlenebilirdi; medya satırının kendi işlemi otomatik olarak geri alınmıyordu. Böylece var olmayan dosyaya işaret eden bir medya kaydı kalabilirdi.

0.10.35 ile medya satırı ve denetim kaydı aynı `DB::transaction` içine alındı. Herhangi bir veritabanı hatasında işlem geri alınır; dıştaki hata yakalama dosyayı depolamadan silmeyi dener ve hatayı yeniden fırlatır. Başarılı durumda dosya, medya satırı ve denetim kaydı birlikte mevcut olur.

`tests/Feature/SiteBuilderTest.php` içine iki regresyon testi eklendi:

- Denetim kaydı oluşturulması hata verdiğinde medya satırının rollback edilmesi ve yüklenen dosyanın temizlenmesi.
- Başarılı yüklemede dosyanın, medya satırının ve denetim kaydının birlikte mevcut olması.

Bu testlerin kaynakları eklendi; ancak bu geliştirme ortamında `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılamadı. Gerçek veritabanı ve dosya sistemi davranışı staging kabulünde ayrıca doğrulanmalıdır.
