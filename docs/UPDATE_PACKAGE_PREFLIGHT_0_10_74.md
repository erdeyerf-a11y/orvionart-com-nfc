# Kontrollü güncelleme paketi ön denetimi — 0.10.74

## Amaç

`update-package-preflight.sh`, kontrollü güncelleme/geri alma altyapısının güvenli ilk basamağıdır. Bir dağıtım ZIP'ini herhangi bir uygulama dizinine dokunmadan, geçici dizinde inceleyerek kabul/ret kararı üretir.

## Kullanım

```bash
bash scripts/update-package-preflight.sh /path/to/ORVIONART_STAGE0_1_0_10_XX.zip <beklenen-64-karakter-SHA256>
```

Beklenen SHA-256, paketin güvenilir teslimat kaynağından ayrıca alınmalıdır. ZIP'in yanındaki checksum dosyasını körlemesine doğruluk kaynağı saymak yeterli değildir; checksum'ın kendisinin de güvenilir kaynaktan gelmesi gerekir.

## Denetimler

- SHA-256 ve ZIP bütünlüğü.
- Boş paket, aşırı girdi sayısı, mutlak yol, `..`/`.` geçişi, ters eğik çizgi ve yinelenen arşiv yolları.
- `.env`, `.env.example` dışındaki ortam dosyaları, bağımlılık klasörleri ve runtime cache/log içerikleri.
- Sembolik bağlantıların reddi.
- Tek kök klasör, klasör adı–`VERSION` eşleşmesi, Laravel `composer.json`, `artisan` ve manifest dosyaları.
- Kaynak manifesti ve tüm PHP dosyalarının sözdizimi.

## Güvenlik sınırı

Bu araç **yalnızca ön denetimdir**. Dosya dağıtmaz, mevcut dosyaları değiştirmez, veritabanına bağlanmaz, migration çalıştırmaz, bakım modunu açmaz ve geri alma yapmaz. `PREFLIGHT_PASS`, canlıya çıkış onayı değildir. Gerçek deployment/rollback için ayrı staging uygulaması, doğrulanmış dosya+DB+medya yedeği, açık onay, değişiklik planı, migration geri dönüş değerlendirmesi ve işlem günlüğü gereklidir.

## Regresyon kontrolleri

```bash
bash scripts/test-update-package-preflight.sh
```

Test kapsamı: geçerli kümülatif ZIP, hatalı SHA, hatalı checksum biçimi, ZIP path traversal ve `.env` içeren paketin reddi. Testler gerçek Plesk/MariaDB üzerinde deployment testi yerine geçmez.
