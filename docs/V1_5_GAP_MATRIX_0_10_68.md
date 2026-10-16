# V1.5 gereksinim matrisi — 0.10.68

## Bu sürümde yapılan geliştirme
- Site Builder bölüm öğesi sınırları sıkılaştırıldı: her bölümde en fazla 100 dolu satır kabul edilir; öğe başlığı/açıklaması ve slider düğme alanları boyut kontrolünden geçer. Bu sınır revizyon geri yükleme doğrulamasıyla tutarlıdır.
- Bölüm CTA ve slider bağlantıları ortak güvenli URL doğrulamasını kullanır. Geçerli HTTPS adresleri, tek eğik çizgiyle başlayan site içi yollar ve `#` bağlantıları kabul edilir; `javascript:`, bozuk HTTPS adresleri, URL kullanıcı bilgileri, kontrol karakterleri ve ters eğik çizgi reddedilir.
- Üç Feature test kaynağı ve Release Gate statik kontrolü eklendi; yeni migration yoktur. Laravel Feature testleri bu ortamda çalıştırılmış sayılmaz.
- Site Builder sayfa bölümlerine Müşteri Görüşleri (`testimonials`) ve İstatistikler (`stats`) türleri eklendi. Yönetici formunda her türün satır formatı açıklanır; yayın görünümünde erişilebilir, mobil uyumlu bileşenler olarak gösterilir. Metinler Blade escaped output ile sunulur.
- Site Builder menü/navigasyon ekranında mevcut header/footer bağlantısını düzenleme eklendi: görünen ad, konum, yayınlanmış sayfa veya HTTPS hedefi ve sıra güncellenebilir. Hedef sayfa transaction içinde kilitlenerek yeniden doğrulanır; düzenleme ve audit kaydı atomiktir.
- Site Builder `contact` bölümü artık genel ayarlardaki e-posta ve telefon için tıklanabilir `mailto:` ve temizlenmiş `tel:` bağlantıları gösterir. Footer da daha önce ayarlanabilen telefon bilgisini görünür kılar. Mobilde bağlantılar sarılır ve erişilebilir dokunma alanı kullanır.
- Site Builder genel ayarlarına isteğe bağlı varsayılan sosyal paylaşım görseli eklenmiştir. PNG/JPEG/WebP ve 5 MB sınırı uygulanır; dosya özel depolamada saklanır ve doğrulanmış `/brand/social-image` rotasından sunulur.
- Yayındaki sayfanın kendi sosyal görseli varsa o görsel önceliklidir. Sayfaya özel görsel yoksa genel varsayılan kullanılır; Open Graph `og:image`, `og:image:alt`, Twitter `twitter:image` ve `summary_large_image` etiketleri üretilir.
- Görselin değiştirilmesi/kaldırılması, ayar kaydı ve audit kaydı akışına eklendi. Eski logo/favicon ayarları korunur; SVG reddedilir.
- Feature test kaynakları, statik Release Gate kontrolleri ve bu belge eklendi. Bu ortamda Composer/Laravel runtime yoksa Feature testleri çalıştırılmış sayılmaz.

## Kaynak kodunda mevcut, gerçek ortamda doğrulanmayı bekleyen
- Deneyim oluşturma/yayımlama, medya ve QR/NFC akışı.
- QR SVG önizleme/ürün etiketi yazdırma ve NFC için aynı kalıcı adresi kopyalama.
- Site Builder sayfa bazlı sosyal görseli ve artık genel varsayılan sosyal görsel fallback'i.
- Site Builder, Demo Engine, kullanım hakları ve video işleme entegrasyonları.

## Kalan kritik doğrulamalar
1. Plesk PHP 8.3.34 ve gerçek MariaDB üzerinde Composer kurulumu ve `php artisan test`.
2. `.env`/APP_URL/HTTPS/izinler/cron/queue yapılandırması.
3. Süper Admin girişi ve içerik→medya→yayın→QR uçtan uca akışı.
4. Gerçek telefonda QR tarama; NFC etikete URL yazma ve okutma.
5. Gerçek yazıcı veya hedef etiket boyutunda QR baskı testi.
6. Yedekleme ve geri alma denemesi.
7. Güvenli Süper Admin sürüm güncelleme modülü: ayrıca tamamlanmalı ve test edilmeli.
8. Site Builder'ın V1.5 bileşen kapsamı ve masaüstü/mobil kabulü.

## Sürüm politikası
Statik kontrollerin geçmesi canlıya çıkış onayı değildir. Gerçek sunucu, yazıcı ve telefon testleri yapılmadan uygulama “çalışır üretim sistemi” olarak ilan edilmemelidir.
