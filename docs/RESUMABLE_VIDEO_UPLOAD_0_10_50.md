# Parçalı ve devam edebilen video yükleme — 0.10.51

## Kapsam

- Süper Admin oturumuyla başlayan video yükleme oturumu.
- Sabit 4 MiB parçalar; son parça dosyanın kalan boyutu kadar.
- Oturum başına 512 MiB üst sınırı, 128 parça üst sınırı ve 24 saatlik süre.
- Parça numarası, beklenen bayt sayısı, sahiplik, oturum durumu ve süre kontrolü.
- Parça kayıtlarının veritabanında izlenmesi; istemcinin gönderdiği `X-Chunk-SHA256` değeri sunucuda yeniden hesaplanan hash ile karşılaştırılır.
- Aynı parça tekrar geldiğinde idempotent cevap verilir; farklı içerik aynı parçanın üzerine yazılamaz.
- Birleştirme öncesi disk içeriği, her parçanın kaydedilmiş SHA-256 değeriyle tekrar doğrulanır.
- Birleştirme sırasında her parçanın varlık/yol kontrolü, toplam dosya boyutu kontrolü ve gerçek MIME türü doğrulaması.
- Başarılı birleştirmede medya kaydı ile audit kaydının aynı veritabanı transaction'ında oluşturulması.
- Veritabanı commit'inden sonra geçici dizin temizliği başarısız olursa tamamlanmış medya dosyası silinmez; temizlik hatası güvenli biçimde loglanır.
- İstemci, sunucunun tamamlamayı kaydettiği fakat HTTP yanıtının kaybolduğu durumda aynı medya dosyasını ikinci kez oluşturmadan tamamlanmış oturumu tanır.
- Tarayıcıda aynı dosya tekrar seçildiğinde kayıtlı oturumdan eksik parçaları sürdürme; önceden yüklenmiş parçaların SHA-256 değerleri yeniden kontrol edilir.
- Süresi dolmuş ve takılı kalmış geçici yükleme dizinlerinin zamanlanmış temizlik komutu.

## Rotalar

- `POST /admin/media/video-uploads` — oturum başlatma.
- `GET /admin/media/video-uploads/{upload}` — oturum durumu.
- `POST /admin/media/video-uploads/{upload}/chunks/{index}` — tek parça yükleme.
- `POST /admin/media/video-uploads/{upload}/complete` — birleştirme ve doğrulama.
- `DELETE /admin/media/video-uploads/{upload}` — iptal.

Bütün rotalar `auth`, `superadmin` ve `admin.session` grubunun içindedir. Oturum, yalnızca oluşturan yönetici tarafından kullanılabilir.

## Sınırlar ve dürüst durum bildirimi

- Kabul edilen türler MP4, WebM ve OGG video dosyalarıdır.
- Bu sürüm parçalı aktarım, istemci/sunucu parça SHA-256 karşılaştırması, birleştirme ve MIME doğrulamasını uygular. Birleştirme sonrası FFprobe doğrulaması arka plan kuyruğunda yapılır; durum `queued/processing/ready/failed` olarak izlenir. FFmpeg transcoding, codec optimizasyonu ve küçük resim üretimi henüz uygulanmamıştır.
- Tarayıcıdaki devam bilgisi `localStorage` içinde dosya adı/boyutu/son değiştirilme zamanı anahtarıyla tutulur. Oturum sunucu tarafında sahiplik ve durum kontrolleriyle korunur.
- Oturumun 24 saat sonra sona ermesi beklenir; temizleme görevi `orvionart:prune-video-uploads` komutuyla çalışır ve günlük 03:35'e planlanmıştır. Sunucunun Laravel scheduler/cron çalıştırması gerekir.
- Bu ortamda Composer ve `vendor/autoload.php` yoksa Laravel Feature testleri çalıştırılamaz. Worker'ın gerçek sunucuda `QUEUE_CONNECTION=database` ile çalıştırılması ve FFprobe erişimi ayrıca doğrulanmalıdır. Gerçek Plesk, PHP upload sınırları, disk alanı ve 512 MiB uçtan uca video aktarımı ayrıca staging kabul testinde doğrulanmalıdır.

## Kabul kontrol listesi

- [ ] Migration staging veritabanına uygulanır.
- [ ] 4 MiB'dan büyük video telefondan yüklenir.
- [ ] Ağ kesintisinden sonra aynı dosya yeniden seçilerek eksik parçalar sürdürülür.
- [ ] Eksik parça ile tamamla çağrısı reddedilir.
- [ ] Farklı yönetici başka kullanıcının oturumunu göremez veya değiştiremez.
- [ ] İstemci SHA-256 değeri yanlışsa parça kaydedilmez.
- [ ] Sahte MIME/yanlış içerik birleştirme sırasında reddedilir.
- [ ] Tamamlama yanıtı ağda kaybolduğunda yeniden deneme ikinci medya kaydı üretmez.
- [ ] Veritabanı commit'inden sonraki geçici dosya temizleme hatası tamamlanmış medyayı silmez.
- [ ] Başarılı dosya medya kütüphanesinde doğru boyut ve MIME ile görünür.
- [ ] Süresi dolan oturumun geçici dosyaları scheduler tarafından temizlenir.
- [ ] PHP `upload_max_filesize`, `post_max_size`, `max_execution_time`, `memory_limit` ve disk alanı gerçek sunucuda kontrol edilir.
