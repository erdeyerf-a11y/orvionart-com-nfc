#!/usr/bin/env bash
# ORVIONART release gate: static source checks plus optional installed-runtime tests.
# Safe by design: never writes to the application database or deploys files.
set -u
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
STRICT=0
if [[ "${1:-}" == "--strict" ]]; then STRICT=1; elif [[ -n "${1:-}" ]]; then
  echo "Kullanım: bash scripts/release-gate.sh [--strict]" >&2; exit 2
fi
FAIL=0
WARN=0
PASS=0
pass() { printf 'PASS: %s\n' "$1"; PASS=$((PASS+1)); }
warn() { printf 'WARN: %s\n' "$1"; WARN=$((WARN+1)); }
fail() { printf 'FAIL: %s\n' "$1" >&2; FAIL=$((FAIL+1)); }

cd "$ROOT" || exit 2

if ! command -v php >/dev/null 2>&1; then fail 'PHP komutu bulunamadı'; echo "ÖZET PASS=$PASS WARN=$WARN FAIL=$FAIL"; exit 2; fi
if php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);'; then pass "PHP $(php -r 'echo PHP_VERSION;')"; else fail 'PHP 8.3 veya üstü gerekli'; fi
if php -r '$x=json_decode(file_get_contents("composer.json"),true); exit(is_array($x) ? 0 : 1);'; then pass 'composer.json geçerli JSON'; else fail 'composer.json geçersiz'; fi

# Syntax-check all project PHP source files, including tests and scripts, excluding dependencies.
PHP_FAIL=0
while IFS= read -r -d '' file; do
  if ! php -l "$file" >/dev/null 2>&1; then
    fail "PHP sözdizimi: ${file#$ROOT/}"
    php -l "$file" >&2
    PHP_FAIL=1
  fi
done < <(find "$ROOT" -type d \( -name vendor -o -name node_modules -o -name .git \) -prune -o -type f -name '*.php' -print0)
if [[ "$PHP_FAIL" -eq 0 ]]; then pass 'PHP kaynak sözdizimi'; fi

SHELL_FAIL=0
while IFS= read -r -d '' file; do
  if ! bash -n "$file"; then fail "Bash sözdizimi: ${file#$ROOT/}"; SHELL_FAIL=1; fi
done < <(find "$ROOT/scripts" -type f -name '*.sh' -print0)
if [[ "$SHELL_FAIL" -eq 0 ]]; then pass 'Shell betiği sözdizimi'; fi

if php "$ROOT/scripts/release-manifest.php" verify; then pass 'Kaynak manifesti'; else fail 'Kaynak manifesti doğrulanamadı'; fi

if grep -q 'InspectSiteBuilder::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectSiteBuilder.php" && -f "$ROOT/tests/Feature/SiteBuilderIntegrityCommandTest.php" ]]; then pass 'Site Builder denetim komutu bootstrap içinde kayıtlı'; else fail 'Site Builder denetim komutu veya test dosyası eksik'; fi
if grep -q 'InspectSiteRedirects::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectSiteRedirects.php" && -f "$ROOT/tests/Feature/SiteRedirectInspectionTest.php" && -f "$ROOT/docs/SITE_REDIRECT_INSPECTION_0_10_17.md" ]]; then pass 'Site Builder yönlendirme denetimi, test kaynağı ve belgesi mevcut'; else fail 'Site Builder yönlendirme denetimi/test/belge eksik'; fi
if grep -q 'InspectAuditLogHygiene::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectAuditLogHygiene.php" && -f "$ROOT/tests/Feature/AuditLogHygieneInspectionTest.php" && -f "$ROOT/docs/AUDIT_LOG_HYGIENE_0_10_21.md" ]]; then pass 'Denetim kaydı hijyeni komutu, test kaynakları ve belgesi mevcut'; else fail 'Denetim kaydı hijyeni komutu/test/belge eksik'; fi
if php "$ROOT/scripts/audit-migrations.php"; then pass 'Migration statik denetimi'; else fail 'Migration statik denetimi bloklayıcı buldu'; fi
if [[ -s "$ROOT/README.md" ]]; then pass 'README kurulum ve test sınırları mevcut'; else fail 'README.md eksik veya boş'; fi
if [[ -s "$ROOT/docs/V1_5_GAP_MATRIX_0_10_74.md" ]] && grep -q 'V1.5 gereksinim matrisi — 0.10.74' "$ROOT/docs/V1_5_GAP_MATRIX_0_10_74.md"; then pass 'Sürüm 0.10.74 V1.5 gereksinim matrisi mevcut'; else fail 'Sürüm 0.10.74 V1.5 gereksinim matrisi eksik'; fi
# A distributable environment template is required; validate safety-sensitive defaults.
if [[ -s "$ROOT/.env.example" ]]; then
  pass '.env.example dağıtım şablonu mevcut'
  if grep -Fxq 'APP_KEY=' "$ROOT/.env.example" && grep -Fxq 'APP_DEBUG=false' "$ROOT/.env.example" && grep -Fxq 'DB_CONNECTION=mysql' "$ROOT/.env.example" && grep -Fxq 'SESSION_SECURE_COOKIE=true' "$ROOT/.env.example" && grep -Fxq 'ORVIONART_PAYMENTS_ENABLED=false' "$ROOT/.env.example" && grep -Fxq 'CACHE_STORE=file' "$ROOT/.env.example" && grep -Fxq 'SESSION_DRIVER=file' "$ROOT/.env.example"; then
    pass '.env.example güvenli başlangıç değerleri doğrulandı'
  else
    fail '.env.example eksik veya güvenlik açısından beklenmeyen varsayılan içeriyor'
  fi
  if awk -F= '/^(DB_PASSWORD|MAIL_PASSWORD|REDIS_PASSWORD)=/ { value=$2; if (value != "" && value != "null" && value !~ /^replace_/) bad=1 } END { exit bad ? 0 : 1 }' "$ROOT/.env.example"; then
    fail '.env.example içinde gerçek parola benzeri değer var'
  else
    pass '.env.example içinde gerçek parola bulunmadı'
  fi
else
  fail '.env.example eksik veya boş'
