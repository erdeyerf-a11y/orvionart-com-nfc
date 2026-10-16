# ORVIONART 0.10.57 — Kontrollü Plesk staging kurulum kontrol listesi

## Kurulumun amacı ve sınırlar
Bu paket kaynak kod teslimatıdır; `vendor/`, gerçek `.env`, veritabanı parolaları ve üretim anahtarları ZIP içinde bulunmaz. Kurulumu yapacak kişi Composer bağımlılıklarını hedef sunucuda kurmalı, yeni ve ayrı bir staging veritabanı kullanmalı ve ilk Süper Admin hesabını etkileşimli olarak oluşturmalıdır. Bu dosya gerçek sunucuya erişildiği veya kurulumun tamamlandığı anlamına gelmez.

## Kurulum öncesi
- [ ] Confirm dedicated staging hostname/app directory, not live document root.
- [ ] Confirm backup/restore route even if live root is currently empty.
- [ ] Kullanıcının bildirdiği Plesk PHP 8.3.34 ayarını doğrula; Composer 2.x, gerekli PHP eklentileri, CLI ve PDO MySQL (`pdo_mysql`) gerçekten mevcut mu kontrol et.
- [ ] Gerçek MySQL/MariaDB sürümünü doğrula ve en az yetkili yeni bir boş staging veritabanı/kullanıcısı oluştur. Mevcut kaynak paketi PDO MySQL/MySQL-MariaDB hedeflidir; PostgreSQL olarak yapılandırma yapma.
- [ ] Set document root to this project's `public/` directory only.
- [ ] Enable HTTPS, Plesk password protection, and noindex.
- [ ] If Plesk is behind a reverse proxy, verify client-IP handling before configuring trusted proxies or IP-based throttling; never trust arbitrary forwarded headers.

## Kurulum
- [ ] Copy `.env.example` to `.env` outside public web access; never commit real `.env`.
- [ ] Kaynak kökünde `composer install --no-dev --prefer-dist --optimize-autoloader` çalıştır. `composer.lock` kaynak paketinde yoksa bağımlılık sürümleri henüz sabitlenmemiş demektir; kurulumdan sonra lock dosyasını teslimat kaydına dahil et ve aynı bağımlılık setiyle test et.
- [ ] Generate unique app key; set DB credentials, staging URL, `APP_ENV=staging`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`.
- [ ] Önce `php scripts/preflight.php --check-db` çalıştır. Hedefin yeni ve doğru staging veritabanı olduğu bağımsız şekilde doğrulandıktan sonra `php artisan migrate --force` çalıştır. Gerçek/canlı veritabanında `migrate:fresh` kullanma.
- [ ] `php artisan orvionart:create-super-admin` komutunu etkileşimli çalıştır; parolayı komut argümanına, dosyaya veya yazışmaya koyma.
- [ ] Set safe ownership/permissions; writable only where Laravel requires it (`storage/`, `bootstrap/cache/`).
- [ ] `.env` doğrulandıktan sonra `php artisan config:clear`, `php artisan route:clear`, ardından uygun biçimde `php artisan config:cache` ve `php artisan route:cache` çalıştır.

## İlk açılış kontrolleri
- [ ] `/health` DB bağlantısı başarılıysa `200`, değilse sırları göstermeden `503` döndürür.
- [ ] `/admin/login` loads over HTTPS.
- [ ] Guest access to `/admin` redirects to login.
- [ ] Correct active super-admin credentials open `/admin`.
- [ ] Incorrect credentials are rejected and throttled.
- [ ] Inactive or non-super-admin accounts cannot access `/admin`.
- [ ] Session ID changes after successful login; CSRF token is required on state-changing forms.
- [ ] Logout invalidates session.
- [ ] Audit log records authentication events without recording passwords.
- [ ] `APP_DEBUG=false`; `.env`, `vendor`, `app`, `config`, and `storage` cannot be downloaded over HTTP.
- [ ] Kurulum URL’sini, PHP/Composer sürümünü, migration listesini, test çıktısını ve engellenen kontrolleri kaydet. Test edilmemiş adımı başarılı olarak işaretleme.

## Geri alma
Disable access to staging host, preserve logs, restore staging directory snapshot and staging DB backup if necessary. Do not delete or modify unrelated live files/databases. Do not use `migrate:fresh` on a real server.


## Süper Admin kullanım akışı
1. `/admin/login` üzerinden oturum aç.
2. Yönetim ana ekranındaki **Yeni içerik oluştur** düğmesini kullan.
3. İçerik ekranındaki bağlantıdan fotoğraf/video yükle; deneyim seçimi otomatik gelir.
4. İçeriği yayımla ve aynı ekrandan QR/NFC bağlantısı oluştur.
5. QR dosyasını indir ve gerçek HTTPS adresini telefonla okut. NFC etiketi fiziksel olarak yazılacaksa aynı kalıcı URL bir NFC yazma uygulamasıyla etikete yazılır.
6. Gerçek sunucuda medya yükleme, QR tarama, NFC yazma, mobil görünüm ve geri yükleme testleri geçmeden “kurulum tamamlandı” deme.

## Video işleme notu
Büyük video yükleme/işleme için Plesk üzerinde PHP upload sınırları, disk alanı, FFmpeg/FFprobe ve `queue:work` worker ihtiyacı ayrıca doğrulanmalıdır. Worker/FFmpeg yoksa video özelliğini çalışıyor diye kabul etme; fotoğraf ve metin + QR akışı için ayrı kabul sonucu kaydet.
