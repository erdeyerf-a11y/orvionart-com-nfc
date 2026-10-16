# Site Builder kilitli sayfa geçişleri — 0.10.38

## Amaç

Site Builder güncelleme, yayınlama, yayından kaldırma ve geçmiş sürüm geri alma işlemleri, işlem başlamadan önce route model binding ile yüklenmiş olabilecek eski bir `SitePage` nesnesinden yazmamalıdır. Birden fazla yönetici aynı sayfada işlem yaparsa sürüm sayacı, revizyon kaydı ve audit metadata güncel satırla tutarlı kalmalıdır.

## Değişiklikler

- Taslak güncelleme, `SitePage` satırını transaction içinde `lockForUpdate()` ile yeniden okur; `before_version` kilitli satırın snapshot'ından alınır.
- Yayınlama, en son taslağı kilit altında yeniden okur; yayın slug çakışması, bölüm medyası ve sosyal paylaşım görseli doğrulaması transaction içinde güncel taslak üzerinden yapılır.
- Yayın snapshot'ı, kalıcı yönlendirmeler, yayın sürüm sayacı, revision ve audit olayı tek transaction içinde yazılır.
- Yayından kaldırma, güncel satırı kilitleyerek sürüm sayacını artırır ve revision/audit kayıtlarını aynı transaction içinde oluşturur.
- Geçmiş sürüm geri alma, güncel sayfayı kilitleyip snapshot/medya kontrollerini transaction içinde tekrar çalıştırır; canlı yayın snapshot'ı ayrıca yayınlama yapılmadıkça değiştirilmez.
- Site Builder güncellemesi için sürüm geçişi ve audit metadata'sını kontrol eden bir Feature test kaynağı eklendi.

## Doğrulama sınırı

PHP sözdizimi, kaynak manifesti ve statik Release Gate kontrolleri çalıştırılabilir. Composer ve `vendor/autoload.php` yoksa Laravel Feature testleri çalıştırılmış sayılmaz. Gerçek veritabanında eşzamanlı yönetici istekleri, staging ve Plesk kabul testleri ayrıca gereklidir. Bu belge canlıya çıkış onayı değildir.
