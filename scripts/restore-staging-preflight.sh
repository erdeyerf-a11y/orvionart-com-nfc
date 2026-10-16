#!/usr/bin/env bash
# Read-only rehearsal preflight. Verifies a backup and an EMPTY, separate staging DB.
# It never imports SQL, extracts media, creates directories, or changes application data.
set -Eeuo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
if [[ $# -ne 1 ]]; then
  echo "Kullanım: RESTORE_TARGET_DB=... RESTORE_CONFIRM_TARGET_DB=... RESTORE_STAGING_ROOT=/web-koku-disinda/yeni-yol $0 /yedek/dizini" >&2
  exit 2
fi

BACKUP_DIR="$(realpath -- "$1")"
[[ -d "$BACKUP_DIR" ]] || { echo "BLOCKED: Yedek dizini bulunamadı." >&2; exit 2; }
bash "$APP_ROOT/scripts/verify-backup.sh" "$BACKUP_DIR" >/dev/null

for name in RESTORE_TARGET_DB RESTORE_CONFIRM_TARGET_DB RESTORE_STAGING_ROOT RESTORE_DB_USER RESTORE_DB_PASSWORD; do
  if [[ ! -v "$name" || -z "${!name}" ]]; then
    echo "BLOCKED: Gerekli staging doğrulama ayarı eksik: $name" >&2
    exit 2
  fi
done
RESTORE_DB_HOST="${RESTORE_DB_HOST:-127.0.0.1}"
RESTORE_DB_PORT="${RESTORE_DB_PORT:-3306}"
for value in "$RESTORE_TARGET_DB" "$RESTORE_CONFIRM_TARGET_DB" "$RESTORE_STAGING_ROOT" "$RESTORE_DB_USER" "$RESTORE_DB_PASSWORD" "$RESTORE_DB_HOST" "$RESTORE_DB_PORT"; do
  if [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]]; then
    echo "BLOCKED: Ayarlarda satır sonu karakteri kullanılamaz." >&2
    exit 2
  fi
done

# An explicit staging-only naming convention and a second confirmation prevent accidental live DB targeting.
if [[ ! "$RESTORE_TARGET_DB" =~ ^orvionart_staging_[A-Za-z0-9_]{1,100}$ ]]; then
  echo "BLOCKED: Hedef veritabanı adı orvionart_staging_ önekiyle başlamalı." >&2
  exit 2
fi
if [[ "$RESTORE_CONFIRM_TARGET_DB" != "$RESTORE_TARGET_DB" ]]; then
  echo "BLOCKED: Hedef veritabanı onayı eşleşmiyor." >&2
  exit 2
fi
if [[ ! "$RESTORE_DB_PORT" =~ ^[0-9]{1,5}$ ]] || (( RESTORE_DB_PORT < 1 || RESTORE_DB_PORT > 65535 )); then
  echo "BLOCKED: RESTORE_DB_PORT geçerli bir TCP portu olmalı." >&2
  exit 2
fi

SOURCE_DB="$(awk -F= '$1 == "database_name" { print substr($0, index($0, "=") + 1); exit }' "$BACKUP_DIR/MANIFEST.txt")"
if [[ -z "$SOURCE_DB" || "$RESTORE_TARGET_DB" == "$SOURCE_DB" ]]; then
  echo "BLOCKED: Kaynak ve hedef veritabanı aynı olamaz." >&2
  exit 2
fi

if [[ "$RESTORE_STAGING_ROOT" != /* ]]; then
  echo "BLOCKED: RESTORE_STAGING_ROOT mutlak bir yol olmalı." >&2
  exit 2
fi
if [[ -L "$RESTORE_STAGING_ROOT" ]]; then
  echo "BLOCKED: Staging hedefi sembolik bağlantı olamaz." >&2
  exit 2
fi
STAGING_ROOT_REAL="$(realpath -m -- "$RESTORE_STAGING_ROOT")"
case "$STAGING_ROOT_REAL/" in
  "$APP_ROOT/"|"$APP_ROOT"/*)
    echo "BLOCKED: Staging hedefi uygulama kaynak dizininin dışında olmalı." >&2
    exit 2
    ;;
  "$BACKUP_DIR/"|"$BACKUP_DIR"/*)
    echo "BLOCKED: Staging hedefi yedek dizininin dışında olmalı." >&2
    exit 2
    ;;
esac
[[ "$STAGING_ROOT_REAL" != "/" ]] || { echo "BLOCKED: Staging hedefi kök dizin olamaz." >&2; exit 2; }
PARENT_DIR="$(dirname -- "$STAGING_ROOT_REAL")"
[[ -d "$PARENT_DIR" && -w "$PARENT_DIR" ]] || { echo "BLOCKED: Staging hedefinin üst dizini mevcut ve yazılabilir olmalı." >&2; exit 2; }
if [[ -e "$STAGING_ROOT_REAL" ]]; then
  [[ -d "$STAGING_ROOT_REAL" ]] || { echo "BLOCKED: Staging hedefi bir dizin olmalı." >&2; exit 2; }
  [[ -z "$(find "$STAGING_ROOT_REAL" -mindepth 1 -maxdepth 1 -print -quit)" ]] || { echo "BLOCKED: Staging hedef dizini boş olmalı." >&2; exit 2; }
fi

command -v mysql >/dev/null 2>&1 || { echo "BLOCKED: mysql istemcisi bulunamadı." >&2; exit 2; }
if ! command -v mktemp >/dev/null 2>&1; then echo "BLOCKED: mktemp bulunamadı." >&2; exit 2; fi
TMP_DIR="$(mktemp -d)"
trap 'rm -rf -- "$TMP_DIR"' EXIT INT TERM
CNF="$TMP_DIR/client.cnf"
escape_option_value() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '%s' "$value"
}
{
  printf '[client]\n'
  printf 'user="%s"\n' "$(escape_option_value "$RESTORE_DB_USER")"
  printf 'password="%s"\n' "$(escape_option_value "$RESTORE_DB_PASSWORD")"
  printf 'host="%s"\n' "$(escape_option_value "$RESTORE_DB_HOST")"
  printf 'port="%s"\n' "$(escape_option_value "$RESTORE_DB_PORT")"
} > "$CNF"
chmod 600 "$CNF"

# Read-only query: only confirms the selected database and counts its tables. No SQL from the backup is run.
QUERY="SELECT CONCAT(DATABASE(), CHAR(9), COUNT(*)) FROM information_schema.tables WHERE table_schema = DATABASE();"
if ! DB_RESULT="$(mysql --defaults-extra-file="$CNF" --batch --skip-column-names --execute="$QUERY" "$RESTORE_TARGET_DB" 2>/dev/null)"; then
  echo "BLOCKED: Hedef staging veritabanına salt-okunur bağlantı kurulamadı." >&2
  exit 1
fi
IFS=$'\t' read -r RETURNED_DB TABLE_COUNT <<< "$DB_RESULT"
if [[ "$RETURNED_DB" != "$RESTORE_TARGET_DB" || ! "$TABLE_COUNT" =~ ^[0-9]+$ ]]; then
  echo "BLOCKED: Veritabanı istemcisinden beklenen doğrulama sonucu alınamadı." >&2
  exit 1
fi
if [[ "$TABLE_COUNT" != "0" ]]; then
  echo "BLOCKED: Hedef staging veritabanı boş değil; hiçbir veri değiştirilmedi." >&2
  exit 1
fi

printf 'PASS: Yedek bütünlüğü ve manifest doğrulandı.\n'
printf 'PASS: Kaynak/hedef ayrımı, açık hedef onayı ve staging dizin sınırları doğrulandı.\n'
printf 'PASS: Hedef veritabanı erişilebilir ve tablo içermiyor.\n'
printf 'SINIR: Bu yalnızca salt-okunur ön kontroldür; SQL içe aktarılmadı, medya çıkarılmadı ve uygulama başlatılmadı.\n'
