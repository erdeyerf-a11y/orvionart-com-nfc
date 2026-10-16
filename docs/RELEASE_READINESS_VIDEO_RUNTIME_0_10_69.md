# Kurulum hazırlık denetimi: yükleme ve video işleme — 0.10.69

`php artisan orvionart:release-check` salt okunur hazırlık raporuna şu operasyonel kontroller eklendi:

- `upload_max_filesize` en az 5 MiB olmalı; 4 MiB video parçası için multipart başlıklarına pay bırakılır.
- `post_max_size` en az 6 MiB olmalı.
- `QUEUE_CONNECTION=sync` veya boş sürücü, arka plan işlerinin senkron çalışabileceği için uyarı üretir. Kuyruk sürücüsünün tanımlı olması worker'ın çalıştığını kanıtlamaz.
- Video dönüştürme etkinse FFmpeg ve FFprobe ikilileri, komut çalıştırmadan ve gerçek sunucu yollarını rapora yazmadan PATH/konfigürasyon üzerinden aranır.
- Video dönüştürme kapalıysa FFmpeg/FFprobe kontrolleri uygulanmaz.

Bu kontroller yalnızca rapor üretir; `.env`, PHP ayarları, dosyalar, kuyruk veya veritabanı değiştirilmez. Eksik operasyonel ayarlar `warn` olarak işaretlenir; `orvionart:release-check` sonucu `REVIEW_REQUIRED` olabilir. Uyarıların gerçek sunucuda giderilmesi ve worker'ın gerçek video ile denenmesi ayrıca gerekir.

Bu ortamda Composer/Laravel runtime ve Plesk kabul testleri çalıştırılmış sayılmaz.
