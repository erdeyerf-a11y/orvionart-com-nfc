# Medya silme karantinası — 0.10.19

## Amaç

Medya kaydı silinirken veritabanı işlemi ile dosya sistemi işleminin birbirinden kopması halinde sessiz veri kaybı veya sahipsiz dosya oluşması riskini azaltmak.

## Yeni akış

1. Medya satırı veritabanı işlemi içinde kilitlenir ve bilinen içerik referansları yeniden kontrol edilir.
2. Dosya yolu yalnızca `orvionart-media/` veya `orvionart-site-media/` altında, göreli ve traversal içermeyen bir yolsa kabul edilir.
3. Dosyanın depolamada gerçekten bulunduğu doğrulanır. Dosya yoksa kayıt silinmez; HTTP 409 döner ve medya bütünlük raporuna yönlendirilir.
4. Dosya, aynı private disk içindeki `orvionart-trash/YYYY/MM/DD/` alanına benzersiz adla taşınır.
5. Denetim kaydı yazılır ve medya satırı silinir.
6. Veritabanı işlemi başarısız olursa karantina dosyası asıl konumuna geri taşınmaya çalışılır. Geri taşıma da başarısız olursa kritik kayıt yazılır.
7. Veritabanı işlemi başarılı olduktan sonra karantina dosyası kalıcı olarak silinir. Son silme başarısız olursa yöneticiye uyarı verilir ve dosya karantinada bırakılır.

## Karantina denetimi

`php artisan orvionart:inspect-media-storage --json` komutu `orvionart-trash/` klasörünü de salt okunur inceler. Karantinada kalan dosyalar `media_quarantine_cleanup_pending` uyarısıyla raporlanır. Komut bunları otomatik silmez.

## Güvenlik sınırları

- Yalnızca yönetilen medya klasörlerindeki göreli yollar silinebilir.
- Mutlak yol, Windows sürücü yolu, ters eğik çizgi ve `.` / `..` segmentleri reddedilir.
- Medya silme işlemi Demo Engine, Site Builder, deneyim ve başka medya kaydı referanslarını yeniden kontrol eder.
- Bu düzenleme uygulama düzeyinde iyileştirmedir; gerçek DB/storage hata enjeksiyonu ve eşzamanlı istek testleri staging ortamında ayrıca yapılmalıdır.
- Laravel Feature testleri kaynak koduna eklenmiştir ancak Composer/Laravel runtime bu çalışma ortamında mevcut olmadığından burada çalıştırılmamıştır.
