# Kullanım Hakları — 0.9.0 kayıt defteri

## Amaç

Bu sürüm, kullanım hakkı kayıtlarını yalnızca Süper Admin'in yönetebildiği bir kayıt defteri, iptal kaydı ve audit izi sağlar. Henüz deneyim, cihaz, müşteri, token veya başka bir kaynağın erişim kararına bağlanmaz. Bu sınır kasıtlıdır; ana V1.5 talimatındaki ticari kararlar kesinleşmemiştir.

## Yönetim rotaları

- `GET /admin/usage-rights` — filtrelenebilir kayıt listesi
- `POST /admin/usage-rights` — manuel kayıt oluşturma
- `PATCH /admin/usage-rights/{id}/revoke` — gerekçeli iptal

Rotalar `auth` ve `superadmin` middleware arkasındadır. Kamuya açık bir hak verme rotası eklenmemiştir.

## Kayıt alanları

- Hak türü: standart 30 günlük ücretsiz plan, genel ücretsiz kullanım dönemi, ilk yıl promosyonu, Demo cihaz yolculuğu etiketi, ücretli erişim etiketi veya özel.
- Başlık, genel varlık türü ve isteğe bağlı hassas olmayan varlık referansı.
- Kayıt kaynağı, başlangıç/bitiş zamanı, not.
- Oluşturan yönetici ve gerekçeli iptal eden yönetici.
- `usage_right.created` ve `usage_right.revoked` audit olayları.

Hak türü/kaynak etiketleri otomatik iş kuralı çalıştırmaz. `paid_access` kaydı ödeme başlatmaz. `standard_free_30_days` seçimi 30 gün hesaplamaz ve süreyi başlatacak olayı belirlemez. Hiçbir hak süresi dolunca otomatik ücret başlatılmaz.

## Durum

Veritabanındaki `status`, yönetici iptalini (`active`/`revoked`) saklar. Listede gösterilen “Henüz başlamadı” ve “Süresi doldu” etiketleri zaman alanlarından yalnızca görüntüleme amacıyla türetilir; veritabanı statüsünü değiştirmez ve erişim denetimi değildir.

## İş kararı gerekli

Üretim erişim denetimi uygulanmadan önce aşağıdakiler açıkça kararlaştırılmalı ve ayrı kabul kriterleriyle test edilmelidir:

1. Hak hangi varlığa bağlanır: deneyim, cihaz, token, müşteri hesabı veya başka bir kimlik?
2. Hesapsız ziyaretçi deneyimi korunurken hak sahibi nasıl belirlenir?
3. 30 günlük standart ücretsiz süre hangi olayla başlar ve hangi saat diliminde gösterilir?
4. İlk yıl promosyonunun başlangıcı ve kapsamı nedir?
5. Hak tüketilebilir mi; “tükenmiş” durumunu hangi olay belirler?
6. İptal/sona erme hangi erişim noktalarında uygulanır?
7. Ücretli erişim hakkı ancak doğrulanmış ödeme olayıyla mı oluşur, yoksa yönetici istisnası olacak mı?

Bu kararlar olmadan uygulama bunları varsaymaz.

## Tarih ve saat

Formdaki `datetime-local` alanları Laravel uygulama saat dilimiyle yorumlanır. Örnek yapılandırmada `APP_TIMEZONE=Europe/Istanbul` bulunur. Gerçek sunucu kurulumu sırasında uygulama ve PHP saat dilimi doğrulanmalıdır.

## Güvenlik sınırı

- Hak oluşturma/iptal yalnızca Süper Admin rotalarında yapılır.
- İptal gerekçesi zorunludur.
- Oluşturma/iptal audit kayıtları yazılır.
- Kayıt referansına parola, token sırrı veya hassas kişisel veri girilmemelidir.
- Bu modül şu an gerçek erişim yetkilendirmesi sağlamaz; kullanıcıya veya müşteriye “erişim hakkın aktif” vaadi olarak kullanılmamalıdır.
