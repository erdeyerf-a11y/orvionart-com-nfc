# ORVIONART 0.9.2 — Denetim kayıtları görüntüleyicisi

## Amaç

Mevcut `audit_logs` kayıtlarını Süper Admin panelinde salt okunur olarak incelemek; işlem türü, tarih aralığı, olay/yönetici/kayıt araması ve sayfalama sağlamak.

## Eklenenler

- `GET /admin/audit-logs` ve `admin.audit-logs.index` rotası.
- Yalnızca mevcut `auth` + `superadmin` yönetim grubunun içinde çalışır.
- Tarih aralığı ve olay türü filtreleri; olay, yönetici adı/e-postası, kayıt türü ve kayıt numarası araması.
- Sayfa başına 30 olay; metaveri JSON olarak açılır-kapanır alanda görüntülenir.
- `password`, `secret`, `token`, `api_key`, `authorization`, `cookie` ve `credential` benzeri anahtar adlarına sahip metaveri alanları görünümde maskelenir.
- Kayıtları değiştiren/silen veya dışa aktaran uç nokta eklenmemiştir.
- Yönetim paneline kısayol ve özellik test kaynakları eklendi.

## Güvenlik ve sınırlar

- IP adresi, kullanıcı e-postası ve user-agent operasyonel kişisel veri olabilir; ekran yalnızca yetkili Süper Admin rolüne açıktır.
- Maskeleme anahtar adına dayalı savunma katmanıdır; tüm metaverinin hassas bilgi içermediğini garanti etmez. Kaynak olaylar gereksiz kişisel veya gizli veri yazmamalıdır.
- Bu ekran bir SIEM veya değiştirilemez harici günlük sistemi değildir. Veritabanına erişimi olan biri kayıtları değiştirebilir.
- Şu an dışa aktarma ve silme işlemi yoktur.

## Test durumu

`tests/Feature/AuditLogViewerTest.php` içinde misafir erişimi, normal kullanıcı reddi, filtreleme/metaveri maskelemesi ve tarih doğrulama senaryoları bulunur. Bu çalışma alanında Composer bağımlılıkları kurulmadığı için testler çalıştırılmış sayılmaz.
