# Demo Engine salt okunur denetimi — 0.9.8

## Amaç

Demo Engine'in yönetim ekranında görünmeyen operasyonel sorunlarını, özellikle depolamada kaybolmuş demo medya dosyalarını ve yapılandırma tutarsızlıklarını sunucuya bağlandıktan sonra hızlıca inceleyebilmek.

## Komutlar

İnsan tarafından okunabilir rapor:

```bash
php artisan orvionart:inspect-demo
```

Makine tarafından okunabilir JSON raporu:

```bash
php artisan orvionart:inspect-demo --json
```

Komut aşağıdaki bilgileri sayar veya denetler:

- Varsayılan Demo Engine ayar kaydı ve aktiflik durumu
- Toplam/aktif demo içeriği
- Toplam, geçerli ve sona ermiş oturumlar
- Son 24 saatte oluşturulan demo olayları
- Bugün için geçerli oturumlarda pasif içeriğe bağlı günlük atamalar
- Demo içeriklerine bağlı dosyaların depolama diskinde bulunabilirliği

Medya denetiminde rapora yalnızca içerik kayıt numaraları eklenir; depolama yolu yazdırılmaz. Ham cihaz token'ları, `.env` değerleri, veritabanı kimlik bilgileri ve medya dosyası içerikleri rapora dahil edilmez.

## Çıkış kodu

- `0`: raporda uyarı yok.
- `1`: bir veya daha fazla tutarsızlık/uyarı var.

Uyarılar; ayar kaydının bulunmaması, aktif engine'de etkin içerik olmaması, medya dosyasının bulunamaması/okunamaması ve bugün için geçerli oturumlarda pasif içeriğe bağlı atamalar için üretilir.

## Salt okunurluk ve sınırlar

Komut kayıt oluşturmaz, güncellemez, silmez; depolama dosyalarını değiştirmez. Bu nedenle varsayılan ayar kaydı yoksa `DemoSetting::current()` çağrısındaki gibi otomatik oluşturma yapmaz. Depolama sürücüsünün okunamaması da güvenli biçimde eksik/erişilemeyen medya uyarısı olarak raporlanır.

Bu komut bir tanılama aracıdır. HTTP uç noktalarını, tarayıcı davranışını, yetki kontrollerini veya gerçek kullanıcı yolculuğunu uçtan uca test etmez. Staging'de runtime ve feature testleri ayrıca çalıştırılmalıdır.
