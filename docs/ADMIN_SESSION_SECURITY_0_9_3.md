# ORVIONART 0.9.3 — Yönetici oturum güvenliği

## Eklenenler
- Yönetici rotalarına bağlı sunucu tarafı hareketsizlik zaman aşımı middleware'i.
- Varsayılan süre 30 dakika; `.env` içindeki `ORVIONART_ADMIN_IDLE_TIMEOUT` ile ayarlanır.
- Middleware ayarı 5–240 dakika aralığında sınırlandırır.
- Yönetici rotalarına her erişimde son etkinlik zamanı güncellenir.
- Süre dolduğunda oturum kapatılır, session invalidate edilir, CSRF token yenilenir ve `auth.session_expired_idle` audit olayı kaydedilmeye çalışılır.
- Zaman aşımı yönetici rotalarına uygulanır; giriş rotası ve herkese açık ziyaretçi deneyimleri bundan etkilenmez.
- Feature test kaynakları etkinlik zamanını, hareketsizlik sonrası çıkışı ve konfigürasyon sınırını kapsar.

## Güvenlik notları ve sınırlar
- Bu özellik yalnızca hareketsizlik zaman aşımıdır; MFA/2FA yerine geçmez.
- Uygulama sunucularının saatleri senkronize olmalıdır.
- Oturum depolama yapılandırması ve HTTPS/secure cookie ayarları hedef sunucuda ayrıca doğrulanmalıdır.
- Laravel runtime, veritabanı ve feature testleri bu çalışma ortamında Composer bağımlılıkları bulunmadığından çalıştırılmamıştır.
