# Kullanım Hakları — 0.9.1 CSV dışa aktarımı ve güvenlik notları

## Bu sürümde

- Süper Admin, `/admin/usage-rights/export` üzerinden mevcut filtreleri uygulayarak CSV indirebilir.
- Dışa aktarma yalnızca `auth` + `superadmin` grubundadır ve ayrıca `throttle:10,1` ile sınırlandırılmıştır.
- Tek dosyada en fazla 5.000 kayıt dışa aktarılır. Sınır aşılırsa kayıtlar kısmen indirilmez; kullanıcıdan filtreleri daraltması istenir.
- UTF-8 BOM Türkçe karakterlerin elektronik tablo uygulamalarında daha doğru açılmasına yardımcı olur.
- CSV hücreleri `=`, `+`, `-`, `@` veya kontrol boşluklarından sonra bu karakterlerle başlayan metinler açısından formül enjeksiyonuna karşı nötralize edilir.
- Yanıt `private, no-store` ve `nosniff` başlıklarıyla gönderilir.
- Dışa aktarma `usage_right.export_prepared` audit olayı üretir. Audit metadata yalnızca satır sayısı ve kullanılan filtre türlerini saklar; arama metninin kendisini saklamaz.
- Dışa aktarım kayıt başlığı, türler, referans, kaynak, süre, durum ve sorumlu yönetici bilgilerini içerir. İç notlar ve genel audit metadata'sı CSV'ye alınmaz.
- Eşzamanlı iptal isteklerinde satır kilidi alınarak kayıt durumu transaction içinde tekrar kontrol edilir.

## Bilinçli sınırlar

Bu modül hâlâ kullanım hakkı kayıt defteridir; gerçek kaynak erişimini açıp kapatmaz. CSV'de görünen “kayıt aktif” ifadesi uygulama erişimi garantisi değildir. Ücretli erişim etiketi ödeme başlatmaz. 30 günlük sürenin hangi olayda başlayacağı ve hangi varlığa bağlanacağı için iş kararı gereklidir.

## Test durumu

CSV yetkisi, formül başlangıcı nötralizasyonu ve audit kaydı için feature test kaynakları eklendi. Bu ortamda Composer bulunmadığı için PHPUnit testleri çalıştırılmadı. PHP sözdizimi kontrolü, JSON kontrolü ve ZIP bütünlük kontrolü çalıştırılabilir; bunlar uygulama runtime testi değildir.