fi
if grep -q "'Cache-Control'=>'no-store, private'" "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q "assertHeader('Cache-Control','no-store, private')" "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_PUBLIC_CACHE_LIFECYCLE_0_10_47.md" ]]; then pass 'Site Builder kamu sayfaları yayın durumu değişince eski yanıtları önbellekte tutmuyor; test kaynakları ve belge mevcut'; else fail 'Site Builder kamu sayfası önbellek yaşam döngüsü/test/belge eksik'; fi
if grep -q "if (!\$response->headers->has('Content-Security-Policy'))" "$ROOT/app/Http/Middleware/SecurityHeaders.php" && grep -q "default-src 'none'; sandbox" "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q "assertHeader('Content-Security-Policy', \"default-src 'none'; sandbox\")" "$ROOT/tests/Feature/MediaPreviewSafetyTest.php" && [[ -f "$ROOT/docs/SECURITY_HEADERS_ROUTE_CSP_PRESERVATION_0_10_45.md" ]]; then pass 'Global CSP middleware route-specific daha sıkı politikaları koruyor; regresyon kaynağı ve belge mevcut'; else fail 'Route-specific CSP koruması/test/belge eksik'; fi
if grep -q 'InspectPlatformIntegrity::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectPlatformIntegrity.php" && -f "$ROOT/tests/Feature/PlatformIntegrityCommandTest.php" ]]; then pass 'Bütünleşik platform denetimi ve test kaynağı mevcut'; else fail 'Bütünleşik platform denetimi veya test kaynağı eksik'; fi
if grep -q 'InspectDemoEngine::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectDemoEngine.php" && -f "$ROOT/tests/Feature/DemoInspectionCommandTest.php" ]]; then pass 'Demo Engine denetimi ve test kaynağı mevcut'; else fail 'Demo Engine denetimi veya test kaynağı eksik'; fi
if grep -q 'InspectDemoLifecycle::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectDemoLifecycle.php" && -f "$ROOT/tests/Feature/DemoLifecycleInspectionTest.php" && -f "$ROOT/docs/DEMO_LIFECYCLE_INSPECTION_0_10_13.md" ]]; then pass 'Demo Engine yaşam döngüsü denetimi ve test kaynakları mevcut'; else fail 'Demo Engine yaşam döngüsü denetimi/test/belge eksik'; fi
if grep -q 'DB::transaction(function () use ($request, $data, $settingsId)' "$ROOT/app/Http/Controllers/Admin/DemoEngineController.php" && grep -q 'lockForUpdate' "$ROOT/app/Http/Controllers/Admin/DemoEngineController.php" && grep -q 'test_demo_settings_change_records_audit_for_the_committed_state' "$ROOT/tests/Feature/DemoEngineTest.php" && grep -q 'test_demo_content_toggle_uses_locked_state_and_records_matching_audit_event' "$ROOT/tests/Feature/DemoEngineTest.php" && [[ -f "$ROOT/docs/DEMO_ADMIN_STATE_AUDIT_ATOMICITY_0_10_35.md" ]]; then pass 'Demo Engine ayar/içerik durum değişikliği kilitli transaction ve denetim kaydıyla korunuyor'; else fail 'Demo Engine durum değişikliği atomikliği/test/belge eksik'; fi
if grep -q "SiteSetting::query()->where('setting_key', 'general')->lockForUpdate()" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_site_settings_update_and_audit_are_atomic_when_audit_write_fails' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_navigation_toggle_uses_locked_current_state_and_audits_the_committed_value' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_navigation_delete_rolls_back_when_audit_write_fails' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_NAVIGATION_SETTINGS_ATOMICITY_0_10_36.md" ]]; then pass 'Site Builder ayar/menü transaction atomikliği, hedef sayfa yeniden doğrulaması, test kaynakları ve belge mevcut'; else fail 'Site Builder ayar/menü atomikliği, test veya belge eksik'; fi
if grep -q 'function updateNavigation(Request \$request, SiteNavigationItem \$item)' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q "name('site-builder.navigation.update')" "$ROOT/routes/web.php" && grep -q 'test_navigation_item_can_be_edited_and_records_before_and_after_audit_state' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_navigation_item_edit_rejects_unpublished_page_without_changing_existing_link' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_navigation_item_edit_rolls_back_when_audit_write_fails' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_NAVIGATION_EDIT_0_10_66.md" ]]; then pass 'Site Builder navigasyon bağlantısı düzenleme: hedef doğrulama, kilitli transaction, denetim kaydı ve regresyon kaynakları mevcut'; else fail 'Site Builder navigasyon düzenleme akışı/test/belge eksik'; fi
if grep -q 'InspectQrLifecycle::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectQrLifecycle.php" && -f "$ROOT/tests/Feature/QrLifecycleInspectionTest.php" && -f "$ROOT/docs/QR_LIFECYCLE_INSPECTION_0_10_14.md" ]]; then pass 'QR/NFC yaşam döngüsü denetimi ve test kaynakları mevcut'; else fail 'QR/NFC yaşam döngüsü denetimi/test/belge eksik'; fi
if [[ -f "$ROOT/docs/RELEASE_HANDOFF_0_10_10.md" ]]; then pass 'Sürüm teslimat sınırları belgelendi'; else fail 'Sürüm teslimat belgesi eksik'; fi
if grep -q 'publishedSlugOwner' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q "X-Robots-Tag' => 'noindex, nofollow, noarchive'" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && [[ -f "$ROOT/tests/Feature/SiteBuilderTest.php" ]]; then pass 'Site Builder yayın çakışması ve önizleme izolasyonu kaynakları mevcut'; else fail 'Site Builder yayın güvenliği kontrolü eksik'; fi
if grep -q 'InspectMediaStorage::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectMediaStorage.php" && -f "$ROOT/tests/Feature/MediaStorageInspectionTest.php" && -f "$ROOT/docs/MEDIA_STORAGE_INSPECTION_0_10_12.md" ]]; then pass 'Medya depolama bütünlük denetimi ve test kaynakları mevcut'; else fail 'Medya depolama denetimi/test/belge eksik'; fi
if grep -q 'InspectMediaReferences::class' "$ROOT/bootstrap/app.php" && [[ -f "$ROOT/app/Console/Commands/InspectMediaReferences.php" && -f "$ROOT/tests/Feature/MediaReferenceInspectionTest.php" && -f "$ROOT/docs/MEDIA_REFERENCE_INSPECTION_0_10_15.md" ]]; then pass 'Medya referans yolu/dosya varlığı denetimi ve test kaynakları mevcut'; else fail 'Medya referans denetimi/test/belge eksik'; fi
if grep -q 'DB::transaction(function () use' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_site_builder_media_upload_rolls_back_asset_when_audit_write_fails' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_site_builder_media_upload_persists_asset_and_audit_together' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_MEDIA_UPLOAD_ATOMICITY_0_10_35.md" ]]; then pass 'Site Builder medya kaydı ve denetim yazımı transaction içinde; hata regresyonları ve belge mevcut'; else fail 'Site Builder medya yükleme atomikliği/test/belge eksik'; fi
if grep -q '&& is_file($path)' "$ROOT/app/Http/Controllers/PublicSite/MediaDeliveryController.php" && grep -q 'test_public_media_rejects_a_directory_even_when_its_path_is_inside_managed_media_scope' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/PUBLIC_MEDIA_FILE_TARGET_0_10_34.md" ]]; then pass 'Genel medya sunumu normal dosya hedefini zorunlu tutuyor; regresyon testi ve belge mevcut'; else fail 'Genel medya normal dosya hedefi koruması/test/belge eksik'; fi
if grep -q "unsafe_filesystem_target" "$ROOT/app/Console/Commands/InspectMediaReferences.php" && grep -q 'test_media_reference_inspection_reports_symlink_target_outside_private_disk' "$ROOT/tests/Feature/MediaReferenceInspectionTest.php" && [[ -f "$ROOT/docs/MEDIA_REFERENCE_INSPECTION_0_10_33.md" ]]; then pass 'Medya referans denetimi symlink dış hedefi ve normal dosya olmayan hedefleri raporluyor'; else fail 'Medya dosya sistemi hedef denetimi/test/belge eksik'; fi
if grep -q 'isReferencedByDemoContent' "$ROOT/app/Models/MediaAsset.php" && grep -q '!$this->isReferencedByDemoContent()' "$ROOT/app/Models/MediaAsset.php" && [[ -f "$ROOT/tests/Feature/MediaAssetDeletionSafetyTest.php" && -f "$ROOT/docs/MEDIA_DELETE_GUARD_0_10_16.md" ]]; then pass 'Medya silme koruması Demo Engine disk/yol referanslarını kontrol ediyor'; else fail 'Medya silme referans koruması/test/belge eksik'; fi
if grep -q 'isReferencedByAnotherAsset' "$ROOT/app/Models/MediaAsset.php" && grep -q 'test_asset_cannot_be_deleted_when_another_asset_row_references_the_same_disk_and_path' "$ROOT/tests/Feature/MediaAssetDeletionSafetyTest.php" && [[ -f "$ROOT/docs/MEDIA_DELETE_GUARD_0_10_18.md" ]]; then pass 'Medya silme koruması aynı disk/yolu kullanan diğer medya kayıtlarını da denetler'; else fail 'Paylaşılan medya yolu koruması/test/belge eksik'; fi
if grep -q 'orvionart-trash/' "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q 'isSafeManagedMediaPath' "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q 'test_unreferenced_media_deletion_uses_private_quarantine_and_removes_bytes' "$ROOT/tests/Feature/QrAndMediaTest.php" && grep -q 'media_quarantine_cleanup_pending' "$ROOT/app/Console/Commands/InspectMediaStorage.php" && [[ -f "$ROOT/docs/MEDIA_DELETE_QUARANTINE_0_10_19.md" ]]; then pass 'Medya silme karantinası, güvenli yol kontrolü ve karantina inceleme kaynakları mevcut'; else fail 'Medya silme karantinası/test/inceleme belgesi eksik'; fi
if grep -q "\$diskName !== 'local'" "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q 'test_media_on_non_private_disk_is_not_moved_to_quarantine_or_deleted' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/MEDIA_PRIVATE_QUARANTINE_GUARD_0_10_22.md" ]]; then pass 'Medya silmede özel disk zorunluluğu, regresyon testi ve belge mevcut'; else fail 'Medya özel disk koruması/test/belge eksik'; fi
if grep -q "\$asset->disk === 'local'" "$ROOT/app/Http/Controllers/PublicSite/MediaDeliveryController.php" && grep -q 'isSafeManagedMediaPath' "$ROOT/app/Http/Controllers/PublicSite/MediaDeliveryController.php" && grep -q 'realpath' "$ROOT/app/Http/Controllers/PublicSite/MediaDeliveryController.php" && grep -q 'test_public_media_rejects_assets_on_non_local_disks' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/PUBLIC_MEDIA_DELIVERY_GUARD_0_10_23.md" ]]; then pass 'Genel medya sunumunda disk/yol/symlink koruması, test kaynakları ve belge mevcut'; else fail 'Genel medya sunumu güvenlik koruması/test/belge eksik'; fi
if grep -q "\$asset->disk === 'local'" "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q 'isSafeManagedMediaPath' "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && grep -q 'realpath' "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" && [[ -f "$ROOT/tests/Feature/MediaPreviewSafetyTest.php" && -f "$ROOT/docs/ADMIN_MEDIA_PREVIEW_GUARD_0_10_24.md" ]]; then pass 'Yönetici medya önizleme disk/yol/symlink koruması, test kaynağı ve belgesi mevcut'; else fail 'Yönetici medya önizleme koruması/test/belge eksik'; fi
if grep -q 'validateRestorableSnapshot' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_revision_restore_rejects_slug_already_used_by_another_page_without_changing_current_draft' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_revision_restore_changes_draft_only_and_keeps_live_snapshot_unchanged' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_REVISION_RESTORE_0_10_11.md" ]]; then pass 'Site Builder sürüm geri alma doğrulaması ve test kaynakları mevcut'; else fail 'Site Builder sürüm geri alma koruması/testleri/belgesi eksik'; fi
if grep -q 'isSafeSnapshotLink' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'isSafeSnapshotCanonical' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_revision_restore_rejects_unsafe_slider_url_without_changing_draft' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_revision_restore_rejects_invalid_canonical_url_without_changing_draft' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_RESTORE_SNAPSHOT_VALIDATION_0_10_27.md" ]]; then pass 'Site Builder geri alma snapshot URL/SEO doğrulaması, testleri ve belgesi mevcut'; else fail 'Site Builder geri alma snapshot doğrulaması/test/belge eksik'; fi
if grep -q "published_at ?: \$item->created_at ?: now()" "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q 'test_production_sitemap_lastmod_tracks_publication_not_later_draft_edits' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_production_sitemap_experience_lastmod_uses_publication_timestamp' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITEMAP_PUBLICATION_LASTMOD_0_10_25.md" ]]; then pass 'Sitemap lastmod yalnızca herkese açık sürümün tarihini kullanır; regresyon testleri ve belge mevcut'; else fail 'Sitemap yayın tarihi koruması/testleri/belgesi eksik'; fi
if grep -q 'isAllowedSitemapUrl' "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q 'addSitemapUrl' "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q 'test_production_sitemap_excludes_external_canonical_urls_and_deduplicates_same_origin_canonicals' "$ROOT/tests/Feature/SiteBuilderTest.php" && grep -q 'test_scheduled_experience_sitemap_lastmod_uses_the_time_it_became_public' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITEMAP_ORIGIN_AND_DEDUP_0_10_26.md" ]]; then pass 'Sitemap köken/tekrar koruması ve zamanlanmış deneyim tarihi için regresyon kaynakları mevcut'; else fail 'Sitemap köken/tekrar koruması, testleri veya belgesi eksik'; fi
if grep -q "private, no-store, no-cache, must-revalidate" "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" && grep -q "noindex, nofollow, noarchive" "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" && grep -q 'test_stable_qr_link_uses_the_current_experience_slug' "$ROOT/tests/Feature/QrAndMediaTest.php"; then pass 'QR/NFC yönlendirme önbellek/indeks koruması ve stabil token test kaynağı mevcut'; else fail 'QR/NFC yönlendirme güvenlik kontrolü eksik'; fi
if grep -q 'lockForUpdate' "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" && grep -q 'test_qr_redirect_does_not_count_a_scan_when_public_destination_is_missing' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/QR_REDIRECT_ATOMICITY_0_10_29.md" ]]; then pass 'Genel QR yönlendirmesi kilit/hedef doğrulaması, regresyon testi ve belge mevcut'; else fail 'Genel QR yönlendirme tutarlılığı/test/belge eksik'; fi
if grep -q 'lockForUpdate' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" && grep -q 'DB::transaction(function () use ($request, $qrLink, $experienceId)' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" && grep -q 'test_super_admin_qr_toggle_records_the_matching_state_change' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/QR_TOGGLE_ATOMICITY_0_10_28.md" ]]; then pass 'QR/NFC durum değişikliği transaction/kilit, regresyon testi ve belge mevcut'; else fail 'QR/NFC durum değişikliği atomikliği/test/belge eksik'; fi
if grep -q "\$experienceId = QrLink::query()->whereKey(\$qrLink->getKey())->value('experience_id')" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" && grep -q 'lockForUpdate' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" && grep -q 'test_qr_image_is_not_generated_for_a_disabled_link' "$ROOT/tests/Feature/QrAndMediaTest.php" && grep -q 'test_qr_image_is_available_for_a_future_scheduled_experience' "$ROOT/tests/Feature/QrAndMediaTest.php" && grep -q 'test_qr_image_is_not_generated_for_an_active_link_with_a_draft_target' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/QR_IMAGE_LIFECYCLE_GUARD_0_10_46.md" ]]; then pass 'QR SVG üretimi güncel kilitli yaşam döngüsünü doğruluyor; zamanlanmış hedef desteği, regresyon kaynakları ve belge mevcut'; else fail 'QR SVG yaşam döngüsü doğrulaması/test/belge eksik'; fi
if grep -q 'targetPage->isPubliclyAvailable()' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_navigation_toggle_cannot_activate_a_link_to_an_unpublished_page' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_NAVIGATION_ACTIVATION_GUARD_0_10_37.md" ]]; then pass 'Site Builder menü etkinleştirmesinde hedef sayfa/HTTPS yeniden doğrulaması ve regresyon kaynağı mevcut'; else fail 'Site Builder menü etkinleştirme hedef koruması/test/belge eksik'; fi
if grep -q 'public function update(Request $request, SitePage $sitePage)' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'public function publish(Request $request, SitePage $sitePage)' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'lockForUpdate()->firstOrFail()' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_site_builder_update_increments_the_locked_version_and_audits_the_transition' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_LOCKED_PAGE_TRANSITIONS_0_10_38.md" ]]; then pass 'Site Builder taslak/yayın/yayından kaldırma/geri alma kilitli sayfa geçişleri, regresyon kaynağı ve belge mevcut'; else fail 'Site Builder kilitli sayfa geçişleri/test/belge eksik'; fi
if [[ $(grep -c '\$this->lockPageMediaAssets' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php") -eq 3 ]] && grep -q 'private function lockPageMediaAssets' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'validateSectionMedia.*data' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" && grep -q 'test_publish_rejects_a_stale_media_reference_without_changing_the_live_page' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SITE_BUILDER_MEDIA_REFERENCE_LOCK_ORDER_0_10_44.md" ]]; then pass 'Site Builder medya referansları silme ile aynı kilit sırasını kullanıyor; işlem içi yeniden doğrulama, regresyon kaynağı ve belge mevcut'; else fail 'Site Builder medya kilit sırası/işlem içi doğrulama/test/belge eksik'; fi
if grep -q 'deactivateQrLinksForExperience' "$ROOT/app/Http/Controllers/Admin/ExperienceController.php" && grep -q 'qr_link.auto_deactivated' "$ROOT/app/Http/Controllers/Admin/ExperienceController.php" && grep -q 'experienceId' "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" && grep -q 'test_moving_experience_to_draft_deactivates_active_qr_links_and_audits_the_change' "$ROOT/tests/Feature/ExperienceManagementTest.php" && grep -q 'test_qr_link_cannot_be_reactivated_when_experience_is_draft' "$ROOT/tests/Feature/QrAndMediaTest.php" && [[ -f "$ROOT/docs/EXPERIENCE_QR_LIFECYCLE_0_10_30.md" ]]; then pass 'Deneyim/QR yaşam döngüsü, otomatik devre dışı bırakma, kilit sırası, test kaynakları ve belge mevcut'; else fail 'Deneyim/QR yaşam döngüsü koruması/test/belge eksik'; fi
if grep -q 'isSafeDemoMediaPath' "$ROOT/app/Http/Controllers/Admin/DemoEngineController.php" && grep -q 'lockForUpdate' "$ROOT/app/Http/Controllers/PublicSite/DemoEngineController.php" && grep -q 'test_demo_content_with_assignment_history_cannot_be_deleted_and_media_is_preserved' "$ROOT/tests/Feature/DemoEngineTest.php" && grep -q 'test_demo_content_deletion_removes_only_safe_private_demo_media_and_audits' "$ROOT/tests/Feature/DemoEngineTest.php" && grep -q 'test_demo_content_with_non_private_media_disk_is_not_deleted' "$ROOT/tests/Feature/DemoEngineTest.php" && [[ -f "$ROOT/docs/DEMO_CONTENT_DELETE_ATOMICITY_0_10_31.md" ]]; then pass 'Demo Engine içerik silme/atama atomikliği, özel medya yolu, test kaynakları ve belge mevcut'; else fail 'Demo Engine içerik silme/atama koruması, test veya belge eksik'; fi
if grep -q "qr_links_waiting_for_scheduled_experience" "$ROOT/app/Console/Commands/InspectPlatformIntegrity.php" && grep -q 'test_platform_integrity_classifies_future_scheduled_qr_target_as_waiting_not_broken' "$ROOT/tests/Feature/PlatformIntegrityCommandTest.php" && [[ -f "$ROOT/docs/PLATFORM_INTEGRITY_SCHEDULED_QR_CLASSIFICATION_0_10_40.md" ]]; then pass 'Bütünleşik denetim gelecekte zamanlanmış QR/NFC hedeflerini kırık hedeflerden ayırıyor'; else fail 'Zamanlanmış QR/NFC hedef sınıflandırması/test/belge eksik'; fi
if grep -q 'historical_revision_media_reference_problems' "$ROOT/app/Console/Commands/InspectPlatformIntegrity.php" && grep -q 'SitePageRevision::query()' "$ROOT/app/Console/Commands/InspectPlatformIntegrity.php" && grep -q 'test_platform_integrity_reports_missing_media_record_in_retained_page_revision' "$ROOT/tests/Feature/PlatformIntegrityCommandTest.php" && grep -q 'test_platform_integrity_reports_missing_file_referenced_only_by_retained_page_revision' "$ROOT/tests/Feature/PlatformIntegrityCommandTest.php" && [[ -f "$ROOT/docs/PLATFORM_INTEGRITY_HISTORICAL_MEDIA_0_10_41.md" ]]; then pass 'Bütünleşik denetim geçmiş Site Builder snapshot medya referanslarını tarıyor; regresyon kaynakları/belge mevcut'; else fail 'Geçmiş sürüm medya bütünlük denetimi/test/belge eksik'; fi
if grep -q 'SitePageRevision::query()' "$ROOT/app/Models/MediaAsset.php" && grep -q 'snapshotReferencesThisAsset' "$ROOT/app/Models/MediaAsset.php" && grep -q 'test_media_asset_cannot_be_deleted_when_only_a_retained_site_page_revision_references_it' "$ROOT/tests/Feature/MediaAssetDeletionSafetyTest.php" && grep -q 'test_media_asset_is_blocked_when_only_a_historical_social_image_references_it' "$ROOT/tests/Feature/MediaAssetDeletionSafetyTest.php" && [[ -f "$ROOT/docs/MEDIA_HISTORICAL_REVISION_REFERENCE_GUARD_0_10_39.md" ]]; then pass 'Medya silme kontrolü Site Builder tarihsel snapshot referanslarını koruyor'; else fail 'Tarihsel Site Builder medya referansı koruması/test/belge eksik'; fi
if grep -q "isSafeDemoMediaPath" "$ROOT/app/Http/Controllers/PublicSite/DemoEngineController.php" && grep -q "realpath" "$ROOT/app/Http/Controllers/PublicSite/DemoEngineController.php" && grep -q 'test_demo_media_rejects_legacy_disks_unsafe_paths_and_unapproved_mime_types' "$ROOT/tests/Feature/DemoEngineTest.php" && [[ -f "$ROOT/docs/DEMO_MEDIA_DELIVERY_GUARD_0_10_32.md" ]]; then pass 'Demo Engine genel medya sunumunda disk/yol/MIME/symlink koruması, regresyon kaynağı ve belge mevcut'; else fail 'Demo Engine medya sunumu güvenlik koruması/test/belge eksik'; fi
if bash "$ROOT/scripts/test-migration-audit.sh"; then pass 'Migration denetim regresyon testi'; else fail 'Migration denetim regresyon testi başarısız'; fi
if [[ -f "$ROOT/scripts/test-preflight.sh" && -f "$ROOT/docs/DEPLOYMENT_PREFLIGHT_DATABASE_CONFIG_0_10_43.md" ]] && grep -q 'DB yer tutucuları' "$ROOT/scripts/test-preflight.sh" && grep -q 'Veritabanı yapılandırması' "$ROOT/scripts/preflight.php"; then if bash "$ROOT/scripts/test-preflight.sh"; then pass 'Kurulum ön kontrolü DB yer tutucularını engelliyor ve sırları gizli tutuyor'; else fail 'Kurulum ön kontrolü regresyon testi başarısız'; fi; else fail 'Kurulum ön kontrolü DB yapılandırma testi/belgesi eksik'; fi
if [[ -f "$ROOT/scripts/test-verify-backup.sh" && -f "$ROOT/docs/BACKUP_VERIFY_HARDENING_0_10_20.md" && -f "$ROOT/docs/BACKUP_MANIFEST_SCHEMA_0_10_70.md" ]] \
  && grep -q "backup_format=2" "$ROOT/scripts/backup-staging.sh" \
  && grep -q 'contains_vendor_directory=no' "$ROOT/scripts/backup-staging.sh" \
  && grep -q 'unknown-key' "$ROOT/scripts/test-verify-backup.sh" \
  && grep -q 'format2-missing-vendor' "$ROOT/scripts/test-verify-backup.sh"; then
  if bash "$ROOT/scripts/test-verify-backup.sh"; then pass 'Yedek manifesti format 2, eski biçim uyumluluğu ve güvenlik regresyon testleri'; else fail 'Yedek manifesti regresyon testleri başarısız'; fi
else fail 'Yedek manifesti format 2 denetimi, testleri veya belgesi eksik'; fi
if [[ -f "$ROOT/scripts/restore-staging-preflight.sh" && -f "$ROOT/scripts/test-restore-staging-preflight.sh" && -f "$ROOT/docs/RESTORE_STAGING_PREFLIGHT_0_10_71.md" ]] \
  && grep -q 'RESTORE_CONFIRM_TARGET_DB' "$ROOT/scripts/restore-staging-preflight.sh" \
  && grep -q 'information_schema.tables' "$ROOT/scripts/restore-staging-preflight.sh" \
  && grep -q 'test-restore-staging-preflight.sh' "$ROOT/README.md"; then
  if bash "$ROOT/scripts/test-restore-staging-preflight.sh"; then pass 'Geri yükleme staging ön kontrolü: hedef DB ayrımı, boş DB ve dizin güvenliği regresyonları'; else fail 'Geri yükleme staging ön kontrolü regresyonları başarısız'; fi
else fail 'Geri yükleme staging ön kontrolü, regresyon testi veya belge eksik'; fi

# Never ship environment secrets or nested historical release archives.
SECRET_FILES=0
while IFS= read -r -d '' file; do
  rel="${file#$ROOT/}"
  case "$rel" in
    .env.example) ;;
    .env|.env.*) fail "Teslimatta olmaması gereken ortam dosyası: $rel"; SECRET_FILES=$((SECRET_FILES+1));;
    .phpunit.result.cache) ;;
  esac
