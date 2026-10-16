# ORVIONART 0.10.21 — Denetim kaydı hijyeni

Yeni salt okunur komut:

```bash
php artisan orvionart:inspect-audit-logs
php artisan orvionart:inspect-audit-logs --json
```

Denetimler:
- Gelecekte görünen `created_at` değerleri (5 dakikalık toleransla).
- Boş `action` alanları.
- Geçerli IPv4/IPv6 biçiminde olmayan IP adresleri.
- Metadata içinde `password`, `secret`, `token`, `authorization`, `api_key`, `cookie` veya `credential` gibi hassas bilgi çağrışımlı anahtarlar.
- `actor_id` değerinin `users` tablosunda karşılığı olmayan kayıtlar.

Komut metadata değerlerini rapora yazmaz; uyarı sayısını ve en fazla 20 kayıt ID'sini gösterir. Kayıtları değiştirmez veya silmez.

## Sınırlar

- Hassas anahtar eşleşmesi bir inceleme sinyalidir, kesin veri sızıntısı kanıtı değildir.
- Bu komut kriptografik hash zinciri oluşturmaz ve audit log kayıtlarının kurcalanmadığını kanıtlamaz.
- Gerçek Laravel Feature testleri, Composer ve bağımlılıklar kurulu bir ortamda çalıştırılmalıdır.
