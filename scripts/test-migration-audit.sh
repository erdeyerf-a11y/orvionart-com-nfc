#!/usr/bin/env bash
# Regression test for the static migration audit; uses temporary fixture projects only.
set -Eeuo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd -P)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/valid/database/migrations" "$TMP/invalid/database/migrations"
cat > "$TMP/valid/database/migrations/2026_10_15_000001_create_example_table.php" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {}
    public function down(): void {}
};
PHP
cp "$TMP/valid/database/migrations/2026_10_15_000001_create_example_table.php" "$TMP/invalid/database/migrations/2026_10_15_000001_create_example_table.php"
cat > "$TMP/invalid/database/migrations/2026_10_15_000002_irreversible_change.php" <<'PHP'
<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void {}
};
PHP
php "$ROOT/scripts/audit-migrations.php" --root="$TMP/valid" >/dev/null
if php "$ROOT/scripts/audit-migrations.php" --root="$TMP/invalid" >/dev/null 2>&1; then
  echo 'FAIL: down() eksik migration engellenmedi.' >&2
  exit 1
fi
printf 'PASS: geçerli fixture kabul edildi; down() eksik fixture engellendi.\n'
