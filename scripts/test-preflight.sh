#!/usr/bin/env bash
# Isolated regression tests for scripts/preflight.php; creates only temporary fixture files.
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PHP_BIN="$(command -v php || true)"
if [[ -z "$PHP_BIN" ]]; then echo 'BLOCKED: php bulunamadı' >&2; exit 2; fi
TMP="$(mktemp -d)" || exit 2
trap 'rm -rf "$TMP"' EXIT
FIXTURE="$TMP/project"
mkdir -p "$FIXTURE/scripts" "$FIXTURE/public" "$FIXTURE/storage" "$FIXTURE/bootstrap/cache" "$TMP/bin"
cp "$ROOT/scripts/preflight.php" "$FIXTURE/scripts/preflight.php"
printf '{"require":{}}\n' > "$FIXTURE/composer.json"
printf '#!/usr/bin/env php\n<?php\n' > "$FIXTURE/artisan"
printf '# fixture\n' > "$FIXTURE/.env.example"
printf 'RewriteEngine On\n' > "$FIXTURE/public/.htaccess"
printf '#!/bin/sh\nexit 0\n' > "$TMP/bin/composer"
chmod +x "$TMP/bin/composer"
cat > "$FIXTURE/.env" <<'ENV'
APP_ENV=staging
APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
APP_DEBUG=false
APP_URL=https://staging.example.invalid
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
ORVIONART_PAYMENTS_ENABLED=false
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=orvionart_test
DB_USERNAME=orvionart_test
DB_PASSWORD=not-a-real-secret
ENV
chmod 600 "$FIXTURE/.env"

OUT="$TMP/secure.out"
if PATH="$TMP/bin:$PATH" "$PHP_BIN" "$FIXTURE/scripts/preflight.php" >"$OUT" 2>&1; then
  if grep -q '\[BLOCKER\]' "$OUT"; then echo 'FAIL: güvenli fixture beklenmedik BLOCKER üretti' >&2; cat "$OUT" >&2; exit 1; fi
  if ! grep -q '\[PASS\] Ödeme entegrasyonu' "$OUT"; then echo 'FAIL: ödeme kapalı kontrolü görünmedi' >&2; cat "$OUT" >&2; exit 1; fi
  echo 'PASS: güvenli yapılandırma BLOCKER üretmedi'
else
  echo 'FAIL: güvenli fixture başarısız oldu' >&2; cat "$OUT" >&2; exit 1
fi

# Turn on debug and payments; both must be blocked. The password must never appear in output.
cat >> "$FIXTURE/.env" <<'ENV'
APP_DEBUG=true
ORVIONART_PAYMENTS_ENABLED=true
ENV
OUT="$TMP/insecure.out"
if PATH="$TMP/bin:$PATH" "$PHP_BIN" "$FIXTURE/scripts/preflight.php" >"$OUT" 2>&1; then
  echo 'FAIL: güvensiz yapılandırma sıfır koduyla geçti' >&2; cat "$OUT" >&2; exit 1
fi
if ! grep -q 'APP_DEBUG açık' "$OUT" || ! grep -q 'ORVIONART_PAYMENTS_ENABLED etkin görünüyor' "$OUT"; then
  echo 'FAIL: beklenen güvenlik engelleri bulunamadı' >&2; cat "$OUT" >&2; exit 1
fi
if grep -q 'not-a-real-secret' "$OUT"; then echo 'FAIL: gizli değer çıktı içine sızdı' >&2; exit 1; fi
echo 'PASS: APP_DEBUG ve ödeme açıkken BLOCKER üretildi; sırlar rapora sızmadı'

# A copied template with database placeholders must be blocked even without --check-db.
python3 - "$FIXTURE/.env" <<'PYENV'
from pathlib import Path
import sys
p = Path(sys.argv[1])
s = p.read_text()
s = s.replace('DB_USERNAME=orvionart_test', 'DB_USERNAME=replace_with_database_user')
s = s.replace('DB_PASSWORD=not-a-real-secret', 'DB_PASSWORD=replace_with_a_unique_database_password')
p.write_text(s)
PYENV
OUT="$TMP/placeholders.out"
if PATH="$TMP/bin:$PATH" "$PHP_BIN" "$FIXTURE/scripts/preflight.php" >"$OUT" 2>&1; then
  echo 'FAIL: DB yer tutucuları --check-db olmadan engellenmedi' >&2; cat "$OUT" >&2; exit 1
fi
if ! grep -q 'Veritabanı yapılandırması' "$OUT"; then echo 'FAIL: DB yer tutucu engeli raporlanmadı' >&2; cat "$OUT" >&2; exit 1; fi
if grep -q 'replace_with_database_user\|replace_with_a_unique_database_password' "$OUT"; then echo 'FAIL: DB yer tutucu/gizli değer rapora sızdı' >&2; exit 1; fi
echo 'PASS: DB yer tutucuları bağlantı denemesi olmadan engelleniyor ve değerler gizli kalıyor'
echo 'ORVIONART PREFLIGHT REGRESSION TESTS: PASS'
