# Site Builder yönlendirme denetimi — 0.10.17

Yeni komut:

```bash
php artisan orvionart:inspect-site-redirects
php artisan orvionart:inspect-site-redirects --json
```

Denetim salt okunurdur. Eski/yeni slug biçimini, kendi adresine yönlendirmeyi, beklenmeyen HTTP durum kodlarını, herkese açık olmayan hedefleri, düzenlenebilir slug alanıyla çakışan kaynak adresleri ve büyük/küçük harf farkıyla oluşan olası yinelenen kaynakları raporlar.

## Güvenlik ve kapsam sınırları

- Hiçbir yönlendirme kaydı oluşturulmaz, değiştirilmez veya silinmez.
- JSON raporunda en fazla 20 örnek kayıt ID'si kategori başına gösterilir; URL parametreleri veya sırlar raporlanmaz.
- Bu, veritabanı durumunu inceleyen salt okunur bir denetimdir; HTTP yönlendirme zinciri, CDN/cache davranışı veya gerçek tarayıcı kabul testi değildir.
- SQL `LOWER()` karşılaştırmasının davranışı üretim veritabanında doğrulanmalıdır.
- Düzeltme öncesinde her kayıt insan tarafından incelenmelidir.

## Test kaynakları

`tests/Feature/SiteRedirectInspectionTest.php` geçerli yönlendirme, erişilemeyen hedef, desteklenmeyen durum kodu ve taslak slug çakışması senaryolarını içerir. Bu paketin hazırlandığı ortamda Composer/Laravel runtime olmadığı için Laravel Feature testleri çalıştırılmamıştır.
