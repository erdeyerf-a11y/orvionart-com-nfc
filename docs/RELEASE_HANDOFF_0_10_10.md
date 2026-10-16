# ORVIONART 0.10.10 — Teslimat kontrol kapsamı (önceki sürüm)

## Bu sürümde

- Paket kökündeki README tamamlandı: gereksinimler, güvenli kontrol komutları, salt okunur denetimler ve test sınırları açıklandı.
- `release-gate.sh` daha önce var olan entegre platform ve Demo Engine denetim komutlarının kaynak kodu ile Feature test dosyalarının pakette bulunduğunu doğrulayacak şekilde genişletildi.
- Release gate, temel denetim komutları eksikse teslimat kontrolünü durdurur.
- Sürüm numarası 0.10.10 olarak güncellendi; kaynak manifesti yeniden oluşturuldu.

## Yeni paket bütünlük kontrolleri

- `orvionart:inspect-platform` komutu ve `PlatformIntegrityCommandTest.php` test kaynağı
- `orvionart:inspect-demo` komutu ve `DemoInspectionCommandTest.php` test kaynağı
- `orvionart:inspect-site-builder` komutu ve `SiteBuilderIntegrityCommandTest.php` test kaynağı
- README'nin boş olmaması
- Mevcut manifestin doğrulanması

Bunlar kaynak dosyalarının teslimatta bulunmasını denetler; testlerin Laravel içinde başarıyla çalıştığını kanıtlamaz.

## Kontrol komutları

```bash
bash scripts/release-gate.sh
bash scripts/release-gate.sh --strict
php scripts/release-manifest.php verify
```

`--strict`, Composer ve `vendor/autoload.php` ile Laravel test ortamı yoksa başarısız olmalıdır. Normal mod, çalışma zamanı eksiklerini uyarı olarak raporlar.

## Bu ortamda tamamlanmayanlar

- Composer bağımlılık kurulumu ve `composer validate`
- PHPUnit/Laravel Feature testlerinin çalıştırılması
- Gerçek DB üzerinde migrate/rollback ve yükseltme testleri
- Plesk staging kurulum ve HTTPS testleri
- Yedekten geri yükleme tatbikatı
- Mobil/tarayıcı erişilebilirlik ve performans kabulü
- Canlıya kontrollü dağıtım

## Güvenlik ve iş kuralı notları

Ödeme entegrasyonu kapalı kalır. Kullanım hakları ticari kuralları kesinleşmeden otomatik yetkilendirme veya tahsilata dönüştürülmez. Denetim komutları salt okunur kalır ve otomatik düzeltme yapmaz.
