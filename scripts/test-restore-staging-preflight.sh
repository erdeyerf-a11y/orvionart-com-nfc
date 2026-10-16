#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT INT TERM

BACKUP="$TMP/backup"
FIXTURE="$TMP/fixture"
mkdir -p "$BACKUP" "$FIXTURE/storage/app/private" "$TMP/bin"
printf '%s\n' '-- ORVIONART disposable backup test fixture' > "$BACKUP/database.sql"
tar -czf "$BACKUP/media-storage.tar.gz" -C "$FIXTURE" storage/app/private
cat > "$BACKUP/MANIFEST.txt" <<'MANIFEST'
application=ORVIONART
backup_format=2
version=0.10.test
created_at_utc=20261016T000000Z
database_engine=mysql
database_host=fixture.invalid
database_name=orvionart_staging_source_fixture
contains_env_file=no
contains_vendor_directory=no
restore_policy=staging_only_without_explicit_approval
MANIFEST
(cd "$BACKUP" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
cat > "$TMP/bin/mysql" <<'MYSQL'
#!/usr/bin/env bash
if [[ -v FAKE_MYSQL_RESULT ]]; then printf '%s\n' "$FAKE_MYSQL_RESULT"; else printf 'orvionart_staging_restore_test\t0\n'; fi
MYSQL
chmod 755 "$TMP/bin/mysql"
mkdir -p "$TMP/restore-parent"
export PATH="$TMP/bin:$PATH"
export RESTORE_TARGET_DB=orvionart_staging_restore_test
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export RESTORE_STAGING_ROOT="$TMP/restore-parent/app"
export RESTORE_DB_USER=fixture_user
export RESTORE_DB_PASSWORD='fixture-password-not-real'
export RESTORE_DB_HOST=127.0.0.1
export RESTORE_DB_PORT=3306

expect_pass() {
  local name="$1"; shift
  if "$@" >/dev/null 2>&1; then printf 'PASS: %s\n' "$name"; else printf 'FAIL: %s\n' "$name" >&2; exit 1; fi
}
expect_fail() {
  local name="$1"; shift
  if "$@" >/dev/null 2>&1; then printf 'FAIL: %s\n' "$name" >&2; exit 1; else printf 'PASS: %s\n' "$name"; fi
}
run_preflight() { bash "$ROOT/scripts/restore-staging-preflight.sh" "$BACKUP"; }

expect_pass 'valid backup and empty staging database accepted' run_preflight
export RESTORE_CONFIRM_TARGET_DB=wrong_name
expect_fail 'target confirmation mismatch rejected' run_preflight
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export RESTORE_TARGET_DB=orvionart_live_database
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
expect_fail 'non-staging database name rejected' run_preflight
export RESTORE_TARGET_DB=orvionart_staging_source_fixture
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
expect_fail 'source and target database collision rejected' run_preflight
export RESTORE_TARGET_DB=orvionart_staging_restore_test
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export FAKE_MYSQL_RESULT=$'orvionart_staging_restore_test\t3'
expect_fail 'non-empty target database rejected' run_preflight
export FAKE_MYSQL_RESULT=$'unexpected_database\t0'
expect_fail 'database identity mismatch rejected' run_preflight
unset FAKE_MYSQL_RESULT
export RESTORE_STAGING_ROOT="$ROOT/unsafe-restore-target"
expect_fail 'application-root destination rejected' run_preflight
export RESTORE_STAGING_ROOT="$TMP/restore-parent/app"
mkdir -p "$RESTORE_STAGING_ROOT"
printf 'keep' > "$RESTORE_STAGING_ROOT/not-empty.txt"
expect_fail 'non-empty restore directory rejected' run_preflight
rm -rf "$RESTORE_STAGING_ROOT"
export RESTORE_STAGING_ROOT="$TMP/restore-parent/app"
export RESTORE_DB_PASSWORD=''
expect_fail 'missing password rejected' run_preflight
printf 'PASS: restore staging preflight regression suite completed.\n'
