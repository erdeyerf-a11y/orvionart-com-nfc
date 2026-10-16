# Demo Engine medya erişim güvenliği — 0.9.7

## Amaç

Demo içeriklerinin medyası normal deneyim medyalarından ayrı kalır. Demo içerik ID'sini bilmek tek başına görsel/video dosyasına erişmek için yeterli olmamalıdır.

## Uygulanan kontroller

- Demo Engine aktif olmalı.
- İstenen demo içeriği aktif olmalı ve medya yolu bulunmalı.
- İstek, `orvionart_demo_device` HttpOnly çerezindeki rastgele 64 karakterlik belirteçle eşleşen bir demo oturumuna sahip olmalı.
- Oturum sona ermemiş olmalı.
- İçerik, bu oturum için bugünün `demo_daily_assignments` kaydında atanmış olmalı.
- Dosya depolamada bulunmalı ve kayıtlı MIME türü `image/*` veya `video/*` olmalı.
- Medya yanıtları `private, no-store`, `X-Robots-Tag: noindex...`, `Referrer-Policy: no-referrer` ve `X-Content-Type-Options: nosniff` başlıklarıyla döner. Genel güvenlik middleware’i, rota tarafından tanımlanmış daha sıkı Referrer-Policy değerini ezmez.
- Medya rotası IP tabanlı `throttle:60,1` sınırını kullanır.

Yetki doğrulamasında başarısız istekler, içeriğin varlığını açığa çıkarmamak için 404 döndürür.

## Demo yolculuğunu yeniden başlatma

Bir tarayıcıda yeni demo yolculuğu başlatıldığında eski belirteçle eşleşen oturum varsa sona erdirilir ve `journey_restarted` olayı kaydedilir. Yeni oturum ve `journey_started` olayı aynı veritabanı transaction'ında oluşturulur. Böylece aynı tarayıcı için her yeniden başlatmada önceki oturumların etkin kalması önlenir.

Yeni yolculuk, Demo Engine aktif ve en az bir aktif demo içeriği bulunduğunda oluşturulur. Yeni yolculuk başlatma ve olay kaydı transaction içinde tutulur.

## Test kapsamı

`tests/Feature/DemoEngineTest.php` içinde aşağıdaki test kaynakları bulunur:

- Oturum olmadan demo medyasına erişim reddedilir.
- Oturum ve bugünün içerik ataması varsa demo medyası sunulur.
- Bugün atanmamış başka içeriğin medyasına erişim reddedilir.
- Yolculuğu yeniden başlatmak önceki oturumu sona erdirir ve olay kaydı üretir.

Bu kaynak paketi hazırlanırken Composer ve `vendor/` bulunmadığından Laravel/PHPUnit testleri çalıştırılamamıştır. Bunlar çalıştırılmış test sonucu değildir; staging ortamında doğrulanmalıdır.
