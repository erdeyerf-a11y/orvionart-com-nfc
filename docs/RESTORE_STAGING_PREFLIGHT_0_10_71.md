# Geri yükleme ön kontrolü — 0.10.71

## Amaç ve sınırlar

`scripts/restore-staging-preflight.sh` bir yedek dosyasını doğrular ve yedekten bağımsız, boş bir staging veritabanına salt-okunur bağlantı testi yapar. **SQL içe aktarmaz, medya arşivini açmaz, dosya veya veritabanı oluşturmaz/değiştirmez.** Gerçek geri yükleme tatbikatı değildir.

## Ön koşullar

- Yedek `scripts/verify-backup.sh` tarafından kabul edilmelidir.
- MySQL/MariaDB istemcisi (`mysql`) bulunmalıdır.
- Operatör tarafından önceden oluşturulmuş, boş bir staging veritabanı ve sadece gerekli erişime sahip bir kullanıcı olmalıdır.
- Hedef DB adı `orvionart_staging_` ile başlamalıdır; yedekteki kaynak DB adıyla aynı olamaz.
- `RESTORE_CONFIRM_TARGET_DB`, `RESTORE_TARGET_DB` ile birebir aynı yazılmalıdır.
- `RESTORE_STAGING_ROOT` mutlak bir yol olmalı, uygulama ve yedek dizininin dışında bulunmalı; mevcutsa boş bir dizin olmalıdır. Üst dizin mevcut ve yazılabilir olmalıdır.

## Çalıştırma

Parolayı komut satırına yazmayın. Örneğin etkileşimli shell'de parolayı gizli istemle alıp ortam değişkeni olarak dışa aktarın:

```bash
export RESTORE_TARGET_DB='orvionart_staging_restore_20261016'
export RESTORE_CONFIRM_TARGET_DB='orvionart_staging_restore_20261016'
export RESTORE_STAGING_ROOT='/var/www/vhosts/EXAMPLE/staging-restore-20261016'
export RESTORE_DB_USER='staging_restore_user'
read -rsp 'Staging DB parolası: ' RESTORE_DB_PASSWORD; echo
export RESTORE_DB_PASSWORD
export RESTORE_DB_HOST='127.0.0.1'
export RESTORE_DB_PORT='3306'
bash scripts/restore-staging-preflight.sh /var/backups/orvionart-staging/orvionart-staging-TIMESTAMP
unset RESTORE_DB_PASSWORD
```

Hedef veritabanı adı, staging dizini ve DB hesabı örnek yer tutuculardır; gerçek sunucu değerlerini güvenli şekilde belirleyin. Parolayı shell geçmişine veya yazışmaya koymayın. Betik geçici bir istemci yapılandırma dosyasını `600` izinle oluşturur ve çıkışta siler.

## Kontrol edilenler

1. Yedek checksum, manifest ve medya arşivi yolları.
2. Staging DB adı ve ikinci onay.
3. Kaynak/hedef DB ayrımı.
4. Uygulama/backup dizinleri dışında ve boş staging hedef yolu.
5. MySQL/MariaDB bağlantısı, seçili DB kimliği ve tablo sayısının sıfır olması.

## Test ve kabul sınırı

`bash scripts/test-restore-staging-preflight.sh` geçici yedek ve sahte `mysql` istemcisi kullanarak olumlu ve olumsuz kontrolleri çalıştırır; gerçek DB'ye bağlanmaz. Gerçek restore için ayrıca onaylı bakım penceresi, ayrı staging DB/dizin, doğrulanmış yedek, SQL/media restore prosedürü ve uygulama kabul testleri gerekir. Bu betik canlıya geri yükleme için kullanılmamalıdır.
