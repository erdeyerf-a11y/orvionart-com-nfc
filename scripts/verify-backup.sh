#!/usr/bin/env bash
# Verifies backup hashes and archive paths only; never extracts or restores data.
set -Eeuo pipefail
umask 077

if [[ $# -ne 1 ]]; then
  echo "Kullanım: $0 /web-koku-disinda/orvionart-staging-YYYYMMDDTHHMMSSZ" >&2
  exit 2
fi
BACKUP_DIR="$(realpath -- "$1")"
[[ -d "$BACKUP_DIR" ]] || { echo "HATA: Yedek dizini bulunamadı." >&2; exit 2; }
for file in database.sql media-storage.tar.gz MANIFEST.txt SHA256SUMS; do
  [[ -s "$BACKUP_DIR/$file" ]] || { echo "HATA: Dosya eksik/boş: $file" >&2; exit 1; }
done

# Do not let a crafted checksum list cause reads of arbitrary paths via sha256sum --check.
# The backup format has exactly these three entries, each represented once.
if ! awk '
  BEGIN { ok=1; count=0 }
  {
    count++
    if ($0 !~ /^[0-9a-fA-F]{64}  (database\.sql|media-storage\.tar\.gz|MANIFEST\.txt)$/) ok=0
    name=$2
    if (seen[name]++) ok=0
  }
  END { if (count != 3 || !seen["database.sql"] || !seen["media-storage.tar.gz"] || !seen["MANIFEST.txt"]) ok=0; exit(ok ? 0 : 1) }
' "$BACKUP_DIR/SHA256SUMS"; then
  echo "HATA: SHA256SUMS biçimi beklenen üç yedek dosyasıyla eşleşmiyor." >&2
  exit 1
fi

if command -v sha256sum >/dev/null 2>&1; then
  (cd "$BACKUP_DIR" && sha256sum --check --status SHA256SUMS) || { echo "HATA: SHA-256 doğrulaması başarısız." >&2; exit 1; }
elif command -v shasum >/dev/null 2>&1; then
  (cd "$BACKUP_DIR" && shasum -a 256 --check SHA256SUMS) || { echo "HATA: SHA-256 doğrulaması başarısız." >&2; exit 1; }
else
  echo "HATA: SHA-256 aracı bulunamadı." >&2
  exit 2
fi

# Validate the manifest schema without printing database metadata or secrets.
# Legacy manifests (format 1: eight keys) remain supported. New backups use format 2
# and explicitly declare that neither .env nor vendor dependencies are included.
if ! awk -F= '
  BEGIN {
    allowed["application"]=1; allowed["version"]=1; allowed["created_at_utc"]=1;
    allowed["database_engine"]=1; allowed["database_host"]=1; allowed["database_name"]=1;
    allowed["contains_env_file"]=1; allowed["restore_policy"]=1;
    allowed["backup_format"]=1; allowed["contains_vendor_directory"]=1;
    ok=1; count=0;
  }
  {
    if (index($0, "=") < 2) { ok=0; next }
    key=$1; value=substr($0, length(key)+2); count++;
    if (!(key in allowed) || seen[key]++) { ok=0; next }
    values[key]=value;
    if (value ~ /[[:cntrl:]]/) ok=0;
  }
  END {
    if (count < 8 || count > 10) ok=0;
    if (values["application"] != "ORVIONART") ok=0;
    if (values["version"] !~ /^[A-Za-z0-9._+-]{1,64}$/) ok=0;
    if (values["created_at_utc"] !~ /^[0-9]{8}T[0-9]{6}Z$/) ok=0;
    if (values["database_engine"] != "mysql" && values["database_engine"] != "mariadb") ok=0;
    if (values["database_host"] == "" || length(values["database_host"]) > 255) ok=0;
    if (values["database_name"] == "" || length(values["database_name"]) > 128) ok=0;
    if (values["contains_env_file"] != "no") ok=0;
    if (values["restore_policy"] != "staging_only_without_explicit_approval") ok=0;
    if (seen["backup_format"]) {
      if (values["backup_format"] != "2") ok=0;
      if (seen["contains_vendor_directory"] != 1 || values["contains_vendor_directory"] != "no") ok=0;
      if (count != 10) ok=0;
    } else if (seen["contains_vendor_directory"]) {
      if (values["contains_vendor_directory"] != "no" || count != 9) ok=0;
    } else if (count != 8) ok=0;
    if (count != 8 && count != 9 && count != 10) ok=0;
    exit(ok ? 0 : 1);
  }
' "$BACKUP_DIR/MANIFEST.txt"; then
  echo "HATA: Yedek manifesti eksik, yinelenen, bilinmeyen veya geçersiz alan içeriyor." >&2
  exit 1
fi
grep -qx 'application=ORVIONART' "$BACKUP_DIR/MANIFEST.txt" || { echo "HATA: Yedek manifesti ORVIONART ile eşleşmiyor." >&2; exit 1; }

command -v tar >/dev/null 2>&1 || { echo "HATA: tar bulunamadı." >&2; exit 2; }
tar -tzf "$BACKUP_DIR/media-storage.tar.gz" >/dev/null || { echo "HATA: Medya arşivi okunamıyor." >&2; exit 1; }
# Archives produced by backup-staging.sh use relative storage/app/{private,public} paths.
# Reject absolute paths, traversal segments, backslashes and unexpected roots before anyone extracts it.
while IFS= read -r entry; do
  [[ -n "$entry" ]] || { echo "HATA: Medya arşivinde boş yol bulundu." >&2; exit 1; }
  [[ "$entry" != /* ]] || { echo "HATA: Medya arşivinde mutlak yol bulundu." >&2; exit 1; }
  [[ "$entry" != *\\* ]] || { echo "HATA: Medya arşivinde ters eğik çizgi bulundu." >&2; exit 1; }
  case "/$entry/" in
    */../*|*/./*) echo "HATA: Medya arşivinde dizin geçişi bulundu." >&2; exit 1 ;;
  esac
  case "$entry" in
    storage/app/private|storage/app/private/*|storage/app/public|storage/app/public/*) ;;
    *) echo "HATA: Medya arşivinde beklenmeyen kök yol bulundu." >&2; exit 1 ;;
  esac
done < <(tar -tzf "$BACKUP_DIR/media-storage.tar.gz")
# Symlinks and hardlinks can redirect extraction outside the intended media tree.
# This backup format needs only regular files and directories.
while IFS= read -r listing; do
  entry_type="${listing:0:1}"
  case "$entry_type" in
    -|d) ;;
    *) echo "HATA: Medya arşivinde izin verilmeyen bağlantı/özel dosya türü bulundu." >&2; exit 1 ;;
  esac
done < <(tar -tvzf "$BACKUP_DIR/media-storage.tar.gz")

[[ -s "$BACKUP_DIR/database.sql" ]] || { echo "HATA: SQL dökümü boş." >&2; exit 1; }
echo "PASS: SHA-256 eşleşti; manifest geçerli; medya arşivi okunabilir ve yolları izin verilen köklerle sınırlı; SQL dökümü mevcut."
echo "SINIR: Veritabanı geri yüklenmedi ve SQL sözdizimi/uygulama uyumu test edilmedi. Arşivi yine de yalnızca ayrı staging dizinine çıkarın."
