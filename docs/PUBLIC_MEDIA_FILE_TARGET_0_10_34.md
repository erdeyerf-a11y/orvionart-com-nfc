# Genel medya sunumunda normal dosya hedefi — 0.10.34

## Değişiklik

`MediaDeliveryController` artık dosyanın özel `local` disk kökü içinde çözülmesini ve `is_file()` ile normal dosya olduğunun doğrulanmasını birlikte şart koşar. Böylece yönetilen klasör altındaki bir dizin veya normal dosya olmayan hedef, `response()->file()` çağrısına ulaşmadan `404` ile reddedilir.

## Neden

Bir veritabanı kaydı güvenli bir göreli yola ve izin verilen MIME türüne sahip olsa da dosya sistemi hedefi normal bir dosya olmayabilir. Kök dizin sınır kontrolü tek başına bunu garanti etmez.

## Regresyon testi

`tests/Feature/QrAndMediaTest.php` içindeki `test_public_media_rejects_a_directory_even_when_its_path_is_inside_managed_media_scope` testi, yönetilen yol altında dizin oluşturur ve genel teslimin `404` vermesini doğrular.

## Doğrulama sınırı

Bu ortamda Composer ve `vendor/autoload.php` bulunmadığından Laravel Feature testi çalıştırılmadı. Kaynak test ve PHP sözdizimi kontrolü, gerçek staging kabulünün yerine geçmez. Gerçek sunucuda dosya sistemi izinleri ve eşzamanlı dosya değiştirme davranışı ayrıca sınanmalıdır.
