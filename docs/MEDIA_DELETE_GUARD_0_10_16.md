# ORVIONART 0.10.16 — Medya silme referans koruması

## Amaç

Medya kütüphanesindeki bir `MediaAsset` dosyasının, Demo Engine içeriği tarafından aynı depolama diski ve aynı yol ile kullanılıyorsa silinmesini engeller. Demo Engine içerikleri bir `MediaAsset` kimliği yerine `media_disk` + `media_path` sakladığından iki alan birlikte karşılaştırılır.

## Davranış

- `MediaAsset::isReferencedByDemoContent()` ilgili Demo Engine referansını kontrol eder.
- `MediaAsset::canBeDeletedSafely()` artık deneyim bağı, Site Builder referansı ve Demo Engine disk/yol referansının üçünü de kontrol eder.
- Silme kontrolü mevcut yönetici silme akışındaki veritabanı işlemi içinde tekrar çağrılır.
- Bu değişiklik otomatik medya temizliği eklemez; sadece kullanılan dosyanın silinmesini önler.
- Disk adı farklıysa yalnızca yolun aynı olması referans sayılmaz.

## Test kaynakları

`tests/Feature/MediaAssetDeletionSafetyTest.php` üç senaryo içerir: aynı disk/yol ile Demo Engine tarafından kullanılan dosya korunur; farklı disk eşleşme sayılmaz; referanssız kütüphane dosyası silinebilir durumda kalır.

Bu Laravel Feature testleri bu paketin oluşturulduğu ortamda çalıştırılmamıştır; Composer/Laravel bağımlılıkları ve veritabanı çalışma ortamı mevcut değildir. Gerçek silme endpoint'i, dosya sistemi ve eşzamanlı işlem davranışı staging kabul testinde doğrulanmalıdır.
