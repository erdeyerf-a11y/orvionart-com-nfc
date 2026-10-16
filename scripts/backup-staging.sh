#!/usr/bin/env bash
# Explicit operator-run backup. Never invoked automatically by the web app.
set -Eeuo pipefail
umask 077

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
: "${BACKUP_ROOT:?BACKUP_ROOT mutlak bir dizin olarak verilmelidir; web kökü dışında olmalı.}"

if [[ "$BACKUP_ROOT" != /* ]]; then
  echo "HATA: BACKUP_ROOT mutlak yol olmalı." >&2
  exit 2
fi

for name in DB_DATABASE DB_USERNAME DB_PASSWORD; do
  if [[ ! -v "$name" ]]; then
    echo "HATA: $name ortam değişkeni tanımlanmalı; sırları komut satırına yazmayın." >&2
    exit 2
  fi
done
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_CONNECTION="${DB_CONNECTION:-mysql}"
if [[ "$DB_CONNECTION" != "mysql" && "$DB_CONNECTION" != "mariadb" ]]; then
  echo "HATA: Bu betik yalnızca MySQL/MariaDB yedeği alır." >&2
  exit 2
fi

for value in "$DB_DATABASE" "$DB_USERNAME" "$DB_PASSWORD" "$DB_HOST" "$DB_PORT"; do
  if [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]]; then
    echo "HATA: Veritabanı ayarlarında satır sonu karakteri kullanılamaz." >&2
    exit 2
  fi
done
if [[ ! "$DB_PORT" =~ ^[0-9]{1,5}$ ]] || (( DB_PORT < 1 || DB_PORT > 65535 )); then
  echo "HATA: DB_PORT geçerli bir TCP portu olmalı." >&2
  exit 2
fi
if [[ "$DB_DATABASE" == -* ]]; then
  echo "HATA: DB_DATABASE tire ile başlayamaz." >&2
  exit 2
fi

BACKUP_ROOT_REAL="$(realpath -m -- "$BACKUP_ROOT")"
case "$BACKUP_ROOT_REAL/" in
  "$APP_ROOT/"|"$APP_ROOT"/*)
    echo "HATA: Yedek dizini uygulama dizininin içinde olamaz." >&2
    exit 2
    ;;
esac
if [[ "$BACKUP_ROOT_REAL" == "/" ]]; then
  echo "HATA: BACKUP_ROOT kök dizin olamaz." >&2
  exit 2
fi
mkdir -p -- "$BACKUP_ROOT_REAL"

DUMP_BIN=""
if command -v mariadb-dump >/dev/null 2>&1; then DUMP_BIN="$(command -v mariadb-dump)"; fi
if [[ -z "$DUMP_BIN" ]] && command -v mysqldump >/dev/null 2>&1; then DUMP_BIN="$(command -v mysqldump)"; fi
if [[ -z "$DUMP_BIN" ]]; then
  echo "HATA: mariadb-dump veya mysqldump bulunamadı." >&2
  exit 2
fi
command -v tar >/dev/null 2>&1 || { echo "HATA: tar bulunamadı." >&2; exit 2; }
command -v gzip >/dev/null 2>&1 || { echo "HATA: gzip bulunamadı." >&2; exit 2; }

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FINAL_DIR="$BACKUP_ROOT_REAL/orvionart-staging-$STAMP"
WORK_DIR="$(mktemp -d "$BACKUP_ROOT_REAL/.orvionart-backup-tmp.XXXXXX")"
CNF="$WORK_DIR/client.cnf"
cleanup() { rm -rf -- "$WORK_DIR"; }
trap cleanup EXIT INT TERM

escape_option_value() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\"/\\\"}"
  printf '%s' "$value"
}
{
  printf '[client]\n'
  printf 'user="%s"\n' "$(escape_option_value "$DB_USERNAME")"
  printf 'password="%s"\n' "$(escape_option_value "$DB_PASSWORD")"
  printf 'host="%s"\n' "$(escape_option_value "$DB_HOST")"
  printf 'port="%s"\n' "$(escape_option_value "$DB_PORT")"
} > "$CNF"
chmod 600 "$CNF"

SQL_FILE="$WORK_DIR/database.sql"
MEDIA_FILE="$WORK_DIR/media-storage.tar.gz"
"$DUMP_BIN" --defaults-extra-file="$CNF" --single-transaction --quick --hex-blob --default-character-set=utf8mb4 --routines --triggers --events "$DB_DATABASE" > "$SQL_FILE"
[[ -s "$SQL_FILE" ]] || { echo "HATA: Veritabanı dökümü boş." >&2; exit 1; }

MEDIA_PATHS=()
[[ -d "$APP_ROOT/storage/app/private" ]] && MEDIA_PATHS+=("storage/app/private")
[[ -d "$APP_ROOT/storage/app/public" ]] && MEDIA_PATHS+=("storage/app/public")
if (( ${#MEDIA_PATHS[@]} == 0 )); then
  echo "HATA: Yedeklenecek storage/app/private veya storage/app/public dizini bulunamadı." >&2
  exit 1
fi
tar -czf "$MEDIA_FILE" -C "$APP_ROOT" "${MEDIA_PATHS[@]}"
[[ -s "$MEDIA_FILE" ]] || { echo "HATA: Medya arşivi boş." >&2; exit 1; }

VERSION="$(tr -d '\r\n' < "$APP_ROOT/VERSION" 2>/dev/null || printf 'unknown')"
{
  printf 'application=ORVIONART\n'
  printf 'backup_format=2\n'
  printf 'version=%s\n' "$VERSION"
  printf 'created_at_utc=%s\n' "$STAMP"
  printf 'database_engine=%s\n' "$DB_CONNECTION"
  printf 'database_host=%s\n' "$DB_HOST"
  printf 'database_name=%s\n' "$DB_DATABASE"
  printf 'contains_env_file=no\n'
  printf 'contains_vendor_directory=no\n'
  printf 'restore_policy=staging_only_without_explicit_approval\n'
} > "$WORK_DIR/MANIFEST.txt"
if command -v sha256sum >/dev/null 2>&1; then
  (cd "$WORK_DIR" && sha256sum database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
elif command -v shasum >/dev/null 2>&1; then
  (cd "$WORK_DIR" && shasum -a 256 database.sql media-storage.tar.gz MANIFEST.txt > SHA256SUMS)
else
  echo "HATA: SHA-256 aracı bulunamadı." >&2
  exit 2
fi
rm -f -- "$CNF"
[[ ! -e "$FINAL_DIR" ]] || { echo "HATA: Hedef yedek dizini zaten var." >&2; exit 1; }
mv -- "$WORK_DIR" "$FINAL_DIR"
# The trap target no longer exists after the atomic move.
WORK_DIR=""
trap - EXIT INT TERM
printf 'Yedek oluşturuldu: %s\n' "$FINAL_DIR"
printf 'Dosyalar: database.sql, media-storage.tar.gz, MANIFEST.txt, SHA256SUMS\n'
printf 'Not: Bu işlem geri yükleme tatbikatı değildir; geri yüklemeyi staging ortamında ayrıca doğrulayın.\n'
