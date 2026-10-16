# Video dönüştürme ve küçük resim — 0.10.53

## Amaç

0.10.51–0.10.52 sürümlerindeki arka plan doğrulama kuyruğu, 0.10.53'te FFmpeg ile tarayıcıya daha uygun bir MP4 türevi ve JPEG küçük resim üretecek şekilde genişletildi. Orijinal yükleme `media_assets.path` konumunda korunur; türetilmiş çıktılar ayrı özel depolama yollarında tutulur.

## İş akışı

1. Kuyruk işi medya başına `WithoutOverlapping` kilidiyle çalışır; iş zaman aşımı 300 saniye, kilit son kullanımı 420 saniyedir.
2. Kaynak dosyanın özel `local` disk altında olduğu, yolun yönetilen `orvionart-media/` ağacında bulunduğu ve MIME değerinin medya kaydıyla uyuştuğu kontrol edilir.
3. FFprobe kaynak video akışını, codec'i, süreyi ve boyutları doğrular.
4. `ORVIONART_VIDEO_TRANSCODING_ENABLED=true` ise FFmpeg, video akışını H.264/yuv420p ve sesi AAC ile MP4'e dönüştürür. Çıktı FFprobe ile tekrar incelenir; video codec'i `h264` olmalıdır.
5. Dönüştürülmüş dosyadan bir saniye civarında JPEG küçük resim çıkarılır. Bu noktada kare yoksa başlangıç karesinde bir kez daha denenir.
6. Türetilmiş dosyalar özel alana taşınır. Veritabanı `processing_status=ready`, `processed_path` ve `thumbnail_path` alanlarını ancak doğrulama başarıyla tamamlandığında yayınlar.
7. Hata durumunda orijinal kaynak korunur, geçici ve yayımlanmamış türetilmiş dosyalar temizlenmeye çalışılır; yöneticiye güvenli ve genel bir hata gösterilir.

## Depolama ve sunum

- Orijinal dosya: `orvionart-media/...` altında `path`.
- MP4 türevi: `orvionart-media/processed/{media-id}/...mp4`.
- JPEG küçük resim: `orvionart-media/thumbnails/{media-id}/...jpg`.
- `processed_path` varsa kamu ve yönetici video önizlemesi MP4 türevini `video/mp4` MIME ile sunar. Alan boş olan eski medya kayıtları önceki yol/MIME davranışını korur.
- Yönetici küçük resmi yalnızca kimliği doğrulanmış admin rotası üzerinden alabilir. Kamu küçük resmi ise yalnızca yayımlanmış bir deneyim veya Site Builder sayfası bu videoya referans verdiğinde sunulur; taslak/pasif içerik üzerinden erişilemez. Deneyim oynatıcısı MP4 türevi için `video/mp4` MIME türünü kullanır.
- Medya silme işlemi orijinal dosyayı mevcut karantina akışıyla işler; türetilmiş dosyaları veritabanı silme işlemi tamamlandıktan sonra temizler. Temizlik hataları loglanır.

## Yapılandırma

`.env.example` içinde güvenli başlangıç ayarları:

```dotenv
ORVIONART_FFPROBE_BINARY=ffprobe
ORVIONART_FFMPEG_BINARY=ffmpeg
ORVIONART_VIDEO_TRANSCODING_ENABLED=true
ORVIONART_FFPROBE_TIMEOUT_SECONDS=90
ORVIONART_FFMPEG_TIMEOUT_SECONDS=240
QUEUE_CONNECTION=database
```

FFmpeg ve FFprobe, worker kullanıcısı tarafından çalıştırılabilir olmalıdır. Plesk worker zaman aşımı uygulama kuyruğunun `--timeout=300` değerinden daha uzun olmalı; `retry_after`/iş görünürlük süresi de worker zaman aşımından büyük seçilmelidir. Paylaşımlı hostingte FFmpeg yoksa transcode etkin kalırken videolar hazır durumuna geçmez; kaynak dosyalar korunur ve kurulum tamamlanınca yönetici yeniden deneyebilir. Bilerek yalnızca metadata doğrulaması isteniyorsa `ORVIONART_VIDEO_TRANSCODING_ENABLED=false` kullanılabilir; küçük resim yine FFmpeg gerektirir.

## Test sınırı

Bu sürümde PHP sözdizimi, shell sözdizimi, migration statik denetimi, manifest, ZIP bütünlüğü ve Release Gate kontrolleri çalıştırıldı. Ayrıca yerel FFmpeg/FFprobe ile sentetik 2 saniyelik örnek üzerinde transcode/probe/thumbnail CLI smoke testi yapıldı. Composer/vendor bulunmadığı sürece Laravel Feature testlerinin çalıştığı iddia edilmez. Laravel job entegrasyonu üzerinde gerçek yüklenmiş dosya, büyük dosya kaynak tüketimi, Plesk worker/queue, veritabanı migration/rollback, tarayıcı video seek/Range ve mobil cihaz testleri bu yerel sürüm oluşturma adımında yapılmış sayılmaz.
