# ORVIONART 0.10.8 — Site Builder yayın çakışması ve önizleme izolasyonu

## Yayın adresi çakışması

Yayınlama öncesinde, taslak sayfanın `slug` değeri başka bir sayfanın `published_slug` değeriyle (büyük/küçük harf farkı gözetmeden) karşılaştırılır. Çakışma varsa yayınlama `422` yanıtıyla durur ve yöneticiye adresin başka bir canlı sayfa tarafından kullanıldığı açıklanır. Bu kontrol veriyi otomatik değiştirmez. Veritabanındaki `published_slug` benzersiz indeksi de son koruma katmanı olarak kalır.

Bu kontrolün amacı, beklenen bir yayın çakışmasının kullanıcıya anlaşılmaz bir veritabanı hatası olarak yansımasını önlemektir. Eşzamanlı iki yayın isteğinde veritabanı benzersiz indeksi yine belirleyicidir; gerçek veritabanı/yarış koşulu testi staging ortamında ayrıca yapılmalıdır.

## Taslak önizlemesi

Site Builder önizleme yanıtı açıkça şu başlıkları döndürür:

- `X-Robots-Tag: noindex, nofollow, noarchive`
- `Cache-Control: private, no-store` (genel yönetici güvenlik middleware'i bunu `no-store, private` biçiminde normalleştirebilir)
- `Referrer-Policy: no-referrer`

Önizleme, taslağı yayınlamaz ve yayın anlık görüntüsünü değiştirmez. Önizleme adresi yalnızca yönetici rotası ve yetkilendirme üzerinden erişilir.

## Eklenen Feature test kaynakları

- Başka bir canlı sayfanın yayın slug'ıyla çakışan taslağın yayınlanmasının engellenmesi ve mevcut canlı sayfanın korunması.
- Taslak önizlemesinde noindex, no-store ve no-referrer başlıklarının bulunması.

Bu test kaynakları Composer/Laravel runtime ve test veritabanı gerektirir. Bu ZIP hazırlanırken Composer bağımlılıkları mevcut olmadığından Laravel Feature testleri çalıştırılmamıştır.
