# Staging Site Builder sayfalarında indeksleme koruması — 0.10.49

## Sorun

Site Builder'ın yayımlanmış sayfaları, `published_noindex=false` olduğunda `index, follow` başlığı döndürebiliyordu. Staging veya başka bir üretim dışı ortamda sayfa yayımlanmış bir snapshot olsa bile bu başlık arama motorlarına indekslemeye izin verebilirdi. `robots.txt` içindeki `Disallow` tek başına yeterli bir indeksleme koruması değildir.

## Değişiklik

Kamuya açık Site Builder sayfaları artık üretim dışındaki ortamlarda `X-Robots-Tag: noindex, nofollow, noarchive` gönderir. Üretimde ise sayfanın `published_noindex` bayrağı korunur: bayrak açıksa noindex, kapalıysa `index, follow`.

Bu değişiklik erişim kontrolünün veya staging parola korumasının yerine geçmez. Staging alanı yine ayrıca erişim kısıtlamasıyla korunmalıdır.

## Test sınırı

Bir Laravel Feature test kaynağı eklendi. Bu çalışma ortamında Composer/vendor bağımlılıkları bulunmadığından Feature test çalıştırılmadı; PHP sözdizimi ve Release Gate statik kontrolleri ayrı yürütülür. Gerçek staging sunucusunda HTTP başlığı ayrıca doğrulanmalıdır.
