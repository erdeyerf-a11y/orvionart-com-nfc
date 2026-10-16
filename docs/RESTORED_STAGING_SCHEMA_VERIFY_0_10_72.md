# Geri yükleme sonrası staging şema doğrulaması — 0.10.72

## Amaç

`verify-restored-staging.sh`, operatör tarafından ayrı staging ortamına geri yükleme yapıldıktan sonra veritabanı kimliği ve temel uygulama şemasının bulunabilirliğini salt-okunur sorgularla kontrol eder.

## Çalıştırma

Parolayı komut argümanına yazmayın. Ortam değişkenlerini güvenli bir shell oturumunda ayarlayın:

```bash
export RESTORE_TARGET_DB='orvionart_staging_ornek'
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export RESTORE_DB_USER='staging_kullanici'
read -rsp 'Staging DB parolası: ' RESTORE_DB_PASSWORD; echo
export RESTORE_DB_PASSWORD
export RESTORE_DB_HOST='127.0.0.1'
export RESTORE_DB_PORT='3306'
bash scripts/verify-restored-staging.sh
unset RESTORE_DB_PASSWORD
```

## Kontroller

- Hedef DB adı `orvionart_staging_` önekiyle başlamalı ve ikinci onayla aynı olmalıdır.
- DB kimliği, sunucu host adı/portu ve `read_only` durumu okunur.
- DB'de tablo bulunması gerekir.
- `migrations`, `users`, `experiences`, `media_assets`, `qr_links`, `site_pages`, `site_settings` tabloları aranır.
- `migrations` tablosunda en az bir kayıt bulunmalıdır.
- Bağlantı bilgileri 0600 izinli geçici MySQL istemci dosyasına yazılır; dosya işlem sonunda temizlenir.

## Sınırlar

Bu komut SQL import etmez, migration çalıştırmaz ve veriyi değiştirmez. Temel tablo adları ve migration kaydı, uygulamanın tamamen çalıştığını kanıtlamaz. HTTP smoke testi, medya dosyası bütünlüğü, gerçek kullanıcı akışı ve Plesk/MariaDB kabul testleri ayrıca gereklidir. Canlı veritabanında çalıştırmayın; sadece ayrı staging DB kullanın.

## Regresyon

```bash
bash scripts/test-verify-restored-staging.sh
```

Test, gerçek DB'ye bağlanmaz; geçici bir `mysql` stub kullanır. Bu nedenle gerçek DB kabul testi değildir.
