# ORVIONART — Yedekleme ve geri yükleme işletim yönergesi (0.10.1)

## Kapsam ve güvenlik

Bu paket, operatörün açıkça çalıştırdığı iki betik içerir:

- `scripts/backup-staging.sh`: MySQL/MariaDB dökümü ile `storage/app/private` ve `storage/app/public` medya dizinlerini yedekler.
- `scripts/verify-backup.sh`: dosyaların SHA-256 toplamlarını ve medya arşivinin okunabilirliğini kontrol eder.

Bunlar web isteğiyle veya Laravel Scheduler üzerinden otomatik çalıştırılmaz. Canlı sunucuya otomatik dağıtım, otomatik geri yükleme veya canlı veriye yazma yapmazlar. Yedekleme, geri yükleme tatbikatı yerine geçmez.

**Yedek dizini uygulama dizininin dışında ve web üzerinden erişilemeyen bir yerde olmalıdır.** Betik `BACKUP_ROOT` uygulama dizininin içindeyse işlemi durdurur. Yedekler veritabanı ve özel medya içerebileceğinden erişimi sınırlayın, uygun şifreleme/harici kopya politikasını işletin ve saklama süresini belirleyin.

## Ön koşullar

- Betikleri uygulama kök dizinindeki kaynak paketinden çalıştırın.
- `mariadb-dump` veya `mysqldump`, `tar`, `gzip`, `realpath` ve SHA-256 aracı mevcut olmalıdır.
- MySQL/MariaDB için yedekleme yetkisi olan kullanıcı kullanın. Gereksiz yönetici/root hesabı kullanmayın.
- `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` ortam değişkenlerini güvenli bir oturum/secret manager üzerinden tanımlayın. Parolayı komut satırı argümanına yazmayın; terminal geçmişine koymayın.
- `BACKUP_ROOT` mutlak yol olmalı; web kökü ve uygulama kökü dışında olmalıdır.

## Hazırlık alanı için yedek alma

Örnek yol yalnızca yer tutucudur; gerçek dizini sunucu yöneticisi belirlemelidir.

```bash
export BACKUP_ROOT=/var/backups/orvionart-staging
export DB_HOST=127.0.0.1
export DB_PORT=3306
export DB_DATABASE=orvionart_staging
export DB_USERNAME=orvionart_backup
# DB_PASSWORD değerini güvenli secret manager/etkileşimli güvenli yöntemle ortam değişkeni olarak sağlayın.
./scripts/backup-staging.sh
```

Betik dosyaları özel izinli geçici dizinde hazırlar, SQL ve medya dosyalarının SHA-256 özetlerini üretir ve yedeği tek bir yeni klasöre taşır. `.env`, `vendor/`, loglar ve framework önbelleği yedeğe dahil edilmez. `MANIFEST.txt` içinde parola bulunmaz.

Yedek alındıktan sonra doğrulayın:

```bash
./scripts/verify-backup.sh /var/backups/orvionart-staging/orvionart-staging-YYYYMMDDTHHMMSSZ
```

Bu doğrulama yalnızca dosya bütünlüğünü ve medya arşivinin okunabilirliğini denetler; SQL'in geri yüklenebildiğini kanıtlamaz.

## Geri yükleme tatbikatı — yalnızca ayrı staging ortamında

1. Hedef staging veritabanı adını ve staging uygulama yolunu ikinci bir kişiyle doğrulayın. Canlı veritabanını hedef olarak kullanmayın.
2. Hedef staging veritabanının mevcut durumunu gerekiyorsa ayrıca yedekleyin.
3. `verify-backup.sh` başarılı olmalı; manifestteki sürüm ve veritabanı adı incelenmelidir.
4. Veritabanını yalnızca staging'e geri yükleyin. Parolayı komut argümanına koymadan, güvenli kimlik bilgisi yöntemi kullanın. Örnek komutun veritabanı adı yer tutucudur:

```bash
mysql --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" -p "$STAGING_RESTORE_DATABASE" < database.sql
```

`-p` parola istemini açar; parolayı `-pPAROLA` biçiminde yazmayın. Komutu yedek klasöründeki `database.sql` dosyasına göre çalıştırın. Hedef veritabanının staging olduğu komut öncesinde tekrar doğrulanmalıdır.

5. Medya arşivini önce geçici bir inceleme dizinine çıkarıp içindeki `storage/app/private` ve `storage/app/public` yollarını doğrulayın. Ardından yalnızca staging uygulama köküne, uygun dosya sahibi/izinleri korunarak geri yükleyin. Doğrudan canlı dizine açmayın.
6. Staging `.env` dosyasını bu ortama özgü olarak oluşturun; canlı gizli anahtarları kopyalamayın. `APP_ENV=staging`, `APP_DEBUG=false`, HTTPS `APP_URL`, güvenli oturum çerezleri ve ödeme kapalı ayarını doğrulayın.
7. Geri yüklenen staging veritabanında migration durumunu inceleyin. Geri yükleme sonrasında migration çalıştırmak gerekiyorsa önce planı inceleyin; otomatik olarak migration veya veri silme yapmayın.
8. Yönetici girişi, deneyim sayfası, QR/NFC, medya, Site Builder, Demo Engine, loglar ve temel mobil akışları doğrulayın. Tatbikat tarihini, yedek kimliğini, sonuçları ve kalan hataları kaydedin.
9. Tatbikat başarılı değilse canlıya geçiş onayı verilmez. Gerekirse yalnızca staging'i temizleyip yeniden kurun.

## Bilinen sınırlar

- Bu betikler uzak/harici yedek kopyası, şifreleme, otomatik saklama/rotasyon veya geri yükleme otomasyonu sağlamaz.
- Yedek veritabanı ile medya dosyalarını aynı anlık görüntüde kilitlemez. Yüksek yazma trafiğinde uygulamayı kontrollü bakım/işlem duraklatma penceresine almak gerekebilir.
- `--single-transaction` InnoDB tabloları için tutarlı döküm sağlar; MyISAM veya dış sistemlerdeki dosya/veri tutarlılığı ayrıca değerlendirilmelidir.
- Yedek doğrulama betiği veritabanına bağlanmaz ve SQL'i çalıştırmaz.
- Bu yönerge gerçek sunucuda çalıştırılmış değildir; sunucuya özgü yollar ve izinler doğrulanmalıdır.
