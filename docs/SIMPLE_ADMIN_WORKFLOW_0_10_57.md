# ORVIONART 0.10.57 — Basit Süper Admin kullanım akışı

## Amaç
Yönetici içerik oluştururken ayrı modüller arasında gereksiz dolaşmasın. Bu sürüm mevcut modülleri yeniden yazmaz; tek içerik → medya → yayın → QR/NFC yolunu belirginleştirir.

## Kullanım
1. Süper Admin ana ekranında **Yeni içerik oluştur** düğmesine basın.
2. Başlık, kısa açıklama ve içerik metnini girin; taslak olarak kaydedin.
3. İçerik düzenleme ekranındaki **Bu içeriğe görsel veya video ekle** bağlantısını kullanın. Medya ekranı o içeriği önceden seçer.
4. Fotoğrafı normal yükleme alanından; büyük videoyu devam edebilen parçalı yükleme alanından yükleyin. Dosya deneyime bağlı kaydedilir.
5. Deneyim ekranına geri dönün, içeriği yayımlayın.
6. Aynı ekrandan QR/NFC bağlantısı oluşturun. QR dosyasını indirin; NFC etiketine aynı kalıcı URL'yi yazın.
7. Telefonla QR kodunu okutun. Gerçek sunucu kabul testi yapılana kadar bu son adım doğrulanmış sayılmaz.

## Bu sürümdeki değişiklikler
- Dashboard gerçek veritabanı kayıt sayılarını gösterir: deneyim, ziyaretçiye açık deneyim, medya dosyası, etkin QR/NFC bağlantısı.
- Ana ekranda beş adımlı temel kullanım yolu gösterilir.
- Deneyim düzenleme ekranından medya yükleme ekranına doğrudan geçiş eklenmiştir.
- Normal medya yüklemesi deneyime bağlandıysa kullanıcı deneyim düzenleme ekranına geri döner.
- Büyük video yüklemesi de deneyime bağlıysa işlem bitince aynı düzenleme ekranına döner.
- Medya yükleme ekranı, deneyim sayfasından gelindiğinde deneyimi iki yükleme türünde de önceden seçer.

## Doğrulama sınırı
Bu paket için PHP/shell statik kontrolleri ve kaynak manifesti çalıştırılabilir. Composer bağımlılıkları ve Laravel runtime bu çalışma ortamında yoksa Feature testleri yalnızca kaynak olarak mevcuttur; çalıştırılmış kabul edilmez. Plesk, DB migration/rollback, medya yükleme, QR tarama ve NFC yazma kabul testleri gerçek staging ortamında yapılmalıdır.
