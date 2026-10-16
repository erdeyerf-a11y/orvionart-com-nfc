# ORVIONART 0.10.13 — Teslimat notu

## Sürüm kapsamı
- Demo Engine oturumlarının kronolojik tutarlılığı için `orvionart:inspect-demo-lifecycle` salt okunur denetim komutu eklendi.
- Denetim oturum bitişi/başlangıcı, son görülme ve sona erme zamanları, oturum aralığı dışındaki günlük atamalar ve oturumdan önce kaydedilen olayları raporlar.
- JSON raporunda sayılar ve sınırlı örnek kayıt ID'leri bulunur; cihaz token'ları bulunmaz.
- Üç Laravel Feature test senaryosu eklendi.

## Gerçekleştirilen kontroller
`docs/BUILD_CHECKS.md` içindeki sonuçlar bu paket hazırlanırken çalıştırılan statik kontrolleri kaydeder. PHP/Shell sözdizimi, manifest, migration statik denetimi ve iki mevcut shell regresyon testi başarılıdır. Release gate: PASS=20, WARN=2, FAIL=0.

## Açık sınırlar
- Composer ve Laravel `vendor/` bağımlılıkları bu ortamda bulunmadığından Laravel Feature testleri çalıştırılmadı.
- Gerçek veritabanı migration/rollback, staging kurulumu, HTTP/tarayıcı/mobil kabul testleri ve yedekten geri yükleme tatbikatı yapılmadı.
- Gerçek sunucuya dağıtım yapılmadı ve Manus'a teslim edilmedi.
- Ödeme varsayılan olarak kapalıdır. Kullanım haklarıyla ilgili karara bağlanmamış ticari kurallar değiştirilmemiştir.

## Komut
Bağımlılıklar kurulu bir test ortamında: `php artisan orvionart:inspect-demo-lifecycle --json`

Bu komut salt okunurdur; uyarıları otomatik düzeltmez veya kayıt silmez.
