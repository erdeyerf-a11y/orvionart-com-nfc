# Platform Integrity: Saklanan Site Builder sürümlerindeki medya referansları (0.10.41)

`orvionart:inspect-platform` denetimi artık Site Builder'ın saklanan `site_page_revisions` snapshot'larını da salt okunur olarak tarar.

## Neden gerekli?

Bir sayfanın mevcut taslağı veya yayındaki içeriği eski bir görseli kullanmıyor olabilir; ancak geçmiş sürüm geri yüklenebilir bir snapshot olarak o görsele hâlâ referans verebilir. Dosya veya medya kaydı haricî bir işlemle kaybolursa yalnızca mevcut taslağı ve yayını denetlemek bozuk geri alma durumunu fark etmeyebilir.

## Denetim davranışı

- Revision kayıtları `chunkById(100)` ile okunur; bütün geçmiş tek seferde belleğe yüklenmez.
- Snapshot içindeki bölüm görselleri, öğe/kart görselleri ve sosyal paylaşım görselleri denetlenir.
- Eksik medya kaydı, yanlış sayfa sahipliği, desteklenmeyen MIME türü ve depolama dosyasının bulunamaması ayrı problem kodlarıyla raporlanır.
- JSON raporuna `historical_revision_media_reference_problems` sayacı ve aynı adlı sorun listesi eklenir.
- Sorun varsa denetim, diğer bütünlük uyarılarında olduğu gibi başarısız çıkış kodu döndürür.
- Komut kayıtları veya dosyaları değiştirmez; otomatik onarım ya da silme yapmaz.

## Doğrulama sınırı

İki Laravel Feature test kaynağı eksik medya kaydı ile yalnızca geçmiş snapshot'ta kullanılan dosyanın kaybolmasını kapsar. Bu geliştirme ortamında Composer/`vendor` yoksa bu Feature testleri çalıştırılmış sayılmaz. Gerçek staging veritabanı ve dosya deposunda kabul testi ayrıca gereklidir.
