# Sitemap `lastmod` — 0.10.25

## Sorun
Sitemap kayıtlarında `updated_at` kullanılması, yayındaki içeriğe dokunmadan yalnızca taslağı düzenleyen yöneticinin sitemap `lastmod` değerini değiştirebiliyordu. Bu, herkese açık içeriğin son değişiklik zamanını yanlış yansıtır.

## Değişiklik
- Site Builder sayfaları `published_at` değerini kullanır; geriye dönük/veri bütünlüğü için alan yoksa `created_at`, o da yoksa geçerli zaman kullanılır.
- Deneyimlerde `published_at`, zamanlanmış yayınlarda `scheduled_at`, son çare olarak `created_at` kullanılır.
- İçerik veya yayın durumu değiştirilmez; yalnızca sitemap tarih alanı hesaplanır.

## Doğrulama
İki Feature test kaynağı sayfa ve deneyim için yayın tarihi ile daha yeni `updated_at` değerini karşılaştırır. Composer ve Laravel runtime bu geliştirme ortamında mevcut olmadığından testlerin çalıştırılması staging ortamına bırakılmıştır.
