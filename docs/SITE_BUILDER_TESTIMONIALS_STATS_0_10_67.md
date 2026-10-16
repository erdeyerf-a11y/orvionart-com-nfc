# Site Builder — Müşteri Görüşleri ve İstatistikler (0.10.67)

## Amaç
Sayfa bölümlerine, ayrı ayrı sayfa şablonu kodlamadan müşteri görüşü ve sayısal başarı bilgisi eklenmesini sağlar.

## Yönetim formu
- **Müşteri Görüşleri (`testimonials`)**: her satır `Görüş metni | İsim` biçimindedir. İkinci alan boş bırakılabilir.
- **İstatistikler (`stats`)**: her satır `Değer | Açıklama` biçimindedir. Örnek: `250+ | Tamamlanan çalışma`.
- Satırlar mevcut `items_text` alanından ayrıştırılır; yeni veri tabanı alanı veya migration gerektirmez.

## Ziyaretçi görünümü
- Müşteri görüşleri `figure`, `blockquote` ve isteğe bağlı `figcaption` öğeleriyle gösterilir.
- İstatistikler değer ve açıklamadan oluşan responsive grid kartlarında gösterilir.
- İçerik Blade escaped output ile yazılır; kullanıcı girdisi HTML/JavaScript olarak çalıştırılmaz.
- Bölümler mevcut `is_active` ve görünüm varyantı kurallarına uyar.

## Doğrulama sınırı
Feature test kaynakları, form seçeneklerini ve yayımlanmış sayfanın render/escaping davranışını kapsar. Composer/Laravel runtime bu çalışma ortamında bulunmadığından testler burada çalıştırılmamıştır. Gerçek tarayıcı, mobil cihaz ve Plesk kabulü ayrıca yapılmalıdır.
