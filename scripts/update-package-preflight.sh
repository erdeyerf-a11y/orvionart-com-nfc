#!/usr/bin/env bash
# ORVIONART controlled update package preflight.
# Read-only: verifies an archive and its extracted source in a temporary directory.
# It never deploys files, writes to the application database, or runs migrations.
set -Eeuo pipefail

usage() {
  echo 'Kullanım: bash scripts/update-package-preflight.sh <release.zip> <expected-sha256>' >&2
  exit 2
}
[[ $# -eq 2 ]] || usage
ARCHIVE="$1"
EXPECTED_SHA="${2,,}"
[[ -f "$ARCHIVE" && -r "$ARCHIVE" ]] || { echo 'FAIL: ZIP dosyası bulunamadı veya okunamıyor.' >&2; exit 1; }
[[ "$EXPECTED_SHA" =~ ^[a-f0-9]{64}$ ]] || { echo 'FAIL: Beklenen SHA-256 64 küçük/büyük hex karakteri olmalı.' >&2; exit 2; }
for cmd in sha256sum unzip zipinfo php find awk grep sort; do
  command -v "$cmd" >/dev/null 2>&1 || { echo "FAIL: Gerekli komut bulunamadı: $cmd" >&2; exit 2; }
done

ACTUAL_SHA="$(sha256sum -- "$ARCHIVE" | awk '{print $1}')"
[[ "$ACTUAL_SHA" == "$EXPECTED_SHA" ]] || { echo 'FAIL: ZIP SHA-256 eşleşmiyor.' >&2; exit 1; }
echo 'PASS: ZIP SHA-256 doğrulandı.'
unzip -tq "$ARCHIVE" >/dev/null || { echo 'FAIL: ZIP bütünlüğü bozuk.' >&2; exit 1; }
echo 'PASS: ZIP arşiv bütünlüğü doğrulandı.'

TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT INT TERM
LIST="$TMP/entries.txt"
zipinfo -1 "$ARCHIVE" > "$LIST"
[[ -s "$LIST" ]] || { echo 'FAIL: ZIP boş.' >&2; exit 1; }
COUNT="$(wc -l < "$LIST" | tr -d ' ')"
(( COUNT <= 20000 )) || { echo 'FAIL: ZIP 20.000 girdilik güvenlik sınırını aşıyor.' >&2; exit 1; }

# Reject path traversal, absolute paths, backslash paths, duplicate paths and unsafe runtime/secrets.
if awk '
  BEGIN { bad=0 }
  {
    p=$0
    if (p ~ /^\// || p ~ /^[A-Za-z]:/ || p ~ /\\/ || p ~ /(^|\/)\.\.(\/|$)/ || p ~ /(^|\/)\.($|\/)/) {
      print "FAIL: Güvensiz ZIP yolu: " p > "/dev/stderr"; bad=1
    }
    if (seen[p]++) { print "FAIL: Yinelenen ZIP yolu: " p > "/dev/stderr"; bad=1 }
    if (p == ".env" || p ~ /(^|\/)\.env\.[^/]+$/ && p !~ /(^|\/)\.env\.example$/) {
      print "FAIL: Gizli ortam dosyası ZIP içinde yasak: " p > "/dev/stderr"; bad=1
    }
    if (p ~ /(^|\/)(vendor|node_modules|\.git)(\/|$)/ || p ~ /(^|\/)storage\/(logs|framework\/cache|framework\/sessions|framework\/views)(\/|$)/ || p ~ /(^|\/)bootstrap\/cache\/[^.]/) {
      print "FAIL: Bağımlılık veya runtime içeriği ZIP içinde yasak: " p > "/dev/stderr"; bad=1
    }
  }
  END { exit bad ? 1 : 0 }
' "$LIST"; then
  echo 'PASS: ZIP yolları, tekrarlar ve hassas/runtime dosyaları güvenli.'
else
  exit 1
fi

# The archive must have one common top-level directory and match its VERSION.
TOPS="$(awk -F/ 'NF {print $1}' "$LIST" | sort -u)"
[[ "$(printf '%s\n' "$TOPS" | wc -l | tr -d ' ')" -eq 1 ]] || { echo 'FAIL: ZIP tek bir kök klasör içermeli.' >&2; exit 1; }
TOP="$(printf '%s\n' "$TOPS" | head -n1)"
[[ "$TOP" =~ ^ORVIONART_STAGE0_1_0_[0-9]+_[0-9]+$ ]] || { echo 'FAIL: Kök klasör sürüm adlandırması beklenmiyor.' >&2; exit 1; }

# Reject symbolic links in ZIP entries before extraction.
while IFS= read -r entry; do
  [[ -z "$entry" ]] && continue
  kind="$(zipinfo -l "$ARCHIVE" "$entry" 2>/dev/null | awk 'NR==1 {print substr($1,1,1); exit}')"
  if [[ "$kind" == 'l' ]]; then echo "FAIL: Sembolik bağlantı ZIP içinde yasak: $entry" >&2; exit 1; fi
done < "$LIST"

mkdir -p "$TMP/unpack"
unzip -q "$ARCHIVE" -d "$TMP/unpack"
SRC="$TMP/unpack/$TOP"
[[ -d "$SRC" && -f "$SRC/VERSION" && -f "$SRC/composer.json" && -f "$SRC/RELEASE_MANIFEST.json" && -f "$SRC/scripts/release-manifest.php" && -f "$SRC/artisan" ]] || {
  echo 'FAIL: Gerekli Laravel/release dosyalarından biri eksik.' >&2; exit 1;
}
VERSION="$(tr -d '\r\n' < "$SRC/VERSION")"
EXPECTED_DIR="ORVIONART_STAGE0_1_0_${VERSION#0.}"
EXPECTED_DIR="${EXPECTED_DIR//./_}"
[[ "$VERSION" =~ ^0\.[0-9]+\.[0-9]+$ && "$TOP" == "$EXPECTED_DIR" ]] || { echo 'FAIL: ZIP kök klasörü ile VERSION uyuşmuyor.' >&2; exit 1; }
php -r '$c=json_decode(file_get_contents($argv[1]),true); exit(is_array($c) && isset($c["require"]["laravel/framework"]) ? 0 : 1);' "$SRC/composer.json" || { echo 'FAIL: composer.json geçersiz veya Laravel bağımlılığı yok.' >&2; exit 1; }
(cd "$SRC" && php scripts/release-manifest.php verify) || { echo 'FAIL: Kaynak manifesti doğrulanamadı.' >&2; exit 1; }
echo "PASS: Sürüm ve kaynak manifesti doğrulandı ($VERSION)."

PHP_FAIL=0
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null 2>&1; then echo "FAIL: PHP sözdizimi: ${file#$SRC/}" >&2; PHP_FAIL=1; fi
done < <(find "$SRC" -type d \( -name vendor -o -name node_modules -o -name .git \) -prune -o -type f -name '*.php' -print0)
[[ "$PHP_FAIL" -eq 0 ]] || exit 1
echo 'PASS: ZIP içindeki PHP kaynaklarının sözdizimi doğrulandı.'
echo "SONUÇ: PREFLIGHT_PASS — sürüm $VERSION; dağıtım yapılmadı, veritabanına dokunulmadı."
