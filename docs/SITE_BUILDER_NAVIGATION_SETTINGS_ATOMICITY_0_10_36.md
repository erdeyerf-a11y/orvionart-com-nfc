# Site Builder ayar ve menü işlemlerinde atomiklik — 0.10.36

## Kapsam

Bu sürüm, Site Builder genel ayarları ve gezinme menüsü üzerindeki veritabanı değişiklikleri ile audit kayıtlarının tutarlı kalmasını hedefler.

- Genel ayarlar satırı yoksa varsayılanla oluşturulur; ardından ayar satırı transaction içinde `lockForUpdate()` ile yeniden okunur.
- `site_settings.updated` audit olayı ve ayar değişikliği aynı transaction içinde yazılır.
- Menü öğesi oluşturma, etkinleştirme/devre dışı bırakma ve silme işlemleri kendi audit olayıyla aynı transaction içinde yapılır.
- Sayfaya bağlı menü öğesi oluşturulurken hedef sayfanın hâlâ herkese açık olduğu ve header için `published_show_in_menu` koşulunu karşıladığı işlem içinde kilit altında tekrar kontrol edilir. Ön kontrol kullanıcıya erken hata vermek için korunur; işlem içi kontrol yarış durumuna karşı son doğrulamadır.
- Toggle işlemi route binding ile gelen potansiyel olarak eski model değerini kullanmaz; kilitli, güncel satırdan hareket eder.
- Menü sıralaması aynı konumdaki kayıtları sıralı şekilde kilitleyip bu güncel kayıt kümesi üzerinde işlem yapar.

## Regresyon test kaynakları

`tests/Feature/SiteBuilderTest.php` içinde:

- `test_site_settings_update_and_audit_are_atomic_when_audit_write_fails`
- `test_navigation_toggle_uses_locked_current_state_and_audits_the_committed_value`
- `test_navigation_delete_rolls_back_when_audit_write_fails`

Testler audit oluşturma sırasında hata enjekte ederek transaction rollback davranışını doğrulamayı amaçlar. Bu geliştirme ortamında `composer`, `vendor/autoload.php` ve Laravel runtime bulunmadığından Feature testleri çalıştırılmadı; yalnızca PHP sözdizimi ve statik teslimat kontrolleri çalıştırılabilir.

## Sınırlar

Bu sürüm canlı sunucuya dağıtılmadı. Eşzamanlı istek davranışı gerçek MySQL/staging ortamında ayrıca doğrulanmalıdır. Bu çalışma kullanım hakkı, ödeme, içerik yayınlama politikası veya kullanıcı rolü kurallarını değiştirmez.