done < <(find "$ROOT" -type d \( -name vendor -o -name node_modules -o -name .git \) -prune -o -type f -print0)
if [[ "$SECRET_FILES" -eq 0 ]]; then pass 'Gerçek .env dosyası bulunmadı'; fi
NESTED_ZIPS=$(find "$ROOT" -type f -iname '*.zip' -not -path '*/vendor/*' -not -path '*/node_modules/*' | wc -l | tr -d ' ')
if [[ "$NESTED_ZIPS" == "0" ]]; then pass 'Kaynak ağacına tarihsel ZIP gömülmemiş'; else fail "Kaynak ağacında $NESTED_ZIPS ZIP bulundu"; fi

# Search indexing is explicitly opt-in, including when APP_ENV is production.
if grep -q "'public_indexing_enabled'[[:space:]]*=>[[:space:]]*(bool) env('ORVIONART_PUBLIC_INDEXING_ENABLED',[[:space:]]*false)" "$ROOT/config/orvionart.php" && grep -q "orvionart.public_indexing_enabled" "$ROOT/routes/web.php" && grep -q "orvionart.public_indexing_enabled" "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q 'test_production_search_indexing_is_opt_in_and_disabled_by_default' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/SEARCH_INDEXING_EXPLICIT_OPT_IN_0_10_48.md" ]]; then
  pass 'Arama motoru indekslemesi açık izinle etkinleşiyor; robots/sitemap, regresyon kaynağı ve belge mevcut'
