# ORVIONART 0.10.32 — Demo Engine medya sunumu güvenliği

## Amaç

Demo medyası herkese açık statik dosya olarak sunulmaz; geçerli cihaz yolculuğu ve bugünkü atama kontrolü gerekir. Bu erişim kontrollerine ek olarak, veritabanındaki eski veya elle değiştirilmiş medya alanları da güvenli kabul edilmemelidir.

## Uygulanan kontroller

- Yalnızca `local` disk kabul edilir.
- Yol, `orvionart-demo/` altında göreli bir yol olmalı; boş segment, `.` / `..`, mutlak yol, Windows sürücü yolu ve ters eğik çizgi reddedilir.
- MIME türü JPEG, PNG, WebP, GIF, MP4, WebM ve OGG için açık izin listesiyle kontrol edilir.
- `realpath` sonucu `local` disk kökünün altında olmalı ve hedef normal bir dosya olmalıdır. Sembolik bağlantı yoluyla disk dışına çıkış reddedilir.
- Dosya açılmadan önce doğrulanır ve açılmış akış `finally` bloğunda kapatılır.
- `Content-Length` veritabanındaki eski `media_size` değerinden değil gerçek dosyadan hesaplanır.
- `nosniff`, özel/no-store önbellek politikası, `noindex` ve `no-referrer` başlıkları korunur.

## Regresyon testi kaynağı

`tests/Feature/DemoEngineTest.php` içinde aşağıdaki olumsuz durumlar için test bulunur:

1. `public` diskte tutulan eski medya kaydı.
2. `orvionart-demo/../.env` gibi dizin dışına çıkmaya çalışan yol.
3. `text/html` gibi izin verilmeyen MIME türü.

Üç durumun da `404` ile sonuçlanması beklenir.

## Doğrulama sınırı

PHP sözdizimi ve statik release gate bu ortamda çalıştırılabilir. Composer bağımlılıkları ve `vendor/autoload.php` yoksa Laravel Feature testi çalıştırılamaz. Gerçek staging dosya sistemi, sembolik bağlantı davranışı ve veritabanı kabul testleri ayrıca yapılmalıdır.
