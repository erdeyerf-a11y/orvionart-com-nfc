# Demo Engine içerik silme güvenliği — 0.10.31

## Değişiklik

- Demo içerik satırı silinmeden önce işlem içinde `lockForUpdate()` ile yeniden okunur.
- Günlük atama geçmişi kontrolü kilitli kayıt üzerinde yapılır; geçmişi olan içerik silinmez.
- İçerik medyası yalnızca `local` diskinde ve `orvionart-demo/` altında, yol geçişi içermeyen bir konumdaysa silinir.
- Beklenmeyen disk veya yol varsa içerik ve dosya korunarak HTTP 409 döndürülür.
- Veritabanı kaydı ve denetim günlüğü aynı transaction içindedir. Dosya, transaction başarılı olduktan sonra silinir.
- Yabancı anahtar kaynaklı eşzamanlı atama yarışı saptanırsa silme işlemi kullanıcıya hata olarak döndürülür; atama geçmişi korunur.
- Genel demo gösteriminde günlük atama kararı, oturum kilidi ve içerik kilitleriyle transaction içine alınır. Atama ile `content_view` olayı birlikte yazılır.

## Regresyon test kaynakları

`tests/Feature/DemoEngineTest.php` şu senaryoları kapsar:

- Atama geçmişi olan içerik silinemez ve medya dosyası korunur.
- Atama geçmişi olmayan içerik, yalnızca güvenli özel medya yoluyla silinir ve denetim kaydı yazılır.
- `public` gibi özel olmayan disk kullanan içerik silinmez.

## Doğrulama sınırı

Bu sürümde PHP statik sözdizimi ve proje release-gate kontrolleri çalıştırılabilir. Composer/Laravel çalışma zamanı yoksa Feature testleri çalıştırılmış sayılmaz. Gerçek MySQL/PostgreSQL eşzamanlılık testi ve sunucu kabul testi staging ortamında yapılmalıdır.
