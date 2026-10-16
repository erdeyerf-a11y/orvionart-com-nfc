# Demo Engine veri saklama ve temizleme — 0.9.4

## Amaç

`demo_settings.retention_days` artık yalnızca belge niteliğinde bir ayar değil; `orvionart:prune-demo-data` komutunun kullandığı veri saklama süresidir. Varsayılan değer 30 gündür. Komut demo oturumlarını ve demo olaylarını temizler; Demo içerik havuzundaki içerik veya medya dosyalarını silmez.

## Temizleme kuralı

- `demo_events.created_at`, kesim zamanından eskiyse olay kaydı silinir.
- Oturum, doğal bitiş tarihi (`ends_at`) saklama süresinden daha eskiyse ve `expired_at` boşsa silinebilir.
- Oturum açıkça sona erdirildiyse (`expired_at` dolu), bu tarih saklama süresinden eski olduğunda silinebilir.
- Oturum silinince ilişkili günlük atamalar foreign key `cascadeOnDelete` kuralıyla silinir.
- Olay kayıtları bağımsız saklama kuralıyla temizlenir; oturum silinmesinden önce eski olaylar temizlenir.

Bu politika cihaz token'ının ham değerini hiçbir zaman saklamaz; mevcut sistemde yalnızca SHA-256 özeti saklanır.

## Komutlar

Önce silinecek adetleri görmek için:

```bash
php artisan orvionart:prune-demo-data --dry-run
```

Temizliği çalıştırmak için:

```bash
php artisan orvionart:prune-demo-data
```

Laravel Scheduler her gün saat 03:20'de çalışacak şekilde tanımlanmıştır. Sunucuda scheduler için cron/iş zamanlayıcı yapılandırılmalıdır; uygulama kodunun schedule tanımı tek başına işletim sistemi zamanlayıcısını etkinleştirmez. Laravel'in önerilen cron girişi:

```cron
* * * * * cd /GERCEK/YOL/ORVIONART && php artisan schedule:run >> /dev/null 2>&1
```

Gerçek sunucu yolu doğrulanmadan bu örnek aynen uygulanmamalıdır. İlk canlı kullanımdan önce staging ortamında dry-run ve normal komutla doğrulama yapılmalıdır. Komut yedekleme yerine geçmez.

## Güvenlik ve sınırlamalar

- Komut idempotent olacak şekilde tasarlanmıştır; tekrar çalıştırma aynı eski kayıtları ikinci kez silmez.
- Silme işlemleri tek veritabanı transaction'ı içinde yürütülür.
- `--dry-run` hiçbir oturum/olay kaydını silmez.
- Demo içerikleri ve medyası silinmez.
- Komut için otomatik audit kaydı eklenmedi; CLI çalıştırma çıktısı ve sunucu log/cron çıktısı operasyonel izleme için kullanılmalıdır.
- Bu özellik kaynak koda eklendi; Composer/Laravel runtime ve gerçek DB testleri bu çalışma alanında çalıştırılmamıştır.
