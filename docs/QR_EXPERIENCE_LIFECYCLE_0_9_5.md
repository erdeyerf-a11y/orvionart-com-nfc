# QR/NFC ve Deneyim Yaşam Döngüsü — 0.9.5

## Amaç
QR/NFC hedefleri kalıcı yönlendirme adresi kullanır. Bu adresin etkin olması, bağlı deneyimin her zaman ziyaretçiye açık olduğu anlamına gelmez. Kamuya açık erişimin tek ölçütü deneyimin yayın durumudur.

## Bu sürümde
- `Experience::scopePubliclyAvailable()` kamuya açık sorgu kuralını tek yerde toplar.
- Deneyim oynatıcı bu sorgu kapsamını kullanır; `Experience::isPubliclyAvailable()` ile aynı kuralları izler.
- QR/NFC yönlendirmesi hedef deneyim yayımlanmamış, zamanı gelmemiş veya arşivlenmişse 404 döndürür ve tarama sayacını artırmaz.
- Yönetim ekranında bağlantının etkin/devre dışı durumu ile hedef deneyimin erişilebilirliği ayrı gösterilir.
- Gelecek tarihli planlı deneyim için yayın zamanı yönetim ekranında görünür.
- Eski/yanlış slug ile gelen kanonik yönlendirme yanıtında önbellek ve referrer başlıkları kullanılır.
- Bakım modunda QR yönlendirmesi 503 verir; bakım sırasında tarama sayacı artırılmaz.

## Yönetim ekranındaki hedef durumları
- **Hedef yayında:** deneyim şu anda kamuya açık.
- **Yayın zamanı bekleniyor:** planlanmış deneyimin zamanı henüz gelmedi.
- **Hedef şu anda erişime kapalı:** taslak, arşiv veya başka bir erişilemez durum.
- **Bağlantı devre dışı:** yönetici QR/NFC bağlantısını kapatmış.
- **Deneyim bulunamadı:** hedef kayıt mevcut değil veya ilişki çözülemiyor.

Bu göstergeler yalnızca yönetim ekranı bilgilendirmesidir; yeni bir erişim hakkı oluşturmaz ve QR/NFC bağlantısının kalıcı token'ını değiştirmez.

## Güvenlik ve kapsam sınırları
- QR/NFC yönlendirmeleri dışarıdan sağlanan URL'lere yönlendirme yapmaz; hedef URL uygulama içindeki deneyim kaydından oluşturulur.
- Ziyaretçi IP adresi ve User-Agent bu yönlendirme sayacı için saklanmaz.
- Bakım modu ziyaretçi trafiğini durdurur, yönetim panelini ve sağlık kontrolünü açık bırakır.
- Bu sürümde fiziksel NFC etikete yazma işlemi yapılmaz; kullanıcı uyumlu yazıcı/telefonla sabit yönlendirme adresini etikete yazar.

## Test durumu
Feature test kaynakları; taslak, gelecekte yayınlanacak, gelecekte planlanmış ve arşivlenmiş hedeflerde 404/sayaç davranışını, sorgu ile model kuralının tutarlılığını ve bakım modunda QR davranışını kapsar. Composer bağımlılıkları bu geliştirme ortamında kurulmadığından testler çalıştırılmamıştır.
