# Staging yedek manifesti şeması — 0.10.70

## Yeni yedek biçimi

`scripts/backup-staging.sh` yeni yedeklerde şu metadata alanlarını üretir:

- `application=ORVIONART`
- `backup_format=2`
- `version` ve `created_at_utc`
- `database_engine`, `database_host`, `database_name`
- `contains_env_file=no`
- `contains_vendor_directory=no`
- `restore_policy=staging_only_without_explicit_approval`

Yedek doğrulayıcı tüm alanların varlığını, tekrar veya bilinmeyen alan bulunmamasını ve alanların biçimini kontrol eder. Metadata değerleri konsola yazdırılmaz.

## Geriye dönük uyumluluk

Eski yedeklerin manifestleri `backup_format` içermeyebilir. Doğrulayıcı eski 8 alanlı manifesti ve daha önce kullanılan `contains_vendor_directory=no` alanlı 9 alanlı biçimi kabul eder. Yeni format 2 ise hem `backup_format=2` hem `contains_vendor_directory=no` zorunludur.

## Güvenlik sınırları

- SHA-256 kayıt listesi beklenen dosya adlarıyla sınırlandırılır.
- Medya arşivi yolları, arşiv türleri ve symlink/hardlink riskleri denetlenir.
- Doğrulama sırasında arşiv açılmaz ve SQL çalıştırılmaz.
- Bu kontrol veritabanı geri yüklemesi, şema uyumu veya uygulama kabul testi değildir.
- Gerçek geri yükleme yalnızca ayrı ve izole staging ortamında, açık operatör onayıyla yapılmalıdır.

## Regresyon

`bash scripts/test-verify-backup.sh` geçerli eski/yeni manifestleri ve bozuk alan, bilinmeyen alan, tekrar eden alan, hatalı zaman damgası/motor, vendor beyanı, checksum, traversal ve symlink vakalarını geçici dizinlerde test eder. Canlı DB veya sunucu verisi kullanmaz.
