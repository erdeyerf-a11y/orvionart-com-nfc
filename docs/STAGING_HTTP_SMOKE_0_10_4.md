# ORVIONART 0.10.4 — Staging HTTP smoke test

## Amaç

`scripts/smoke-staging.sh` gerçek staging URL'sine yalnızca HTTP GET istekleri göndererek temel erişim ve güvenlik başlıklarını hızlıca kontrol eder. Giriş yapmaz, form göndermez, veri oluşturmaz/silmez, migration çalıştırmaz ve canlıya dağıtım yapmaz.

## Çalıştırma

```bash
bash scripts/smoke-staging.sh https://staging.orvionart.com
```

Plesk HTTP Basic parola koruması etkinse kullanıcı adı/parolayı komut satırına yazmayın. İzinleri `600` olan bir netrc dosyası oluşturup şu biçimde çalıştırabilirsiniz:

```bash
chmod 600 /gizli/yol/staging.netrc
bash scripts/smoke-staging.sh https://staging.orvionart.com --netrc-file /gizli/yol/staging.netrc
```

Netrc dosyasında `machine staging.orvionart.com login KULLANICI password PAROLA` satırı bulunmalıdır. Gerçek parolayı kaynak ZIP'ine veya proje belgelerine koymayın. Betik TLS sertifikasını normal şekilde doğrular; sertifika hatalarını atlatmak için `curl -k` kullanılmaz.

## Kontroller

- `/health` HTTP 200 ve JSON `status: ok` döndürür; bu uygulama ile DB bağlantısının temel kontrolüdür.
- `/admin/login` HTTP 200 döndürür.
- Yönetici giriş yanıtında `X-Robots-Tag: noindex` vardır.
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` ve `Content-Security-Policy` başlıkları vardır.
- `robots.txt`, `Disallow: /` ile staging arama motoru taramasını engeller.
- Oturum açmamış ziyaretçinin `/admin` isteği login sayfasına yönlendirilir.

## Güvenli sınırlar ve yorumlama

- Bu betik HTTP erişimini doğrular; Plesk parola korumasını, dosya izinlerini, gerçek `document root` değerini, veritabanı şemasını, dosya indirme engellerini veya mobil tarayıcı görünümünü tek başına kanıtlamaz.
- `/health` 503 döndürürse betik başarısız olur. Veritabanı bağlantısı, yapılandırma ve uygulama logları sunucuda incelenmelidir; loglarda gizli değerleri paylaşmayın.
- `robots.txt` kuralı tek başına gizlilik kontrolü değildir. Staging ayrıca Plesk/HTTP Basic parola korumasıyla korunmalıdır.
- Bu smoke test başarılı olsa bile Composer kurulumu, `php artisan test`, migration kontrolü, yedekten geri yükleme tatbikatı ve manuel tarayıcı testleri ayrıca tamamlanmalıdır.
- Bu betik üretim URL'sine değil, sadece staging URL'sine yöneliktir. Hiçbir zaman deploy/rollback veya veri değiştirme komutu çalıştırmaz.
