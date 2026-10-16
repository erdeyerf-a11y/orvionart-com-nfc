# ORVIONART 0.10.2 — Sürüm manifesti ve teslimat doğrulaması

## Amaç

Bu sürüm, teslim edilen kaynak ağacının yanlışlıkla değişmediğini veya ZIP paketine beklenmeyen bir dosya eklenmediğini kontrol etmek için SHA-256 tabanlı bir manifest içerir.

## Komutlar

Kaynak dosyaları son hâline geldikten sonra manifest oluşturun:

```bash
php scripts/release-manifest.php create
```

Daha sonra dosyaların manifestle aynı olduğunu kontrol edin:

```bash
php scripts/release-manifest.php verify
```

`verify` dosyaları değiştirmez. Bir kaynak dosyası değişmiş, silinmiş veya beklenmeyen bir dosya eklenmişse sıfır dışı çıkış kodu döndürür.

## Manifest kapsamı

`RELEASE_MANIFEST.json`, kaynak dosyalarının göreli yolunu, bayt cinsinden boyutunu ve SHA-256 değerini kaydeder. Şunlar kapsam dışındadır:

- Gerçek `.env` ve `.env.*` gizli yapılandırmaları (`.env.example` kapsam içindedir)
- `vendor/`, `node_modules/` ve `.git/`
- Çalışma zamanı verileri, loglar, cache, oturumlar ve yüklenen dosyalar
- `docs/archive/` içindeki tarihsel ZIP arşivleri ve diğer ZIP dosyaları
- Manifest dosyasının kendisi

Tarihsel teslimat ZIP'leri ayrı teslimatlar olarak saklanır; her yeni ZIP'in içine önceki ZIP'leri tekrar gömmek paket boyutunu katlayarak büyüttüğü için bu sürümde durduruldu. Güncel kaynak ağacı önceki aşamalarda biriktirilen kodu ve belgeleri içerir; geçmiş teslimat dosyaları ayrı bağlantılarıyla korunur.

## ZIP teslimatı

Manifest doğrulandıktan sonra ZIP oluşturun ve ayrıca arşiv bütünlüğünü kontrol edin:

```bash
php scripts/release-manifest.php verify
zip -qr ../ORVIONART_STAGE0_1_0_10_2.zip . -x 'docs/archive/*.zip' '*.zip'
unzip -t ../ORVIONART_STAGE0_1_0_10_2.zip
```

ZIP dosyasının SHA-256 değeri, ZIP oluşturulduktan sonra ayrı teslimat kaydında tutulabilir. ZIP kendi içine kendi hash değerini yazamaz.

## Sınırlar

Bu araç kaynak dosyası bütünlüğünü denetler; Composer bağımlılıklarını kurmaz, `composer.lock` üretmez, PHP/Laravel uygulamasını başlatmaz, migration çalıştırmaz, veritabanı test etmez ve sunucuya bağlanmaz. Manifest doğrulamasının başarılı olması canlıya çıkış onayı değildir.
