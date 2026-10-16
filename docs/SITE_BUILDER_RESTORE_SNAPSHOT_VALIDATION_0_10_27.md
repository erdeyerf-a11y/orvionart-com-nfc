# Site Builder geçmiş sürüm snapshot doğrulaması — 0.10.27

## Amaç

Site Builder geri alma işlemi, veritabanında saklanan geçmiş snapshot'ı doğrudan güvenilir kabul etmemelidir. Özellikle slider CTA adresleri yayın sayfasında `href` olarak kullanılır. Güncel form doğrulamasını atlayan bozuk veya değiştirilmiş bir snapshot, tehlikeli URL şeması içerebilir.

## Değişiklik

Geri alma öncesi doğrulama artık şunları denetler:

- `show_in_menu` ve varsa `noindex` bayraklarının boolean biçimi;
- SEO başlığı/açıklamasının türü ve uzunluğu;
- canonical adresin geçerli HTTPS URL'si olması, kullanıcı bilgisi ve fragment içermemesi;
- bölüm sayısı/türü, metin alanları, varyant, etkinlik bayrağı, görsel kimlikleri ve öğe dizisi;
- bölüm ve slider CTA bağlantılarının yalnızca HTTPS, `/` ile başlayan site içi yollar veya `#` bağlantıları olması.

`javascript:`, `data:`, `vbscript:` ve `//example.com` gibi protokol-relative bağlantılar reddedilir. Hata oluştuğunda snapshot uygulanmaz ve mevcut taslak değiştirilmez. Yayındaki snapshot ayrıca korunur; geri alma yine yalnızca taslağı değiştirir.

## Doğrulama durumu

`tests/Feature/SiteBuilderTest.php` içine güvensiz slider URL'si ve geçersiz canonical adresi için iki regresyon testi eklendi. Bu çalışma ortamında Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılamadı. PHP sözdizimi, release manifesti ve statik release-gate kontrolleri ayrı olarak çalıştırılır; bunlar Laravel çalışma zamanı testinin yerine geçmez.
