#!/usr/bin/env bash
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf -- "$TMP"' EXIT INT TERM
mkdir -p "$TMP/bin"
cat > "$TMP/bin/mysql" <<'MYSQL'
#!/usr/bin/env bash
query=""
db=""
while (($#)); do
  case "$1" in
    --execute=*) query="${1#--execute=}" ;;
    --execute) shift; query="${1:-}" ;;
    --defaults-extra-file=*) ;;
    --batch|--skip-column-names) ;;
    *) db="$1" ;;
  esac
  shift || true
done
if [[ "$query" == *"@@hostname"* ]]; then
  printf '%s\t%s\t%s\t%s\t%s\n' "${FAKE_DB_NAME:-orvionart_staging_verify_test}" "${FAKE_DB_HOSTNAME:-stage-db}" "${FAKE_DB_PORT:-3306}" "${FAKE_READ_ONLY:-0}" "${FAKE_TABLE_COUNT:-22}"
elif [[ "$query" == *"information_schema.tables"* ]]; then
  printf '%s\n' migrations users experiences media_assets qr_links site_pages site_settings sessions cache jobs
elif [[ "$query" == *"COUNT(*) FROM migrations"* ]]; then
  printf '%s\n' "${FAKE_MIGRATION_COUNT:-18}"
else
  exit 1
fi
MYSQL
chmod 755 "$TMP/bin/mysql"
export PATH="$TMP/bin:$PATH"
export RESTORE_TARGET_DB=orvionart_staging_verify_test
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export RESTORE_DB_USER=fixture_user
export RESTORE_DB_PASSWORD='fixture-password-not-real'

run_check() { bash "$ROOT/scripts/verify-restored-staging.sh"; }
expect_pass() { local name="$1"; shift; if "$@" >/dev/null 2>&1; then printf 'PASS: %s\n' "$name"; else printf 'FAIL: %s\n' "$name" >&2; exit 1; fi; }
expect_fail() { local name="$1"; shift; if "$@" >/dev/null 2>&1; then printf 'FAIL: %s\n' "$name" >&2; exit 1; else printf 'PASS: %s\n' "$name"; fi; }

expect_pass 'restored staging schema accepted' run_check
export FAKE_DB_NAME=wrong_database
expect_fail 'database identity mismatch rejected' run_check
export FAKE_DB_NAME=orvionart_staging_verify_test
export FAKE_READ_ONLY=1
expect_fail 'read-only database rejected' run_check
unset FAKE_READ_ONLY
export FAKE_TABLE_COUNT=0
expect_fail 'database without tables rejected' run_check
export FAKE_TABLE_COUNT=22
export FAKE_MIGRATION_COUNT=0
expect_fail 'empty migration history rejected' run_check
export FAKE_MIGRATION_COUNT=18
export RESTORE_CONFIRM_TARGET_DB=wrong_name
expect_fail 'target confirmation mismatch rejected' run_check
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export RESTORE_TARGET_DB=orvionart_live_database
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
expect_fail 'non-staging database name rejected' run_check
export RESTORE_TARGET_DB=orvionart_staging_verify_test
export RESTORE_CONFIRM_TARGET_DB="$RESTORE_TARGET_DB"
export FAKE_MISSING_TABLE=1
# A dedicated missing-table fixture is supplied by swapping the mock output.
cat > "$TMP/bin/mysql" <<'MYSQL2'
#!/usr/bin/env bash
query=""
while (($#)); do case "$1" in --execute=*) query="${1#--execute=}";; --execute) shift; query="${1:-}";; esac; shift || true; done
if [[ "$query" == *"@@hostname"* ]]; then printf 'orvionart_staging_verify_test\tstage-db\t3306\t0\t21\n'
elif [[ "$query" == *"information_schema.tables"* ]]; then printf '%s\n' migrations users experiences media_assets qr_links site_pages sessions cache jobs
elif [[ "$query" == *"COUNT(*) FROM migrations"* ]]; then printf '18\n'
else exit 1; fi
MYSQL2
chmod 755 "$TMP/bin/mysql"
expect_fail 'missing required application table rejected' run_check
printf 'PASS: restored staging verification regression suite completed.\n'