else
  fail 'Arama motoru indeksleme açık izin koruması/test/belgesi eksik'
fi

if [[ -f "$ROOT/docs/SITE_BUILDER_SAFE_THEME_CONTROLS_0_10_54.md" ]] \
  && grep -q "'background_color'=>" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q "'button_style'=>\['sometimes','required',Rule::in(\['pill','rounded','square'\])\]" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q 'array_merge(\$this->defaultSettings(), \$current, \$data)' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q 'test_site_theme_controls_are_saved_and_applied_from_allowlisted_settings' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_site_theme_settings_reject_unapproved_css_values' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_legacy_site_settings_update_preserves_new_theme_controls' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_public_theme_render_falls_back_for_corrupt_legacy_color_values' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q -- '--button-radius:' "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'header.site-header{position:sticky' "$ROOT/resources/views/public/site-page.blade.php"; then
  pass 'Site Builder tema ayarları: izinli renk/yazı tipi/buton/header/footer seçenekleri, eski ayar uyumluluğu ve regresyon kaynakları mevcut'
else
  fail 'Site Builder tema ayarları veya regresyon kaynakları eksik'
fi

if grep -q "'testimonials', 'stats'" "$ROOT/app/Models/SitePage.php" \
  && grep -q "'testimonials'=>'Müşteri Görüşleri','stats'=>'İstatistikler'" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -Fq 'class="testimonials-grid"' "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -Fq 'class="stats-grid"' "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'test_site_builder_form_offers_testimonial_and_statistics_sections' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_published_page_renders_testimonial_and_statistics_sections_as_escaped_content' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && [[ -f "$ROOT/docs/SITE_BUILDER_TESTIMONIALS_STATS_0_10_67.md" ]]; then
  pass 'Site Builder müşteri görüşleri ve istatistik bölümleri: izinli türler, yönetici formu, güvenli public render ve regresyon kaynakları mevcut'
else
  fail 'Site Builder müşteri görüşleri/istatistik bölümleri veya regresyon kaynakları eksik'
fi

# QR/NFC inventory export must be admin-only through the shared group, bounded, and CSV-formula safe.
if grep -q "Route::get('/qr-links/export', \[QrLinkController::class, 'export'\])" "$ROOT/routes/web.php" \
  && grep -q "function export(Request \$request): StreamedResponse|RedirectResponse" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q "limit(5001)" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q "qr_link.export_prepared" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q "function safeCsvCell" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q "test_super_admin_can_export_filtered_qr_nfc_inventory_with_formula_safe_cells" "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q "test_non_admin_cannot_export_qr_nfc_inventory" "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && [[ -f "$ROOT/docs/QR_NFC_CSV_EXPORT_0_10_73.md" ]]; then
  pass 'QR/NFC CSV dışa aktarımı: filtre, 5.000 kayıt sınırı, CSV formül koruması, denetim kaydı ve regresyon kaynakları mevcut'
else
  fail 'QR/NFC CSV dışa aktarımının kodu, güvenlik kontrolleri, test kaynakları veya belgesi eksik'
fi

# Payment integration must remain disabled in source configuration.
if grep -Eq "'payments_enabled'[[:space:]]*=>[[:space:]]*\(bool\)[[:space:]]*env\('ORVIONART_PAYMENTS_ENABLED',[[:space:]]*false\)" "$ROOT/config/orvionart.php"; then
  pass 'Ödeme entegrasyonu varsayılan olarak kapalı'
else
  fail 'Ödeme varsayılanı kapalı görünmüyor; config/orvionart.php incelenmeli'
fi

if command -v composer >/dev/null 2>&1; then
  if (cd "$ROOT" && composer validate --no-check-publish --no-interaction); then pass 'Composer manifest doğrulaması'; else fail 'composer validate başarısız'; fi
else
  if [[ "$STRICT" -eq 1 ]]; then fail 'Strict modda Composer zorunlu, ancak bulunamadı'; else warn 'Composer yok; composer validate çalıştırılamadı'; fi
fi

if [[ -f "$ROOT/vendor/autoload.php" && -f "$ROOT/artisan" ]]; then
  if (cd "$ROOT" && php artisan test); then pass 'Laravel test paketi'; else fail 'Laravel testleri başarısız'; fi
else
  if [[ "$STRICT" -eq 1 ]]; then fail 'Strict modda vendor/autoload.php ve artisan test ortamı zorunlu'; else warn 'vendor/autoload.php yok; Laravel runtime/feature testleri çalıştırılmadı'; fi
fi

echo ""
if grep -q "config('app.env') !== 'production'" "$ROOT/app/Http/Controllers/PublicSite/SitePageController.php" && grep -q 'test_public_site_builder_page_is_noindex_outside_production_even_when_page_allows_indexing' "$ROOT/tests/Feature/SiteBuilderTest.php" && [[ -f "$ROOT/docs/STAGING_PUBLIC_SITE_NOINDEX_0_10_49.md" ]]; then
    pass 'Site Builder staging sayfaları noindex koruması, regresyon kaynağı ve belge mevcut'
else
    fail 'Site Builder staging noindex koruması eksik'
fi

if [[ -f "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" && -f "$ROOT/app/Models/VideoUploadSession.php" && -f "$ROOT/database/migrations/2026_10_15_000001_create_video_upload_sessions_table.php" && -f "$ROOT/tests/Feature/VideoChunkUploadTest.php" && -f "$ROOT/docs/RESUMABLE_VIDEO_UPLOAD_0_10_50.md" ]] \
  && grep -q 'MAX_VIDEO_BYTES = 512' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q 'hasReceivedAllChunks' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q 'finfo(FILEINFO_MIME_TYPE)' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q "hash_file('sha256'" "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q "chunk_hashes" "$ROOT/database/migrations/2026_10_15_000001_create_video_upload_sessions_table.php" \
  && grep -q 'assertOwner' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q 'orvionart:prune-video-uploads' "$ROOT/bootstrap/app.php" \
  && grep -q 'test_chunk_upload_records_received_part_and_rejects_wrong_chunk_size' "$ROOT/tests/Feature/VideoChunkUploadTest.php" \
  && grep -q 'test_video_upload_session_is_private_to_its_creator' "$ROOT/tests/Feature/VideoChunkUploadTest.php" \
   && grep -q "X-Chunk-SHA256" "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
   && grep -q 'test_chunk_upload_requires_matching_client_sha256' "$ROOT/tests/Feature/VideoChunkUploadTest.php" \
   && grep -q 'partHash' "$ROOT/resources/views/admin/media/index.blade.php" \
   && grep -q 'assetCommitted' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
   && grep -q "current.status === 'completed'" "$ROOT/resources/views/admin/media/index.blade.php"; then
  pass 'Parçalı video yükleme: sahiplik, istemci/sunucu SHA-256, idempotent tekrar, güvenli temizlik ve regresyon kaynakları mevcut'
else
  fail 'Parçalı video yükleme altyapısı, güvenlik kontrolleri, temizlik veya regresyon kaynakları eksik'
fi

if [[ -f "$ROOT/app/Jobs/ProcessUploadedVideo.php" && -f "$ROOT/database/migrations/2026_10_15_000002_add_video_processing_fields_to_media_assets_table.php" && -f "$ROOT/database/migrations/2026_10_15_000003_add_video_derivative_paths_to_media_assets_table.php" && -f "$ROOT/tests/Feature/VideoProcessingQueueTest.php" && -f "$ROOT/docs/VIDEO_PROCESSING_QUEUE_0_10_51.md" && -f "$ROOT/docs/VIDEO_TRANSCODING_AND_THUMBNAILS_0_10_53.md" ]] \
  && grep -q "ProcessUploadedVideo::dispatch" "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q "ProcessUploadedVideo::dispatch" "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" \
  && grep -q "processing_status === 'ready'" "$ROOT/app/Http/Controllers/PublicSite/MediaDeliveryController.php" \
  && grep -q '^QUEUE_CONNECTION=database$' "$ROOT/.env.example" \
  && grep -q 'test_failed_video_can_be_requeued_without_reuploading_bytes' "$ROOT/tests/Feature/VideoProcessingQueueTest.php" \
  && grep -q 'WithoutOverlapping' "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q 'test_video_processing_job_uses_per_asset_overlap_lock_and_transcode_timeout_budget' "$ROOT/tests/Feature/VideoProcessingQueueTest.php" \
  && grep -q -- '-protocol_whitelist' "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q "'ffmpeg_binary'" "$ROOT/config/orvionart.php" \
  && grep -q "'processed_path' => \$processedPath" "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q "'thumbnail_path' => \$thumbnailPath" "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q -- "-c:v', 'libx264'" "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q -- "-c:a', 'aac'" "$ROOT/app/Jobs/ProcessUploadedVideo.php" \
  && grep -q "name('media.thumbnail')" "$ROOT/routes/web.php" \
  && grep -q 'test_public_thumbnail_is_not_available_for_an_unpublished_experience' "$ROOT/tests/Feature/VideoProcessingQueueTest.php" \
  && grep -Fq "processed_path ? 'video/mp4'" "$ROOT/resources/views/public/experience.blade.php" \
  && grep -q 'DB_QUEUE_RETRY_AFTER=360' "$ROOT/.env.example" \
  && grep -q "DB_QUEUE_RETRY_AFTER', 360" "$ROOT/config/queue.php" \
  && grep -q 'PruneVideoProcessingArtifacts::class' "$ROOT/bootstrap/app.php" \
  && grep -q 'test_pruner_removes_only_stale_temporary_video_derivatives' "$ROOT/tests/Feature/VideoProcessingArtifactPruneTest.php" \
  && grep -q 'orvionart:prune-video-processing-artifacts' "$ROOT/bootstrap/app.php"; then
  pass 'Video processing: FFprobe güvenliği, FFmpeg H.264/AAC dönüştürme, JPEG küçük resim, özel türev yolları, retry, admin önizleme ve kamu sunum koruması mevcut'
else
  fail 'Video işleme/dönüştürme altyapısı, güvenlik koruması veya regresyon kaynakları eksik'
fi

if [[ -f "$ROOT/app/Http/Controllers/PublicSite/BrandAssetController.php" && -f "$ROOT/docs/SITE_BUILDER_BRAND_ASSETS_0_10_55.md" ]] \
  && grep -q "name('brand.asset')" "$ROOT/routes/web.php" \
  && grep -q "'logo_file'=>\['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:4096'\]" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q "'favicon_file'=>\['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:2048'\]" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q 'test_site_brand_logo_and_favicon_uploads_are_private_and_served_through_safe_routes' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_replacing_or_removing_brand_assets_deletes_only_superseded_private_files' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_site_brand_upload_rejects_svg_and_other_unapproved_content_types' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q "X-Content-Type-Options' => 'nosniff'" "$ROOT/app/Http/Controllers/PublicSite/BrandAssetController.php"; then
  pass 'Site Builder logo/favicon: özel depolama, sabit kamu uçları, MIME/uzantı koruması, yaşam döngüsü ve regresyon kaynakları mevcut'
else
  fail 'Site Builder logo/favicon yönetimi, güvenli kamu sunumu, test veya belge eksik'
fi

if [[ -f "$ROOT/app/Http/Controllers/Admin/ExperienceController.php" && -f "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" ]] \
  && grep -q "'qrLinks' => \$experience->qrLinks()->latest()->get()" "$ROOT/app/Http/Controllers/Admin/ExperienceController.php" \
  && grep -q 'test_experience_editor_offers_qr_nfc_creation_and_shows_stable_destination' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'test_qr_nfc_creation_returns_to_the_experience_editor' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'data-copy-target' "$ROOT/resources/views/admin/experiences/form.blade.php" \
  && grep -q 'NFC etiketine yazılacak kalıcı adres' "$ROOT/resources/views/admin/experiences/form.blade.php" \
  && grep -q "redirect()->route('admin.experiences.edit', \$link->experience_id)" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php"; then
  pass 'Temel içerik akışı: deneyim ekranından QR/NFC oluşturma, sabit adresi kopyalama, QR indirme ve regresyon kaynakları mevcut'
else
  fail 'Deneyimden doğrudan QR/NFC akışı, kalıcı adres kopyalama veya regresyon kaynakları eksik'
fi

if grep -q "'publishedCount' => Experience::query()->publiclyAvailable()->count()" "$ROOT/app/Http/Controllers/Admin/DashboardController.php" \
  && grep -q "'mediaCount' => MediaAsset::count()" "$ROOT/app/Http/Controllers/Admin/DashboardController.php" \
  && grep -q "'activeQrCount' => QrLink::where('is_active', true)->count()" "$ROOT/app/Http/Controllers/Admin/DashboardController.php" \
  && [[ -s "$ROOT/docs/SIMPLE_ADMIN_WORKFLOW_0_10_57.md" ]] \
  && grep -q 'test_experience_editor_links_to_media_upload_with_experience_preselected' "$ROOT/tests/Feature/QrAndMediaTest.php"; then
  pass 'Süper Admin temel kullanım yolu ve gerçek kayıt sayıları mevcut'
else
  fail 'Süper Admin temel kullanım yolu, kayıt sayıları veya test kaynağı eksik'
fi
if grep -q 'experience-media-shortcut' "$ROOT/resources/views/admin/experiences/form.blade.php" \
  && grep -q "'selectedExperienceId' => \$selectedExperienceId" "$ROOT/app/Http/Controllers/Admin/MediaAssetController.php" \
  && grep -q 'redirectUrlForExperience(\$session->experience_id)' "$ROOT/app/Http/Controllers/Admin/VideoUploadController.php" \
  && grep -q 'test_media_upload_page_preselects_the_requested_experience_for_both_upload_modes' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'test_standard_media_upload_returns_to_the_experience_it_was_attached_to' "$ROOT/tests/Feature/QrAndMediaTest.php"; then
  pass 'Deneyimden medya yükleme, otomatik ön seçim ve deneyim ekranına geri dönüş mevcut'
else
  fail 'Deneyim-medya kolay akışı, geri dönüş veya test kaynakları eksik'
fi

if grep -q 'aynı ekrandan kalıcı QR/NFC bağlantısı üretin' "$ROOT/resources/views/admin/experiences/index.blade.php" \
  && grep -q 'QR/NFC bağlantısı yayımlanan deneyim ekranından oluşturulur' "$ROOT/resources/views/admin/experiences/index.blade.php" \
  && ! grep -q 'QR/NFC üretimi, medya yükleme ve ödeme sistemi henüz etkin değildir' "$ROOT/resources/views/admin/experiences/index.blade.php" \
  && grep -q 'test_experience_list_describes_qr_nfc_and_media_as_available_workflows' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && [[ -f "$ROOT/docs/QR_NFC_SOURCE_AUDIT_0_10_58.md" ]]; then
  pass 'Deneyim listesi QR/NFC açıklamaları güncel; regresyon test kaynağı ve QR/NFC denetim belgesi mevcut'
else
  fail 'Deneyim listesi QR/NFC metni tutarsız veya regresyon belgesi/testi eksik'
fi

if [[ -f "$ROOT/resources/views/admin/qr-links/preview.blade.php" ]] \
  && grep -q "name('qr-links.preview')" "$ROOT/routes/web.php" \
  && grep -q "name('qr-links.preview-image')" "$ROOT/routes/web.php" \
  && grep -q 'function previewImage' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q 'currentQrDestination' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q 'test_qr_preview_page_shows_print_controls_and_the_same_stable_nfc_url' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'test_qr_preview_image_is_inline_svg_and_still_checks_link_lifecycle' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'test_qr_link_list_offers_preview_and_nfc_copy_actions' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && [[ -f "$ROOT/docs/QR_NFC_PREVIEW_PRINT_0_10_59.md" ]]; then
  pass 'QR/NFC önizleme, yazdırma, NFC adres kopyalama, aynı hedef ve yaşam döngüsü kontrolleri ile regresyon kaynakları mevcut'
else
  fail 'QR/NFC önizleme/yazdırma akışı, yaşam döngüsü kontrolleri veya regresyon kaynakları eksik'
fi

if [[ -f "$ROOT/database/migrations/2026_10_16_000001_add_ends_at_to_experiences_table.php" ]] \
  && grep -q "'ends_at' => 'datetime'" "$ROOT/app/Models/Experience.php" \
  && grep -q "whereNull('ends_at')->orWhere('ends_at', '>', now())" "$ROOT/app/Models/Experience.php" \
  && grep -q 'test_expired_published_experience_is_not_publicly_available' "$ROOT/tests/Feature/PublicExperiencePlayerTest.php" \
  && grep -q 'test_expired_experience_qr_does_not_redirect_or_count_a_scan' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'İçeriğin erişim bitiş zamanı' "$ROOT/resources/views/admin/experiences/form.blade.php" \
  && [[ -f "$ROOT/docs/EXPERIENCE_ACCESS_EXPIRY_0_10_60.md" ]]; then
  pass 'Deneyim bitiş zamanı: yönetim alanı, sunucu tarafı erişim kontrolü, kalıcı QR güvenliği ve regresyon kaynakları mevcut'
else
  fail 'Deneyim bitiş zamanı kontrolü, yönetim alanı, test kaynakları veya belge eksik'
fi

if [[ -f "$ROOT/resources/views/admin/qr-links/preview.blade.php" ]] \
  && grep -q 'class="print-label"' "$ROOT/resources/views/admin/qr-links/preview.blade.php" \
  && grep -q 'class="label-brand">ORVIONART' "$ROOT/resources/views/admin/qr-links/preview.blade.php" \
  && grep -q 'Bu eserin hikâyesini ve ayrıntılarını görmek için QR kodu okutun.' "$ROOT/resources/views/admin/qr-links/preview.blade.php" \
  && grep -q 'test_qr_preview_page_shows_print_controls_and_the_same_stable_nfc_url' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && [[ -f "$ROOT/docs/QR_PRINTABLE_PRODUCT_LABEL_0_10_61.md" ]]; then
  pass 'Markalı QR ürün etiketi önizlemesi, yazdırma düzeni, kalıcı hedef ve regresyon kaynağı mevcut'
else
  fail 'Markalı QR ürün etiketi veya regresyon belgesi/test kaynağı eksik'
fi


if [[ -f "$ROOT/resources/views/public/qr-unavailable.blade.php" ]] \
  && grep -q "'state' => 'expired'" "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" \
  && grep -q "'state' => 'unavailable'" "$ROOT/app/Http/Controllers/PublicSite/QrRedirectController.php" \
  && grep -q 'assertStatus(410)' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && grep -q 'Bu içerik şu anda kullanılamıyor' "$ROOT/resources/views/public/qr-unavailable.blade.php" \
  && [[ -f "$ROOT/docs/QR_UNAVAILABLE_VISITOR_PAGE_0_10_62.md" ]] \
  && grep -q 'test_expired_experience_qr_does_not_redirect_or_count_a_scan' "$ROOT/tests/Feature/QrAndMediaTest.php"; then
  pass 'Devre dışı/süresi dolmuş QR için ziyaretçiye açıklayıcı, noindex ve tarama sayacını artırmayan yanıt kaynakları mevcut'
else
  fail 'QR/NFC kullanılamayan içerik ekranı veya regresyon kontrolleri eksik'
fi

if grep -q "'q' => \['nullable', 'string', 'max:120'\]" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q "'status' => \['nullable', 'in:all,active,inactive'\]" "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q 'withQueryString()' "$ROOT/app/Http/Controllers/Admin/QrLinkController.php" \
  && grep -q 'id="qr-search" name="q"' "$ROOT/resources/views/admin/qr-links/index.blade.php" \
  && grep -q 'id="qr-status" name="status"' "$ROOT/resources/views/admin/qr-links/index.blade.php" \
  && grep -q 'test_qr_link_list_can_search_label_or_experience_and_filter_active_state' "$ROOT/tests/Feature/QrAndMediaTest.php" \
  && [[ -f "$ROOT/docs/QR_ADMIN_SEARCH_FILTER_0_10_63.md" ]]; then
  pass 'QR/NFC yönetim listesi: arama, etkinlik filtresi, sorgu parametresi koruması ve regresyon kaynakları mevcut'
else
  fail 'QR/NFC arama/filtreleme, sayfalama parametreleri, regresyon testi veya belge eksik'
fi

if [[ -f "$ROOT/docs/SITE_BUILDER_GLOBAL_SOCIAL_IMAGE_0_10_64.md" ]] \
  && grep -q "'social_image_file'=>\['nullable','file','mimetypes:image/jpeg,image/png,image/webp','max:5120'\]" "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q "'social-image'" "$ROOT/routes/web.php" \
  && grep -q "og:image:alt" "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'test_global_social_share_image_is_private_and_used_as_public_page_fallback' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_global_social_share_image_rejects_svg_uploads' "$ROOT/tests/Feature/SiteBuilderTest.php"; then
  pass 'Site Builder varsayılan sosyal görseli: güvenli upload/sunum, SEO fallback ve regresyon kaynakları mevcut'
else
  fail 'Site Builder varsayılan sosyal görseli veya güvenlik/regresyon kaynakları eksik'
fi

if grep -q 'count($itemLines) > 100' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q 'isSafeSnapshotLink($item' "$ROOT/app/Http/Controllers/Admin/SiteBuilderController.php" \
  && grep -q 'test_site_builder_rejects_more_than_100_items_in_a_section' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_site_builder_rejects_oversized_item_titles_and_unsafe_slider_urls' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && grep -q 'test_site_builder_rejects_malformed_https_section_button_urls' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && [[ -f "$ROOT/docs/SITE_BUILDER_SECTION_VALIDATION_0_10_68.md" ]]; then
  pass 'Site Builder bölüm öğe sınırı ve güvenli bağlantı doğrulaması, regresyon kaynakları ve belge mevcut'
else
  fail 'Site Builder bölüm öğe sınırı/bağlantı doğrulaması, test veya belge eksik'
fi

if grep -Fq "(\$section['type']??'')==='contact'" "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'footerPhoneHref' "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'href="tel:{{ \$contactPhoneHref }}"' "$ROOT/resources/views/public/site-page.blade.php" \
  && grep -q 'test_site_builder_contact_section_and_footer_render_safe_clickable_contact_links' "$ROOT/tests/Feature/SiteBuilderTest.php" \
  && [[ -f "$ROOT/docs/SITE_BUILDER_CONTACT_LINKS_0_10_65.md" ]]; then
  pass 'Site Builder iletişim bölümü ve footer telefon/e-posta bağlantıları, tel şeması temizliği ve regresyon kaynağı mevcut'
else
  fail 'Site Builder iletişim bölümü/footer bağlantıları, telefon temizliği, regresyon testi veya belge eksik'
fi

if grep -q 'function checkOperationalLimits' "$ROOT/app/Console/Commands/ReleaseReadinessCheck.php" \
  && grep -q "'php_upload_max_filesize'" "$ROOT/app/Console/Commands/ReleaseReadinessCheck.php" \
  && grep -q "'php_post_max_size'" "$ROOT/app/Console/Commands/ReleaseReadinessCheck.php" \
  && grep -q "'queue_connection'" "$ROOT/app/Console/Commands/ReleaseReadinessCheck.php" \
  && grep -q "'ffmpeg_binary'" "$ROOT/app/Console/Commands/ReleaseReadinessCheck.php" \
  && grep -q 'test_release_check_reports_upload_queue_and_video_runtime_readiness_without_exposing_paths' "$ROOT/tests/Feature/ReleaseReadinessCheckTest.php" \
  && [[ -f "$ROOT/docs/RELEASE_READINESS_VIDEO_RUNTIME_0_10_69.md" ]]; then
  pass 'Kurulum hazırlık denetimi PHP yükleme limitleri, queue sürücüsü ve video ikililerini güvenli biçimde raporluyor'
else
  fail 'Kurulum hazırlık denetimi operasyonel video/yükleme kontrolleri, regresyon kaynağı veya belge eksik'
fi


if [[ -f "$ROOT/scripts/verify-restored-staging.sh" && -f "$ROOT/scripts/test-verify-restored-staging.sh" && -f "$ROOT/docs/RESTORED_STAGING_SCHEMA_VERIFY_0_10_72.md" ]] \
  && grep -q 'RESTORE_CONFIRM_TARGET_DB' "$ROOT/scripts/verify-restored-staging.sh" \
  && grep -q 'information_schema.tables' "$ROOT/scripts/verify-restored-staging.sh" \
  && grep -q 'COUNT(\*) FROM migrations' "$ROOT/scripts/verify-restored-staging.sh" \
  && grep -q 'missing required application table rejected' "$ROOT/scripts/test-verify-restored-staging.sh"; then
  if bash "$ROOT/scripts/test-verify-restored-staging.sh"; then pass 'Geri yükleme sonrası staging şeması: DB kimliği, salt-okunur durumu, temel tablolar ve migration geçmişi regresyonları'; else fail 'Geri yükleme sonrası staging şema doğrulama regresyonları başarısız'; fi
else
  fail 'Geri yükleme sonrası staging şema doğrulama betiği, regresyon testi veya belge eksik'
fi

if [[ -f "$ROOT/scripts/update-package-preflight.sh" && -f "$ROOT/scripts/test-update-package-preflight.sh" && -f "$ROOT/docs/UPDATE_PACKAGE_PREFLIGHT_0_10_74.md" ]] \
  && grep -q 'never deploys files' "$ROOT/scripts/update-package-preflight.sh" \
  && grep -q 'PREFLIGHT_PASS' "$ROOT/scripts/update-package-preflight.sh"; then
  if bash "$ROOT/scripts/test-update-package-preflight.sh"; then pass 'Güncelleme ZIP ön denetimi: checksum, ZIP yolu, gizli dosya, sürüm/manifest ve PHP sözdizimi regresyonları'; else fail 'Güncelleme ZIP ön denetimi regresyonları başarısız'; fi
else
  fail 'Güncelleme paketi ön denetimi, regresyon testi veya güvenlik sınırı belgesi eksik'
fi

echo "ORVIONART RELEASE GATE — sürüm $(cat "$ROOT/VERSION")"
echo "ÖZET PASS=$PASS WARN=$WARN FAIL=$FAIL"
if [[ "$FAIL" -gt 0 ]]; then echo 'SONUÇ: BLOCKED'; exit 1; fi
if [[ "$WARN" -gt 0 ]]; then echo 'SONUÇ: STATIC_CHECKS_PASS_RUNTIME_PENDING'; exit 0; fi
echo 'SONUÇ: CHECKS_PASS — canlıya çıkış onayı değildir'; exit 0
