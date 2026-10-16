# ORVIONART 0.8.2 — Yayın sürümü ayrımı, yönlendirme ve paylaşım SEO

## Temel düzeltme
Önceki Site Builder sürümünde bazı sayfa üst verileri taslak kaydedildiği anda yayımlanmış görünümü etkileyebilirdi. Bu sürümde başlık, slug, sayfa türü, menü görünürlüğü ve sosyal paylaşım görseli yayın anında ayrı `published_*` alanlarına kopyalanır. Taslak kaydı tek başına canlı görünümü değiştirmemelidir.

## Eklenenler
- Yayınlanmış slug için ayrı `published_slug`; ana sayfa ve normal sayfa aramaları yayınlanmış slug üzerinden yapılır.
- Bir yayınlanmış sayfanın slug'ı değiştiğinde eski adres için 301 yönlendirme kaydı. Önceki yönlendirmeler yeni adrese birleştirilir; taslak slug değişikliği canlı yönlendirme oluşturmaz.
- Open Graph title/description/image ve Twitter card metadata. Görsel yalnızca aynı sayfanın özel medya kayıtlarından seçilebilir.
- Header/footer navigasyon sıralamasını yukarı/aşağı taşıma kontrolleri.
- Önceden yayımlanmış kayıtlar için yeni yayın alanlarını dolduran migration backfill.

## Güvenlik ve iş sınırları
- Taslak sayfalar eski slug yönlendirmesiyle içerik sızdırmamalıdır. Mevcut taslak slug doğrudan bulunursa 404 verilir.
- 301 yalnızca sayfanın yeni sürümü yayınlanınca oluşturulur.
- Sosyal görselin medya kaydı, taslak veya yayımlanmış sayfada referanslıyken silinemez.
- Ödeme ve otomatik tahsilat yoktur; Demo Engine ve ücretsiz kullanım hakları bu geliştirmeden etkilenmez.

## Test durumu
Bu pakette PHP lint, composer.json JSON doğrulaması, route/view statik incelemesi ve ZIP bütünlük kontrolü yapılacaktır. Composer bağımlılık kurulumu, Laravel runtime, migration execution, PHPUnit, tarayıcı ve canlı sunucu testleri mevcut ortamda yapılamaz; sonuçlar ayrıca raporlanır.
