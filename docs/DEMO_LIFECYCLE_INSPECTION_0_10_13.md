# Demo Engine yaşam döngüsü denetimi — 0.10.13

## Amaç

`php artisan orvionart:inspect-demo-lifecycle` Demo Engine oturumlarının, günlük içerik atamalarının ve olay zamanlarının temel kronolojik tutarlılığını salt okunur olarak denetler.

## Kontroller

- Oturum bitiş zamanı başlangıç zamanından önce mi?
- `last_seen_at` oturum başlangıcından önce mi?
- Açık `expired_at` değeri oturum başlangıcından önce mi?
- Günlük içerik atamasının tarihi oturumun başlangıç/bitiş tarih aralığının dışında mı?
- Demo olayı, ilişkili oturumun başlangıcından önce mi kaydedilmiş?

Her kontrol toplam kayıt sayısını ve inceleme için en fazla 20 örnek kayıt kimliğini raporlar. Cihaz token hash'i veya ham cihaz token'ı rapora eklenmez.

## Kullanım

```bash
php artisan orvionart:inspect-demo-lifecycle
php artisan orvionart:inspect-demo-lifecycle --json
```

Uyarı bulunursa komut `1` çıkış kodu verir. Bu, kayıtların otomatik düzeltildiği anlamına gelmez. Komut hiçbir satırı değiştirmez veya silmez.

## Sınırlar

Bu araç yalnızca zaman tutarlılığını inceler. Kullanım hakkı, ödeme, demo erişim süresi veya ticari kuralları değiştirmez. Oturumun doğal süresi dolmuş ancak `expired_at` alanı boş olması tek başına tutarsızlık sayılmaz; uygulama `ends_at` alanını da sona erme ölçütü olarak kullanır. Raporun temiz çıkması, gerçek ortamda uçtan uca kabul testi yerine geçmez.

## Test durumu

Üç Laravel Feature test senaryosu eklendi: temiz kayıtların değişmeden kalması, tutarsız oturum zamanlarının raporlanması ve oturum aralığı dışındaki atamanın raporlanması. Bu geliştirme ortamında Composer/Laravel bağımlılıkları yoksa testler çalıştırılamaz; yalnızca PHP sözdizimi kontrolü yapıldığı ayrıca belirtilmelidir.
