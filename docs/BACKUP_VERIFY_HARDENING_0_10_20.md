# ORVIONART 0.10.20 — Yedek doğrulama sıkılaştırması

## Amaç

`scripts/verify-backup.sh` yedekleri geri yüklemeden önce doğrularken dosya özetleri, manifest ve medya arşivinin yapısını kontrol eder. Betik hiçbir arşivi çıkartmaz, veritabanına bağlanmaz ve veriyi değiştirmez.

## Eklenen kontroller

- `SHA256SUMS` yalnızca beklenen üç dosyayı (`database.sql`, `media-storage.tar.gz`, `MANIFEST.txt`) tam birer kez içerebilir. Böylece checksum dosyasındaki `../...` veya mutlak yol girdileriyle başka dosyaların okunması önlenir.
- Manifestte ORVIONART uygulama adı, biçimi geçerli bir sürüm, `contains_env_file=no` ve staging-only geri yükleme politikası aranır.
- Medya arşivi bozuksa doğrulama durur.
- Arşiv yolları mutlak yol, `..`/`.` segmenti, ters eğik çizgi veya `storage/app/private` ve `storage/app/public` dışındaki kökler içeremez.
- Arşivde yalnızca normal dosya ve dizin türleri kabul edilir. Sembolik bağ, hard link ve özel dosya türleri reddedilir; bu tür kayıtlar arşiv açılırken hedef dizin dışına yönlendirme riski oluşturabilir.
- SQL dosyasının boş olmadığı kontrol edilir; SQL çalıştırılmaz ve geri yüklenebilirliği iddia edilmez.

## Regresyon testi

```bash
bash scripts/test-verify-backup.sh
```

Test, geçerli bir yedek örneğinin kabul edildiğini; dış yol içeren checksum listesinin, arşivde dizin geçişinin, sembolik bağın ve `.env` içerdiğini iddia eden manifestin reddedildiğini doğrular. Testler geçici dizinlerde çalışır; gerçek yedeğe veya veritabanına erişmez.

## Sınırlar

- SHA-256 eşleşmesi arşivin güvenilir kaynaktan geldiğini kanıtlamaz; yalnızca dosyaların kontrol listesiyle aynı olduğunu gösterir.
- SQL sözdizimi, uygulama sürümü uyumu veya gerçek geri yükleme doğrulanmaz.
- Yedekler yalnızca izole staging ortamında, yollar ve hedef veritabanı ikinci kez doğrulanarak geri yüklenmelidir.
