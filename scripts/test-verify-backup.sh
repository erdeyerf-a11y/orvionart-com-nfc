#!/usr/bin/env bash
# Regression tests for backup verification. Uses temporary fixtures only; no DB access.
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
command -v tar >/dev/null 2>&1 || { echo 'SKIP: tar bulunamadı'; exit 2; }
command -v sha256sum >/dev/null 2>&1 || { echo 'SKIP: sha256sum bulunamadı'; exit 2; }
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
make_fixture() {
  local dir="$1" mode="$2"
  mkdir -p "$dir/source/storage/app/private" "$dir/source/storage/app/public"
  printf 'SQL fixture\n' > "$dir/database.sql"
  printf 'private fixture\n' > "$dir/source/storage/app/private/a.bin"
  if [[ "$mode" == 'traversal' ]]; then
    mkdir -p "$dir/evil"
    printf 'evil\n' > "$dir/evil/outside.txt"
    tar -czf "$dir/media-storage.tar.gz" -C "$dir/source" storage/app/private -C "$dir/evil" ../outside.txt 2>/dev/null || {
      # GNU tar rejects some traversal spellings; create an archive with an absolute name instead.
      tar -czf "$dir/media-storage.tar.gz" -C "$dir/source" storage/app/private
      tar -rzf "$dir/media-storage.tar.gz" -C "$dir/evil" outside.txt 2>/dev/null || true
    }
  else
    tar -czf "$dir/media-storage.tar.gz" -C "$dir/source" storage/app/private storage/app/public
  fi
  cat > "$dir/MANIFEST.txt" <<'MANIFEST'
application=ORVIONART
version=0.10.20
created_at_utc=20261015T000000Z
database_engine=mysql
database_host=127.0.0.1
database_name=orvionart_test
contains_env_file=no
restore_policy=staging_only_without_explicit_approval
MANIFEST
  (cd "$dir" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
}

SAFE="$TMP/safe"
mkdir -p "$SAFE"
make_fixture "$SAFE" safe
if bash "$ROOT/scripts/verify-backup.sh" "$SAFE" >/dev/null; then echo 'PASS: valid backup fixture accepted'; else echo 'FAIL: valid backup fixture rejected' >&2; exit 1; fi

LEGACY9="$TMP/legacy9"
cp -a "$SAFE" "$LEGACY9"
cat >> "$LEGACY9/MANIFEST.txt" <<'MANIFEST'
contains_vendor_directory=no
MANIFEST
(cd "$LEGACY9" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
if bash "$ROOT/scripts/verify-backup.sh" "$LEGACY9" >/dev/null; then echo 'PASS: legacy 9-key manifest accepted'; else echo 'FAIL: legacy 9-key manifest rejected' >&2; exit 1; fi

BADSUM="$TMP/badsum"
cp -a "$SAFE" "$BADSUM"
printf '%064d  ../outside.txt\n' 0 >> "$BADSUM/SHA256SUMS"
if bash "$ROOT/scripts/verify-backup.sh" "$BADSUM" >/dev/null 2>&1; then echo 'FAIL: unsafe checksum list accepted' >&2; exit 1; else echo 'PASS: unsafe checksum list rejected'; fi


BADTAR="$TMP/badtar"
cp -a "$SAFE" "$BADTAR"
mkdir -p "$BADTAR/evil"
printf 'outside\n' > "$BADTAR/evil/outside.txt"
# GNU tar preserves the traversal spelling in the member name while warning about it.
tar -czf "$BADTAR/media-storage.tar.gz" --transform='s|outside.txt|../outside.txt|' -C "$BADTAR/evil" outside.txt 2>/dev/null
(cd "$BADTAR" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
if bash "$ROOT/scripts/verify-backup.sh" "$BADTAR" >/dev/null 2>&1; then echo 'FAIL: archive path traversal accepted' >&2; exit 1; else echo 'PASS: archive path traversal rejected'; fi


BADLINK="$TMP/badlink"
cp -a "$SAFE" "$BADLINK"
ln -s ../../../../outside.txt "$BADLINK/source/storage/app/private/link"
tar -czf "$BADLINK/media-storage.tar.gz" -C "$BADLINK/source" storage/app/private storage/app/public
(cd "$BADLINK" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
if bash "$ROOT/scripts/verify-backup.sh" "$BADLINK" >/dev/null 2>&1; then echo 'FAIL: archive symlink accepted' >&2; exit 1; else echo 'PASS: archive symlink rejected'; fi

BADMANIFEST="$TMP/badmanifest"
cp -a "$SAFE" "$BADMANIFEST"
sed -i 's/contains_env_file=no/contains_env_file=yes/' "$BADMANIFEST/MANIFEST.txt"
(cd "$BADMANIFEST" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
if bash "$ROOT/scripts/verify-backup.sh" "$BADMANIFEST" >/dev/null 2>&1; then echo 'FAIL: manifest claiming .env included accepted' >&2; exit 1; else echo 'PASS: unsafe backup manifest rejected'; fi

# New format-2 manifests must declare both excluded runtime dependencies.
FORMAT2="$TMP/format2"
cp -a "$SAFE" "$FORMAT2"
cat > "$FORMAT2/MANIFEST.txt" <<'MANIFEST'
application=ORVIONART
backup_format=2
version=0.10.70
created_at_utc=20261016T100000Z
database_engine=mysql
database_host=127.0.0.1
database_name=orvionart_test
contains_env_file=no
contains_vendor_directory=no
restore_policy=staging_only_without_explicit_approval
MANIFEST
(cd "$FORMAT2" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
if bash "$ROOT/scripts/verify-backup.sh" "$FORMAT2" >/dev/null; then echo 'PASS: format-2 manifest accepted'; else echo 'FAIL: valid format-2 manifest rejected' >&2; exit 1; fi

reject_manifest() {
  local name="$1"
  local body="$2"
  local dir="$TMP/$name"
  cp -a "$FORMAT2" "$dir"
  printf '%s\n' "$body" > "$dir/MANIFEST.txt"
  (cd "$dir" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
  if bash "$ROOT/scripts/verify-backup.sh" "$dir" >/dev/null 2>&1; then
    echo "FAIL: $name manifest accepted" >&2; exit 1
  else
    echo "PASS: $name manifest rejected"
  fi
}

reject_manifest duplicate-key "$(cat "$FORMAT2/MANIFEST.txt")"$'\n'version=0.10.70
reject_manifest unknown-key "$(cat "$FORMAT2/MANIFEST.txt")"$'\n'operator_note=unexpected
reject_manifest bad-timestamp "$(sed 's/created_at_utc=.*/created_at_utc=yesterday/' "$FORMAT2/MANIFEST.txt")"
reject_manifest bad-engine "$(sed 's/database_engine=mysql/database_engine=postgresql/' "$FORMAT2/MANIFEST.txt")"
reject_manifest vendor-included "$(sed 's/contains_vendor_directory=no/contains_vendor_directory=yes/' "$FORMAT2/MANIFEST.txt")"
reject_manifest format2-missing-vendor "$(grep -v '^contains_vendor_directory=' "$FORMAT2/MANIFEST.txt")"
echo 'ORVIONART BACKUP VERIFY REGRESSION TESTS: PASS'
