# QR/NFC genel yönlendirme tutarlılığı — 0.10.29

## Değişiklik

Genel QR yönlendirmesi artık QR kaydını ve hedef deneyimi aynı veritabanı işlemi içinde yeniden okur. QR satırı önce, deneyim satırı sonra `lockForUpdate()` ile kilitlenir; bağlantının hâlâ etkin olduğu ve deneyimin hâlâ herkese açık olduğu kontrol edilir.

Yönlendirme sayacı ve `last_scanned_at` yalnızca geçerli bir genel hedef URL üretilebildiğinde güncellenir. Eski/bozuk bir deneyim kaydında `public_id` eksikse istek `404` ile biter ve tarama sayacı değişmez.

## Güvenlik ve tutarlılık etkisi

- QR devre dışı bırakma işlemiyle eşzamanlı yönlendirme yarışı, QR satırı üzerindeki kilit ile sıralanır.
- Hedef deneyim silinmiş, yayında değil veya yayın zamanı gelmemişse ziyaret sayılmaz.
- Geçerli yayın durumuna rağmen `publicUrl()` boş dönerse boş/geçersiz yönlendirme oluşturulmaz.
- Ziyaretçi IP adresi veya User-Agent bu değişiklikle kaydedilmez.

## Doğrulama sınırı

Bir regresyon Feature test kaynağı eklendi: `public_id` eksik hedefin `404` vermesi ve sayaç/zaman damgasını değiştirmemesi. Bu çalışma alanında Composer ve `vendor/` mevcut olmadığından Laravel Feature testi çalıştırılmadı. Veritabanı kilidi davranışı gerçek staging veritabanında ayrıca sınanmalıdır.
