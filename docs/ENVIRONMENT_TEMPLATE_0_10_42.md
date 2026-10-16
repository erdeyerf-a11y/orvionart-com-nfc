# ORVIONART ortam şablonu — 0.10.42

## Amaç

`.env.example`, dağıtım paketinde bulunması gereken örnek yapılandırmadır. Gerçek erişim parolaları, üretim anahtarları veya API sırları içermez. Gerçek `.env` dosyası paket dışında oluşturulmalı ve sürüm kontrolüne/teslimat ZIP'ine dahil edilmemelidir.

## Kurulum adımları

1. `.env.example` dosyasını `.env` olarak kopyalayın.
2. `APP_URL` değerini gerçek staging adresiyle değiştirin.
3. Staging veritabanı adı, kullanıcı adı ve parolasını güvenli biçimde girin.
4. `php artisan key:generate` ile benzersiz `APP_KEY` oluşturun.
5. HTTPS staging/production ortamlarında `SESSION_SECURE_COOKIE=true` bırakın. Bu değeri yalnızca HTTPS kullanmayan yerel geliştirme ortamında geçici olarak kapatın.
6. `APP_DEBUG=false` ve `ORVIONART_PAYMENTS_ENABLED=false` değerlerini koruyun.
7. `bash scripts/release-gate.sh` komutunu çalıştırın.

## Veritabanı ve çalışma zamanı sınırı

Mevcut `composer.json` `ext-pdo_mysql` gerektirir ve uygulama yapılandırması MySQL/MariaDB bağlantısı tanımlar. PostgreSQL'e geçiş bu şablonla yapılmış veya doğrulanmış değildir. Şablondaki `CACHE_STORE=file` ve `SESSION_DRIVER=file`, ilk kurulumda cache/session tablolarına bağımlılığı azaltır; dağıtım kararı staging testleriyle doğrulanmalıdır.

Bu belge gerçek sunucu kurulumunun yapıldığı anlamına gelmez. Composer doğrulaması, Laravel Feature testleri, migration/rollback, HTTPS, izinler ve Plesk kabul testleri ayrıca çalıştırılmalıdır.
