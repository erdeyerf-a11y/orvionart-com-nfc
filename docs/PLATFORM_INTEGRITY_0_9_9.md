# Bütünleşik platform veri bütünlüğü denetimi — 0.9.9

## Kullanım

Salt okunur kısa rapor:

```bash
php artisan orvionart:inspect-platform
```

Makine tarafından okunabilir JSON raporu:

```bash
php artisan orvionart:inspect-platform --json
```

Komut uyarı bulmazsa çıkış kodu `0`, uyarı bulursa `1` döndürür. Veritabanına yazmaz, kayıtları düzeltmez ve dosya silmez.

## Kontrol kapsamı

- Deneyim kayıtlarının ve herkese açık deneyimlerin sayısı.
- Etkin QR/NFC bağlantılarının hedefinin herkese açık olup olmadığı.
- Medya kayıtlarının dosyalarının depolamada bulunması.
- Bir medya kaydının aynı anda hem deneyime hem Site Builder sayfasına bağlanması.
- Yayındaki deneyimlerin medya dosyaları ve yayındaki Site Builder sayfalarının medya referansları.
- Site Builder eski adres yönlendirmelerinin hedefinin hâlen yayında olup olmadığı.
- Kullanım hakkı kayıtlarının sayısı ve henüz karara bağlanmamış `subject_type=unresolved` kayıtları.

## Uyarı kodları

- `media_has_multiple_owners`: Medya kaydı iki modüle birden bağlıdır.
- `media_file_missing`: Depolama dosyası bulunamıyor veya disk okunamıyor.
- `active_qr_target_unavailable`: QR/NFC bağlantısı etkin, ancak hedef deneyim herkese açık değil. Taslak/planlı deneyimlerde bu durum yönetici tarafından bilerek bırakılmış olabilir; rapor inceleme gerektiren bir durumu bildirir.
- `published_media_reference_invalid`: Yayındaki içerikte eksik medya kaydı, yanlış sahiplik, desteklenmeyen MIME türü veya eksik dosya vardır.
- `site_redirect_target_unavailable`: Eski adres yönlendirmesinin hedefi şu anda yayında değildir.

## Gizlilik ve sınırlar

JSON raporu QR/NFC token'larını, gizli yapılandırmayı veya depolama yollarını yazdırmaz. Medya ve sayfa kayıt kimlikleri yönetim/CLI incelemesi için rapora dahil edilir. Kullanım hakları yalnızca sayılır; gerçek erişim kontrolüne dönüştürülmez. Bu komut Laravel runtime, yetki, güvenlik, mobil veya son kabul testlerinin yerine geçmez.

Composer bağımlılıkları ve veritabanı çalışma ortamı bu kaynak hazırlama ortamında kurulmadığı için komut henüz gerçek uygulama runtime'ında çalıştırılmamıştır. Test kaynakları eklendi; testler çalıştırılmadı.
