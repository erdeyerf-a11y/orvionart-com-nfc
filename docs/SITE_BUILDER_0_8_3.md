# ORVIONART 0.8.3 — Merkezi Medya ve Bakım Modu

## Bu sürümde
- Merkezi medya kütüphanesinde görsel/video türü ve kullanım yeri filtreleri.
- Kart görünümünde görsel/video önizlemesi, dosya türü/boyutu ve ilişkilendirme bilgisi.
- Önizleme yalnızca yönetici rotasında, oturum ve Super Admin kontrolü arkasında sunulur; özel dosyayı herkese açmak için yeni bir public URL oluşturulmaz.
- Bir deneyime bağlı medyanın doğrudan silinmesi engellenir; önce deneyim medya bağlantısı yönetimden kaldırılmalıdır.
- Site Builder genel ayarındaki bakım modu artık tüm herkese açık hedeflere uygulanır: ana sayfa, içerik sayfaları, deneyim oynatıcı, QR/NFC yönlendirmeleri, public medya, Demo Engine ve sitemap.
- `/admin/*`, `/health`, `/up` ve `robots.txt` bakım modunda erişilebilir kalır. Bakım yanıtı HTTP 503, `Retry-After`, `no-store` ve `noindex` başlıklarını içerir.
- Bakım modu için Feature test kaynakları eklendi.

## Bilinen sınırlamalar
- Composer bağımlılıkları kurulmadı; Laravel/DB testleri çalıştırılmadı.
- Medya kütüphanesinde ilişkili varlıkların toplu taşınması veya toplu silinmesi yoktur.
- Depolama adaptörünün stream davranışı gerçek sunucuda doğrulanmalıdır.
- `health` endpoint'i bakım modundan bağımsız olarak gerçek veritabanı durumunu bildirir; veritabanı erişilemiyorsa 503 dönmesi beklenir.
- Bu sürüm gerçek sunucuya yüklenmedi.
