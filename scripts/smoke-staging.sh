#!/usr/bin/env bash
# ORVIONART staging HTTP smoke test. GET-only; does not log in or mutate application data.
set -u

usage() {
  cat <<'USAGE'
Kullanım:
  bash scripts/smoke-staging.sh https://staging.example.com
  bash scripts/smoke-staging.sh https://staging.example.com --netrc-file /gizli/yol/staging.netrc

Yalnızca GET istekleri gönderir. TLS sertifikasını doğrular; -k/--insecure kullanmaz.
Plesk HTTP Basic parola koruması varsa kullanıcı/parola komut satırına yazılmadan
izinleri kısıtlı netrc dosyası belirtilebilir.
Staging ortamı bekler: /health=200, /admin/login=200, /admin -> login yönlendirmesi,
robots.txt içinde Disallow: / ve admin login yanıtında noindex güvenlik başlığı.
USAGE
}

if [[ "${1:-}" == "-h" || "${1:-}" == "--help" ]]; then usage; exit 0; fi
if [[ $# -ne 1 && $# -ne 3 ]]; then usage; exit 2; fi
BASE_URL="${1%/}"
NETRC_FILE=""
if [[ $# -eq 3 ]]; then
  if [[ "$2" != "--netrc-file" || ! -f "$3" || ! -r "$3" ]]; then echo "BLOCKED: --netrc-file için okunabilir bir dosya yolu belirtin." >&2; exit 2; fi
  NETRC_FILE="$3"
  if command -v stat >/dev/null 2>&1; then
    MODE="$(stat -c '%a' "$NETRC_FILE" 2>/dev/null || true)"
    if [[ -n "$MODE" ]] && (( (8#$MODE & 077) != 0 )); then echo "BLOCKED: netrc dosyasının izinleri 600 veya daha kısıtlı olmalı (chmod 600)." >&2; exit 2; fi
  fi
fi
if [[ "$BASE_URL" != https://* ]]; then echo "BLOCKED: HTTPS adresi zorunlu." >&2; exit 2; fi
if ! command -v curl >/dev/null 2>&1; then echo "BLOCKED: curl bulunamadı." >&2; exit 2; fi

TMP_DIR="$(mktemp -d)" || exit 2
trap 'rm -rf "$TMP_DIR"' EXIT
PASS=0; FAIL=0
pass() { printf 'PASS: %s\n' "$1"; PASS=$((PASS+1)); }
fail() { printf 'FAIL: %s\n' "$1" >&2; FAIL=$((FAIL+1)); }

request() {
  local path="$1" prefix="$2" status
  local -a auth_args=()
  if [[ -n "$NETRC_FILE" ]]; then auth_args+=(--netrc-file "$NETRC_FILE"); fi
  status="$(curl --silent --show-error --connect-timeout 8 --max-time 20 "${auth_args[@]}" \
    --output "$TMP_DIR/${prefix}.body" --dump-header "$TMP_DIR/${prefix}.headers" \
    --write-out '%{http_code}' "$BASE_URL$path" 2>"$TMP_DIR/${prefix}.error")" || status="000"
  printf '%s' "$status"
}
header_has() {
  local file="$1" name="$2" regex="$3"
  awk -v name="$name" -v regex="$regex" 'BEGIN{IGNORECASE=1} { sub(/\r$/, ""); if (tolower($0) ~ "^" tolower(name) ":" && $0 ~ regex) found=1 } END{ exit(found ? 0 : 1) }' "$file"
}

status="$(request '/health' health)"
if [[ "$status" == "200" ]] && grep -q '"status"[[:space:]]*:[[:space:]]*"ok"' "$TMP_DIR/health.body"; then pass '/health HTTP 200 ve durum ok'; else fail "/health beklenen 200/ok; HTTP $status"; fi

status="$(request '/admin/login' login)"
if [[ "$status" == "200" ]]; then pass '/admin/login HTTP 200'; else fail "/admin/login beklenen 200; HTTP $status"; fi
if [[ -f "$TMP_DIR/login.headers" ]] && header_has "$TMP_DIR/login.headers" 'X-Robots-Tag' 'noindex'; then pass 'Yönetici giriş yanıtında noindex'; else fail 'Yönetici giriş yanıtında X-Robots-Tag noindex yok'; fi
if [[ -f "$TMP_DIR/login.headers" ]] && header_has "$TMP_DIR/login.headers" 'X-Content-Type-Options' 'nosniff'; then pass 'nosniff güvenlik başlığı'; else fail 'X-Content-Type-Options nosniff eksik'; fi
if [[ -f "$TMP_DIR/login.headers" ]] && header_has "$TMP_DIR/login.headers" 'X-Frame-Options' 'DENY'; then pass 'X-Frame-Options DENY'; else fail 'X-Frame-Options DENY eksik'; fi
if [[ -f "$TMP_DIR/login.headers" ]] && header_has "$TMP_DIR/login.headers" 'Content-Security-Policy' 'frame-ancestors'; then pass 'Content-Security-Policy mevcut'; else fail 'Content-Security-Policy eksik'; fi

status="$(request '/robots.txt' robots)"
if [[ "$status" == "200" ]] && grep -Eiq '^[[:space:]]*Disallow:[[:space:]]*/[[:space:]]*$' "$TMP_DIR/robots.body"; then pass 'Staging robots.txt tüm taramayı engelliyor'; else fail "Staging robots.txt Disallow: / içermiyor veya HTTP $status"; fi

status="$(request '/admin' admin)"
if [[ "$status" =~ ^(301|302|303|307|308)$ ]] && grep -Eiq '^Location:[[:space:]]*https?://[^/]+/admin/login|^Location:[[:space:]]*/admin/login' "$TMP_DIR/admin.headers"; then pass 'Misafir /admin erişimi giriş ekranına yönlendiriliyor'; else fail "Misafir /admin yönlendirmesi beklenmedi; HTTP $status"; fi

printf '\nORVIONART STAGING HTTP SMOKE TEST\nAdres: %s\nPASS=%d FAIL=%d\n' "$BASE_URL" "$PASS" "$FAIL"
if [[ "$FAIL" -gt 0 ]]; then echo 'SONUÇ: BLOCKED — başarısız kontroller düzeltilmeden staging kabul edilmemeli.'; exit 1; fi
echo 'SONUÇ: HTTP_SMOKE_PASS — bu test tüm güvenlik/kabul testlerinin yerine geçmez.'
