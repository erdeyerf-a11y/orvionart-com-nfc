# Migration Statik Denetimi — 0.10.6

## Amaç

Migration kaynak dosyalarında kolayca yakalanabilen yapısal sorunları, Laravel bağımlılıkları veya veritabanı bağlantısı olmadan tespit etmek.

## Komutlar

```bash
php scripts/audit-migrations.php
php scripts/audit-migrations.php --json
bash scripts/test-migration-audit.sh
```

İsteğe bağlı `--root=/proje/koku` seçeneği, yalnızca statik denetim yapılacak kökü seçer. Bu özellikle test fixture'ları içindir; gerçek projede doğru kökü verdiğinizden emin olun.

## Kontroller

- Laravel migration dosya adlandırma biçimi.
- Aynı zaman damgasının birden fazla migration tarafından kullanılması.
- `up()` ve `down()` metotlarının bulunması.
- Migration sınıfı yapısının tanınabilir olması.
- `up()` içinde bazı yıkıcı ham SQL kalıpları görülürse `REVIEW` uyarısı.

## Sınırlar

- Migration kodu çalıştırılmaz; SQL şeması oluşturulmaz.
- `down()` metodunun gerçekten veriyi/şemayı doğru geri çevirdiği kanıtlanmaz.
- Foreign key sırası, DB motoru uyumluluğu, mevcut veriye etkiler ve uzun süren kilitler doğrulanmaz.
- `REVIEW` uyarısı otomatik olarak hata değildir; bir geliştiricinin staging planı ve veri etkisini incelemesi gerekir.
- `STATIC_CHECKS_PASS_RUNTIME_PENDING` canlıya çıkış izni değildir.

Gerçek kabul için önce atılabilir bir staging veritabanında migration'ları çalıştırın, rollback davranışını ve veri kaybı riskini kontrol edin, ardından geri yükleme tatbikatını kaydedin. Canlı veritabanında deneme yapılmamalıdır.
