# ORVIONART 0.10.11 — Site Builder sürüm geri alma güvenliği

## Amaç

Site Builder sürüm geçmişi kayıtları eski içerik snapshot'ları tutar. Bu kayıtlar mevcut kurallara göre tekrar doğrulanmadan doğrudan uygulanmamalıdır. Bu sürüm, geri alma işlemine mevcut sayfa doğrulamalarını ekler.

## Yapılan değişiklik

- Snapshot'ın dizi biçiminde olması gerekir.
- Başlık boş olamaz ve 180 karakteri aşamaz.
- Slug, küçük harfli harf/rakam ve tire biçimine uymalıdır.
- Sayfa türü `SitePage::TYPES` içinde olmalıdır.
- `home` slug'ı ile `home` sayfa türü birlikte kullanılmalıdır.
- `admin`, `api`, `go`, `media`, `demo`, `health`, `robots`, `p`, `login`, `logout`, `sitemap` ve `admin-` ile başlayan adresler reddedilir.
- Eski slug başka bir sayfanın güncel slug alanını kullanıyorsa geri alma doğrulama hatasıyla durur.
- Bölüm medya referansları ve sosyal paylaşım görseli için mevcut kontroller çalışmaya devam eder.

## Canlı yayın koruması

Geri alma işlemi yalnızca düzenlenebilir taslak alanlarını günceller. `published_sections`, `published_slug`, `published_title` ve diğer yayın snapshot alanları bu işlemde değiştirilmez. Geri alma sonrası canlı içeriği değiştirmek için yönetici ayrıca yayınlama işlemi yapmalıdır.

## Eklenen test kaynakları

1. Eski slug başka sayfada kullanılıyorsa geri alma reddedilir ve mevcut taslak değişmez.
2. Geçerli eski sürüm taslağa geri alınır; canlı yayın snapshot'ı değişmez ve `restored` revision/audit akışı sürer.

Bu testler Laravel Feature test kaynaklarıdır; Composer ve Laravel bağımlılıkları bulunmadığından bu çalışma ortamında çalıştırılmadılar.

## Statik sınırlar

Bu denetim slug çakışması ve snapshot şekli gibi belirli durumları kapsar; gerçek DB eşzamanlılık, migration, tarayıcı ve staging kabul testlerinin yerine geçmez. Canlı sunucuda uygulanmış değildir.
