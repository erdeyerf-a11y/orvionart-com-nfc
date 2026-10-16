# ORVIONART 0.8.1 — Site Builder genişletmesi

## Eklenenler
- `hero_slider`: Site Builder bölüm öğelerinden çoklu slayt. Satır biçimi `Başlık | Açıklama | Görsel ID | Buton metni | Buton adresi`; görsel ID aynı sayfanın özel medya alanındaki görsele ait olmalı. Görsel ID boş bırakılabilir. Slider manuel ileri/geri, durdur/oynat, 6 saniyelik otomatik geçiş ve `prefers-reduced-motion` desteği içerir.
- `experience_list`: Deneyim kayıtlarını çoğaltmaz. Deneyim Yönetimi'ndeki yayınlanmış veya planlı zamanı gelmiş erişilebilir kayıtları listeler; taslak ve arşiv kayıtları gösterilmez. En fazla 24 kayıt gösterilir.
- `qr_nfc_promo`: QR/NFC erişimini tanıtan içerik alanı. İsteğe bağlı öğe satırları (`Başlık | Açıklama`) ile kart metinleri yönetilebilir; öğe yoksa güvenli varsayılan metinler gösterilir.
- `demo_promo`: Demo Engine'in gerçek aktif durumunu okur. Engine kapalıysa “Demo şu anda kullanılamıyor” mesajı gösterilir ve bölüm CTA'sı gizlenir. Site Builder Demo Engine'i etkinleştirmez.
- `free_plan_promo`: Yalnızca pazarlama metni sunar; kullanım hakkı, süre veya ücret durumu oluşturmaz.
- `/sitemap.xml`: Production ortamında indekslenebilir Site Builder sayfalarını ve erişilebilir deneyimleri listeler. Staging/test ortamında boş URL seti verir. Harici canonical URL'ler site haritasına eklenmez.
- Taslak önizlemesinde masaüstü/tablet/mobil genişlik simülasyonu. Bu bir viewport önizlemesidir; gerçek cihaz testi değildir.

## Güvenlik ve iş kuralı sınırları
- Metinler Blade escape ile basılır; keyfî CSS/JS kabul edilmez.
- Slider görselleri aynı Site Builder sayfasına bağlı olmalıdır; yayınlama ve revizyon geri alma sırasında referanslar tekrar kontrol edilir. Yayındaki slider görselleri kontrollü medya rotasından sunulur; taslak/yayın bölümlerinde referanslı görselin silinmesi engellenir.
- Demo tanıtımı Demo Engine durumunu değiştirmez.
- Ücretsiz kullanım tanıtımı hak tanımlamaz.
- Ödeme/checkout/otomatik tahsilat eklenmedi ve etkinleştirilmedi.

## Kontrol durumu
- PHP sözdizimi ve JSON doğrulaması yapılabilir.
- Bu geliştirme ortamında Composer bağımlılıkları ve veritabanı olmadığı için Laravel runtime, migration, feature test, tarayıcı testi ve gerçek cihaz testi çalıştırılmamıştır.
