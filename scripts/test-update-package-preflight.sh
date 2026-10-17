#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT INT TERM

expect_pass() { local label="$1"; shift; if "$@" >"$TMP/out" 2>&1; then echo "PASS: $label"; else cat "$TMP/out" >&2; echo "FAIL: $label" >&2; exit 1; fi; }
expect_fail() { local label="$1"; shift; if "$@" >"$TMP/out" 2>&1; then cat "$TMP/out" >&2; echo "FAIL: $label" >&2; exit 1; else echo "PASS: $label"; fi; }

# Use the current distributable source archive as a valid fixture.
VALID="$TMP/valid.zip"
SOURCE_ARCHIVE="${ORVIONART_VALID_ARCHIVE:-$ROOT/../ORVIONART_STAGE0_1_0_10_74_SERVER_READY.zip}"
if [[ -f "$SOURCE_ARCHIVE" ]]; then
  cp "$SOURCE_ARCHIVE" "$VALID"
elif git -C "$ROOT" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  # Keep the regression suite self-contained when historical ZIP fixtures are absent.
  git -C "$ROOT" archive --format=zip --prefix=ORVIONART_STAGE0_1_0_10_74/ -o "$VALID" HEAD
else
  echo "Geçerli ZIP fixture bulunamadı; ORVIONART_VALID_ARCHIVE ayarlayın." >&2
  exit 1
fi
SHA="$(sha256sum "$VALID" | awk '{print $1}')"
expect_pass 'valid release archive passes preflight' bash "$ROOT/scripts/update-package-preflight.sh" "$VALID" "$SHA"
expect_fail 'incorrect checksum rejected' bash "$ROOT/scripts/update-package-preflight.sh" "$VALID" "$(printf '0%.0s' {1..64})"
expect_fail 'malformed checksum argument rejected' bash "$ROOT/scripts/update-package-preflight.sh" "$VALID" bad

# Build a tiny archive with path traversal; it must be rejected before extraction.
mkdir -p "$TMP/evil/ORVIONART_STAGE0_1_0_10_74"
printf 'x\n' > "$TMP/evil/ORVIONART_STAGE0_1_0_10_74/VERSION"
(cd "$TMP/evil" && zip -q -r "$TMP/traversal.zip" .)
# Inject a traversal entry with zip's path argument support.
printf 'unsafe\n' > "$TMP/escape.txt"
(cd "$TMP" && zip -q "$TMP/traversal.zip" escape.txt)
# The archive entry is ordinary here; separately create a canonical traversal archive using Python's zipfile.
python3 - "$TMP/traversal.zip" <<'PY'
import sys, zipfile
p=sys.argv[1]
with zipfile.ZipFile(p,'w') as z:
    z.writestr('../escape.txt','unsafe')
PY
EVIL_SHA="$(sha256sum "$TMP/traversal.zip" | awk '{print $1}')"
expect_fail 'ZIP path traversal rejected' bash "$ROOT/scripts/update-package-preflight.sh" "$TMP/traversal.zip" "$EVIL_SHA"

# A secret-bearing archive is rejected before extraction.
python3 - "$TMP/secret.zip" <<'PY'
import sys, zipfile
with zipfile.ZipFile(sys.argv[1],'w') as z:
    z.writestr('ORVIONART_STAGE0_1_0_10_74/.env','DB_PASSWORD=do-not-use')
PY
SECRET_SHA="$(sha256sum "$TMP/secret.zip" | awk '{print $1}')"
expect_fail 'secret .env archive rejected' bash "$ROOT/scripts/update-package-preflight.sh" "$TMP/secret.zip" "$SECRET_SHA"

# A Unix symlink entry must be rejected before extraction.
python3 - "$TMP/symlink.zip" <<'PY'
import sys, zipfile
with zipfile.ZipFile(sys.argv[1],'w') as z:
    info=zipfile.ZipInfo('ORVIONART_STAGE0_1_0_10_74/link')
    info.create_system=3
    info.external_attr=(0o120777 << 16)
    z.writestr(info,'/etc/passwd')
PY
LINK_SHA="$(sha256sum "$TMP/symlink.zip" | awk '{print $1}')"
expect_fail 'ZIP symbolic link rejected' bash "$ROOT/scripts/update-package-preflight.sh" "$TMP/symlink.zip" "$LINK_SHA"
echo 'PASS: update package preflight regression suite completed.'
