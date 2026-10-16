# Medya referans denetimi — 0.10.33

`php artisan orvionart:inspect-media-references --json` salt okunur biçimde `MediaAsset` ve `DemoContent` kayıtlarının `local` disk referanslarını inceler.

Bu sürüm, önceki yol biçimi, yönetilen klasör kapsamı ve dosya varlığı kontrollerine ek olarak dosya sistemindeki gerçek hedefi `realpath` ile çözümler. Yönetilen klasör altında görünen bir yol özel disk kökü dışına sembolik bağlantı ile çıkıyorsa veya normal bir dosya yerine dizin/başka hedef gösteriyorsa `unsafe_filesystem_target` olarak raporlanır. JSON çıktısındaki `counts.unsafe_filesystem_targets` alanı bu kayıtların sayısını gösterir.

Komut dosya veya veritabanı kaydını değiştirmez, taşımaz veya silmez. Sembolik bağlantı regresyon testi `tests/Feature/MediaReferenceInspectionTest.php` içine eklenmiştir. Bu ortamda Composer/Laravel runtime bulunmadığı için Feature test çalıştırılmamıştır; test kaynağı statik olarak kontrol edilmiştir.

Bu denetim anlık bir gözlemdir; dosya sistemi denetiminden sonra hedefin değişmesini (TOCTOU) önlemez ve gerçek staging/kabul testinin yerini tutmaz.
