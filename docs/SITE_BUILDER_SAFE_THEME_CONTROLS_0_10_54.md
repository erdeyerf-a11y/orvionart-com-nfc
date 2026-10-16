# Site Builder güvenli tema kontrolleri — 0.10.54

## Amaç
V1.5 ana talimatının genel site ayarları bölümündeki tema seçeneklerinin bir kısmını panelden kod değiştirmeden yönetilebilir yapmak.

## Uygulanan alanlar
- Sayfa arka plan rengi ve bölüm yüzeyi rengi.
- Başlık ve gövde için ayrı yazı tipi seçimi (system, sans-serif, serif).
- Buton köşe stili (oval, yuvarlatılmış, köşeli).
- Üst menü davranışı (normal veya kaydırmada sabit).
- Footer yerleşimi (yan yana veya alt alta).
- Eski `font_family` alanı korunur ve yeni alanlar yoksa geriye dönük varsayılan olarak kullanılır.

## Güvenlik ve bütünlük
- Renkler yalnızca `#RRGGBB` biçiminde kabul edilir.
- Font, buton, header ve footer değerleri sabit izin listelerinden seçilir; keyfî CSS/JavaScript alanı yoktur.
- Public Blade render renk değerlerini tekrar doğrular; eski/bozuk ayar satırı CSS'e serbest metin olarak basılmaz.
- Ayar kaydı transaction ve audit kaydı içinde kalır; mevcut ayarlar varsayılanlarla birleştirilir, eski form istemcisi yeni alanları silmez.
- Ödeme veya Demo Engine ayarları bu ekrana eklenmedi ve bu iş kuralları değiştirilmedi.

## Test kanıtı ve sınır
- `SiteBuilderTest` içine allowlist/persist/render ve geçersiz CSS değeri regresyon test kaynakları eklendi.
- `scripts/release-gate.sh` bu kaynakların ve tema uygulamasının mevcutluğunu kontrol eder.
- Bu ortamda Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testleri çalıştırılmadı. Release Gate statik kontroldür; DB veya tarayıcı kabul testi değildir.

## V1.5 kapsamında kalanlar
Logo/favikon yükleme ve yaşam döngüsü, varsayılan sosyal paylaşım görseli, header menü davranışının daha ayrıntılı seçenekleri, daha kapsamlı footer bileşenleri, masaüstü/tablet/mobil gerçek cihaz testi ve kalan bölüm türlerinin yönetilebilirliği ayrıca tamamlanmalıdır.
