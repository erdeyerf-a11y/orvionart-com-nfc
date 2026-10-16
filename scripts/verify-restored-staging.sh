#!/usr/bin/env bash
# Read-only post-restore verification for a staging database. Does not run migrations or mutate data.
set -Eeuo pipefail
umask 077

if [[ $# -ne 0 ]]; then
  echo "Kullanım: RESTORE_TARGET_DB=orvionart_staging_x RESTORE_CONFIRM_TARGET_DB=orvionart_staging_x RESTORE_DB_USER=... RESTORE_DB_PASSWORD=... $0" >&2
  exit 2
fi

for name in RESTORE_TARGET_DB RESTORE_CONFIRM_TARGET_DB RESTORE_DB_USER RESTORE_DB_PASSWORD; do
  if [[ ! -v "$name" || -z "${!name}" ]]; then
    echo "BLOCKED: Gerekli staging doğrulama ayarı eksik: $name" >&2
    exit 2
  fi
done
RESTORE_DB_HOST="${RESTORE_DB_HOST:-127.0.0.1}"
RESTORE_DB_PORT="${RESTORE_DB_PORT:-3306}"
for value in "$RESTORE_TARGET_DB" "$RESTORE_CONFIRM_TARGET_DB" "$RESTORE_DB_USER" "$RESTORE_DB_PASSWORD" "$RESTORE_DB_HOST" "$RESTORE_DB_PORT"; do
  if [[ "$value" == *$'\n'* || "$value" == *$'\r'* ]]; then
    echo "BLOCKED: Ayarlarda satır sonu karakteri kullanılamaz." >&2
    exit 2
  fi
done
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
command -v mysql >/dev/null 2>&1 || { echo "BLOCKED: mysql istemcisi bulunamadı." >&2; exit 2; }
command -v mktemp >/dev/null 2>&1 || { echo "BLOCKED: mktemp bulunamadı." >&2; exit 2; }

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

IDENTITY_QUERY="SELECT CONCAT(DATABASE(), CHAR(9), @@hostname, CHAR(9), @@port, CHAR(9), @@read_only, CHAR(9), COUNT(*)) FROM information_schema.tables WHERE table_schema = DATABASE();"
if ! IDENTITY="$(mysql --defaults-extra-file="$CNF" --batch --skip-column-names --execute="$IDENTITY_QUERY" "$RESTORE_TARGET_DB" 2>/dev/null)"; then
  echo "BLOCKED: Hedef staging veritabanına salt-okunur bağlantı kurulamadı." >&2
  exit 1
fi
IFS=$'\t' read -r RETURNED_DB SERVER_HOST SERVER_PORT READ_ONLY TABLE_COUNT <<< "$IDENTITY"
if [[ "$RETURNED_DB" != "$RESTORE_TARGET_DB" || -z "$SERVER_HOST" || ! "$SERVER_PORT" =~ ^[0-9]{1,5}$ || ! "$READ_ONLY" =~ ^[01]$ || ! "$TABLE_COUNT" =~ ^[0-9]+$ ]]; then
  echo "BLOCKED: Veritabanı kimliği veya kontrol sonucu beklenen biçimde değil." >&2
  exit 1
fi
if [[ "$READ_ONLY" != "0" ]]; then
  echo "BLOCKED: Staging veritabanı salt-okunur durumda; uygulama kabul testi yapılamaz." >&2
  exit 1
fi
if (( TABLE_COUNT < 1 )); then
  echo "BLOCKED: Hedef veritabanında tablo bulunamadı; geri yükleme tamamlanmış görünmüyor." >&2
  exit 1
fi

TABLE_QUERY="SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name;"
if ! TABLES="$(mysql --defaults-extra-file="$CNF" --batch --skip-column-names --execute="$TABLE_QUERY" "$RESTORE_TARGET_DB" 2>/dev/null)"; then
  echo "BLOCKED: Geri yüklenen tablo listesi okunamadı." >&2
  exit 1
fi
for required in migrations users experiences media_assets qr_links site_pages site_settings; do
  if ! grep -Fqx -- "$required" <<< "$TABLES"; then
    echo "BLOCKED: Geri yüklenen veritabanında beklenen tablo eksik: $required" >&2
    exit 1
  fi
done

MIGRATION_QUERY="SELECT COUNT(*) FROM migrations;"
if ! MIGRATION_COUNT="$(mysql --defaults-extra-file="$CNF" --batch --skip-column-names --execute="$MIGRATION_QUERY" "$RESTORE_TARGET_DB" 2>/dev/null)"; then
  echo "BLOCKED: Migration geçmişi okunamadı." >&2
  exit 1
fi
if [[ ! "$MIGRATION_COUNT" =~ ^[0-9]+$ ]] || (( MIGRATION_COUNT < 1 )); then
  echo "BLOCKED: Migration geçmişi boş veya geçersiz." >&2
  exit 1
fi

printf 'PASS: Hedef veritabanı adı ve bağlantı kimliği doğrulandı.\n'
printf 'PASS: Veritabanı salt-okunur değil; %s tablo bulundu.\n' "$TABLE_COUNT"
printf 'PASS: Temel ORVIONART tabloları ve %s migration kaydı bulundu.\n' "$MIGRATION_COUNT"
printf 'SINIR: Bu kontrol yalnızca şema/kimlik doğrulamasıdır; uygulama HTTP testi, medya dosyası doğrulaması ve canlıya geçiş onayı değildir.\n'
