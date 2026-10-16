#!/usr/bin/env bash
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
FAIL=0
if ! command -v php >/dev/null 2>&1; then echo "BLOCKER: php komutu bulunamadı"; exit 2; fi
PHP_VERSION="$(php -r 'echo PHP_VERSION;')"
if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then echo "PASS: PHP $PHP_VERSION"; else echo "BLOCKER: PHP $PHP_VERSION; PHP 8.3+ gerekir"; FAIL=1; fi
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null 2>&1; then echo "FAIL: PHP sözdizimi: ${file#$ROOT/}"; php -l "$file"; FAIL=1; fi
done < <(find "$ROOT/app" "$ROOT/routes" "$ROOT/database" "$ROOT/bootstrap" "$ROOT/config" "$ROOT/public" "$ROOT/tests" "$ROOT/scripts" -type f -name '*.php' -print0)
if [ "$FAIL" -eq 0 ]; then echo "PASS: PHP dosyalarının sözdizimi kontrolü"; fi
if command -v bash >/dev/null 2>&1; then
  while IFS= read -r -d '' file; do
    if ! bash -n "$file"; then echo "FAIL: Bash sözdizimi: ${file#$ROOT/}"; FAIL=1; fi
  done < <(find "$ROOT/scripts" -type f -name '*.sh' -print0)
  if [ "$FAIL" -eq 0 ]; then echo "PASS: Bash betiklerinin sözdizimi kontrolü"; fi
else
  echo "INFO: bash bulunamadı; shell betikleri kontrol edilmedi."
fi
if php -r '$j=json_decode(file_get_contents($argv[1]), true); exit(is_array($j) ? 0 : 1);' "$ROOT/composer.json"; then echo "PASS: composer.json JSON"; else echo "FAIL: composer.json JSON"; FAIL=1; fi
if command -v composer >/dev/null 2>&1; then
  (cd "$ROOT" && composer validate --no-check-publish --no-interaction) || FAIL=1
else
  echo "INFO: Composer yok; composer validate ve runtime testleri çalıştırılamadı."
fi
if [ "$FAIL" -eq 0 ]; then echo "SONUÇ: Statik kontroller tamamlandı. Bu, runtime veya kabul testi değildir."; exit 0; fi
echo "SONUÇ: Hata bulundu; teslimat öncesi düzeltin."; exit 1
