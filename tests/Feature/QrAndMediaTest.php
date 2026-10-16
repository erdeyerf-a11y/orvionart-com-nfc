<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\MediaAsset;
use App\Models\QrLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QrAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin-qr@example.test',
            'password' => 'test-password-long-enough',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }


    public function test_experience_editor_links_to_media_upload_with_experience_preselected(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Mavi Çini Tabak',
            'slug' => 'mavi-cini-tabak',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experiences.edit', $experience))
            ->assertOk()
            ->assertSee('Bu içeriğe görsel veya video ekle')
            ->assertSee(route('admin.media.index', ['experience_id' => $experience->id]));
    }

    public function test_media_upload_page_preselects_the_requested_experience_for_both_upload_modes(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Seramik Üretimi',
            'slug' => 'seramik-uretimi',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.media.index', ['experience_id' => $experience->id]))
            ->assertOk()
            ->assertSee('id="experience_id" name="experience_id"', false)
            ->assertSee('id="chunk_video_experience"', false)
            ->assertSee('value="'.$experience->id.'" selected', false);
    }

    public function test_standard_media_upload_returns_to_the_experience_it_was_attached_to(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'El Yapımı Kase',
            'slug' => 'el-yapimi-kase',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.media.store'), [
                'file' => UploadedFile::fake()->image('kase.png'),
                'title' => 'Kase fotoğrafı',
                'alt_text' => 'El yapımı kase',
                'experience_id' => $experience->id,
            ])
            ->assertRedirect(route('admin.experiences.edit', $experience));

        $this->assertDatabaseHas('media_assets', [
            'experience_id' => $experience->id,
            'title' => 'Kase fotoğrafı',
            'kind' => 'image',
        ]);
    }

    public function test_experience_list_describes_qr_nfc_and_media_as_available_workflows(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.experiences.index'))
            ->assertOk()
            ->assertSee('aynı ekrandan kalıcı QR/NFC bağlantısı üretin')
            ->assertSee('QR/NFC bağlantısı yayımlanan deneyim ekranından oluşturulur')
            ->assertDontSee('QR/NFC üretimi, medya yükleme ve ödeme sistemi henüz etkin değildir');
    }

    public function test_experience_editor_offers_qr_nfc_creation_and_shows_stable_destination(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'El Yapımı Çini Tabak',
            'slug' => 'el-yapimi-cini-tabak',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Çini tabak etiketi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.experiences.edit', $experience))
            ->assertOk()
            ->assertSee('QR / NFC bağlantısı')
            ->assertSee('Bu deneyim için QR/NFC oluştur')
            ->assertSee('Adresi kopyala')
            ->assertSee($link->redirectUrl())
            ->assertSee(route('admin.qr-links.image', $link));
    }

    public function test_qr_nfc_creation_returns_to_the_experience_editor(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Mavi Seramik Vazo',
            'slug' => 'mavi-seramik-vazo',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.qr-links.store'), [
                'experience_id' => $experience->id,
                'label' => 'Vazo etiketi',
            ])
            ->assertRedirect(route('admin.experiences.edit', $experience));

        $this->assertDatabaseHas('qr_links', [
            'experience_id' => $experience->id,
            'label' => 'Vazo etiketi',
            'is_active' => true,
        ]);
    }

    public function test_qr_redirect_counts_successful_visit_and_redirects_to_public_experience(): void
    {
        $experience = Experience::create(['title' => 'Çini Vazo', 'slug' => 'cini-vazo', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Vazo etiketi', 'is_active' => true]);

        $this->get(route('qr.redirect', ['token' => $link->token]))
            ->assertRedirect($experience->publicUrl())
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->assertHeader('Pragma', 'no-cache')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertSame(1, $link->fresh()->scan_count);
        $this->assertNotNull($link->fresh()->last_scanned_at);
    }


    public function test_stable_qr_link_uses_the_current_experience_slug_without_changing_the_token(): void
    {
        $experience = Experience::create([
            'title' => 'Çini Tabak',
            'slug' => 'cini-tabak-eski',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Tabak QR', 'is_active' => true]);
        $originalToken = $link->token;

        $experience->update(['slug' => 'cini-tabak-yeni']);

        $this->get(route('qr.redirect', ['token' => $originalToken]))
            ->assertRedirect($experience->fresh()->publicUrl())
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

        $this->assertSame($originalToken, $link->fresh()->token);
        $this->assertSame(1, $link->fresh()->scan_count);
    }


    public function test_qr_image_is_not_generated_for_a_disabled_link(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Devre dışı QR hedefi',
            'slug' => 'devre-disi-qr-hedefi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Devre dışı QR',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.image', $link))
            ->assertNotFound();
    }

    public function test_qr_image_is_available_for_a_future_scheduled_experience(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Zamanlanmış QR hedefi',
            'slug' => 'zamanlanmis-qr-hedefi',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Zamanlanmış QR',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.image', $link))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'private, no-store')
            ->assertSee('<svg', false);
    }

    public function test_qr_image_is_not_generated_for_an_active_link_with_a_draft_target(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Taslak QR hedefi',
            'slug' => 'taslak-qr-gorsel-hedefi',
            'status' => 'draft',
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Tutarsız etkin QR',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.image', $link))
            ->assertNotFound();
    }

    public function test_super_admin_qr_toggle_records_the_matching_state_change(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'QR geçiş testi',
            'slug' => 'qr-gecis-testi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Geçiş testi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.qr-links.toggle', $link))
            ->assertRedirect(route('admin.qr-links.index'));

        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'qr_link.deactivated',
            'subject_type' => QrLink::class,
            'subject_id' => (string) $link->id,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.qr-links.toggle', $link))
            ->assertRedirect(route('admin.qr-links.index'));

        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => true]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'qr_link.activated',
            'subject_type' => QrLink::class,
            'subject_id' => (string) $link->id,
        ]);
    }


    public function test_qr_link_cannot_be_reactivated_when_experience_is_draft(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Taslak QR hedefi',
            'slug' => 'taslak-qr-hedefi',
            'status' => 'draft',
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Kapalı taslak QR',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.qr-links.toggle', $link))
            ->assertRedirect(route('admin.qr-links.index'))
            ->assertSessionHasErrors('qr_link');

        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => false]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'qr_link.activated',
            'subject_id' => (string) $link->id,
        ]);
    }

    public function test_qr_redirect_does_not_count_a_scan_when_public_destination_is_missing(): void
    {
        $experience = Experience::create([
            'title' => 'Eksik public ID',
            'slug' => 'eksik-public-id',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        // Simulate a legacy/corrupt row that bypassed the model's creating hook.
        \Illuminate\Support\Facades\DB::table('experiences')
            ->where('id', $experience->id)
            ->update(['public_id' => null]);

        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Hedefsiz QR',
            'is_active' => true,
        ]);

        $this->get(route('qr.redirect', ['token' => $link->token]))->assertNotFound();

        $this->assertSame(0, $link->fresh()->scan_count);
        $this->assertNull($link->fresh()->last_scanned_at);
    }

    public function test_expired_experience_qr_does_not_redirect_or_count_a_scan(): void
    {
        $experience = Experience::create([
            'title' => 'Süresi dolmuş QR hedefi',
            'slug' => 'suresi-dolmus-qr-hedefi',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'label' => 'Süresi dolmuş QR',
            'is_active' => true,
        ]);

        $this->get(route('qr.redirect', ['token' => $link->token]))
            ->assertStatus(410)
            ->assertSee('Bu içeriğin erişim süresi sona erdi')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'scan_count' => 0, 'last_scanned_at' => null]);
    }

    public function test_disabled_qr_link_does_not_redirect(): void
    {
        $experience = Experience::create(['title' => 'Çini Vazo', 'slug' => 'cini-vazo', 'status' => 'published', 'published_at' => now()->subMinute()]);
        $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Kapalı etiket', 'is_active' => false]);
        $this->get(route('qr.redirect', ['token' => $link->token]))
            ->assertNotFound()
            ->assertSee('Bu içerik şu anda kullanılamıyor');
        $this->assertSame(0, $link->fresh()->scan_count);
    }

    public function test_qr_link_does_not_count_visits_when_target_is_not_publicly_available(): void
    {
        $cases = [
            ['status' => 'draft'],
            ['status' => 'published', 'published_at' => now()->addDay()],
            ['status' => 'scheduled', 'scheduled_at' => now()->addDay()],
            ['status' => 'archived', 'archived_at' => now()->subMinute()],
        ];

        foreach ($cases as $index => $attributes) {
            $experience = Experience::create(array_merge([
                'title' => 'Kapalı deneyim ' . $index,
                'slug' => 'kapali-deneyim-' . $index,
            ], $attributes));
            $link = QrLink::create(['experience_id' => $experience->id, 'label' => 'Etiket ' . $index, 'is_active' => true]);

            $this->get(route('qr.redirect', ['token' => $link->token]))->assertNotFound();
            $this->assertSame(0, $link->fresh()->scan_count);
        }
    }

    public function test_public_experience_query_and_model_availability_rule_agree_for_scheduled_experience(): void
    {
        $ready = Experience::create([
            'title' => 'Zamanı gelen deneyim', 'slug' => 'zamani-gelen-deneyim',
            'status' => 'scheduled', 'scheduled_at' => now()->subMinute(),
        ]);
        $future = Experience::create([
            'title' => 'Gelecekteki deneyim', 'slug' => 'gelecekteki-deneyim',
            'status' => 'scheduled', 'scheduled_at' => now()->addDay(),
        ]);

        $this->assertTrue($ready->isPubliclyAvailable());
        $this->assertTrue(Experience::query()->publiclyAvailable()->whereKey($ready->id)->exists());
        $this->assertFalse($future->isPubliclyAvailable());
        $this->assertFalse(Experience::query()->publiclyAvailable()->whereKey($future->id)->exists());
    }

    public function test_super_admin_can_upload_private_image_media(): void
    {
        Storage::fake('local');
        $experience = Experience::create(['title' => 'Çini Vazo', 'slug' => 'cini-vazo', 'status' => 'draft']);
        $admin = $this->superAdmin();

        $this->actingAs($admin)->post(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('vazo.jpg'),
            'title' => 'Vazo fotoğrafı',
            'alt_text' => 'Mavi beyaz vazo',
            'experience_id' => $experience->id,
        ])->assertRedirect(route('admin.media.index'));

        $asset = MediaAsset::firstOrFail();
        $this->assertSame('image', $asset->kind);
        Storage::disk('local')->assertExists($asset->path);
    }

    public function test_private_media_is_not_served_when_experience_is_draft(): void
    {
        Storage::fake('local');
        $experience = Experience::create(['title' => 'Taslak', 'slug' => 'taslak-medya', 'status' => 'draft']);
        $path = UploadedFile::fake()->image('secret.jpg')->store('orvionart-media/test', 'local');
        $asset = MediaAsset::create(['experience_id' => $experience->id, 'title' => 'Gizli', 'disk' => 'local', 'path' => $path, 'original_name' => 'secret.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'file_size' => 100]);

        $this->get(route('media.public', ['asset' => $asset->id]))->assertNotFound();
    }
    public function test_super_admin_can_detach_experience_media_without_deleting_the_file(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Yayındaki deneyim',
            'slug' => 'yayindaki-deneyim-medya',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $path = UploadedFile::fake()->image('vazo.jpg')->store('orvionart-media/test', 'local');
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'uploaded_by' => $admin->id,
            'title' => 'Vazo',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'vazo.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 100,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.media.detach-experience', $asset))
            ->assertRedirect(route('admin.media.index'));

        $this->assertNull($asset->fresh()->experience_id);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'media.detached_from_experience',
            'subject_id' => (string) $asset->id,
            'actor_id' => $admin->id,
        ]);
        $this->get(route('media.public', $asset))->assertNotFound();
    }

    public function test_attached_experience_media_cannot_be_deleted_until_detached(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $experience = Experience::create(['title' => 'Taslak deneyim', 'slug' => 'taslak-deneyim-medya-silme', 'status' => 'draft']);
        $path = UploadedFile::fake()->image('silinmeyecek.jpg')->store('orvionart-media/test', 'local');
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'uploaded_by' => $admin->id,
            'title' => 'Bağlı dosya',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'silinmeyecek.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 100,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.media.index'))
            ->delete(route('admin.media.destroy', $asset))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHasErrors('media');

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'experience_id' => $experience->id]);
        Storage::disk('local')->assertExists($path);
    }

    public function test_unreferenced_media_deletion_uses_private_quarantine_and_removes_bytes(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $path = 'orvionart-media/2026/10/remove-me.jpg';
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $asset = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => $admin->id,
            'title' => 'Silinecek medya', 'disk' => 'local', 'path' => $path,
            'original_name' => 'remove-me.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 16,
        ]);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))
            ->assertRedirect(route('admin.media.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('media_assets', ['id' => $asset->id]);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], Storage::disk('local')->allFiles('orvionart-trash'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'media.deleted',
            'subject_id' => (string) $asset->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_media_on_non_private_disk_is_not_moved_to_quarantine_or_deleted(): void
    {
        Storage::fake('public');
        $admin = $this->superAdmin();
        $path = 'orvionart-media/2026/10/legacy-public.jpg';
        Storage::disk('public')->put($path, 'legacy-public-bytes');
        $asset = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => $admin->id,
            'title' => 'Eski herkese açık disk kaydı', 'disk' => 'public', 'path' => $path,
            'original_name' => 'legacy-public.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 19,
        ]);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))
            ->assertStatus(409);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'disk' => 'public']);
        Storage::disk('public')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles('orvionart-trash'));
    }

    public function test_media_record_is_not_deleted_when_storage_file_is_missing(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $asset = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => $admin->id,
            'title' => 'Dosyası eksik', 'disk' => 'local',
            'path' => 'orvionart-media/2026/10/missing.jpg',
            'original_name' => 'missing.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 16,
        ]);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))
            ->assertStatus(409);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_media_delete_rejects_path_outside_safe_managed_prefixes(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();
        $asset = MediaAsset::create([
            'experience_id' => null, 'site_page_id' => null, 'uploaded_by' => $admin->id,
            'title' => 'Güvensiz yol', 'disk' => 'local',
            'path' => 'orvionart-media/../outside.jpg',
            'original_name' => 'outside.jpg', 'mime_type' => 'image/jpeg',
            'kind' => 'image', 'file_size' => 16,
        ]);

        $this->actingAs($admin)->delete(route('admin.media.destroy', $asset))
            ->assertStatus(409);

        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_public_media_is_served_from_private_local_disk_for_published_experience(): void
    {
        Storage::fake('local');
        $experience = Experience::create([
            'title' => 'Yayındaki deneyim medyası',
            'slug' => 'yayindaki-deneyim-medyasi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $path = 'orvionart-media/2026/10/public-image.jpg';
        Storage::disk('local')->put($path, 'image-bytes');
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Yayın görseli',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'public-image.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 11,
        ]);

        $this->get(route('media.public', $asset))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
    }

    public function test_public_media_rejects_assets_on_non_local_disks(): void
    {
        Storage::fake('public');
        $experience = Experience::create([
            'title' => 'Eski disk deneyimi',
            'slug' => 'eski-disk-deneyimi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $path = 'orvionart-media/2026/10/legacy.jpg';
        Storage::disk('public')->put($path, 'legacy-bytes');
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Eski herkese açık disk medyası',
            'disk' => 'public',
            'path' => $path,
            'original_name' => 'legacy.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 12,
        ]);

        $this->get(route('media.public', $asset))->assertNotFound();
    }

    public function test_public_media_rejects_paths_outside_managed_media_prefixes(): void
    {
        Storage::fake('local');
        $experience = Experience::create([
            'title' => 'Yol kontrol deneyimi',
            'slug' => 'yol-kontrol-deneyimi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Güvensiz medya yolu',
            'disk' => 'local',
            'path' => 'orvionart-media/../outside.jpg',
            'original_name' => 'outside.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 12,
        ]);

        $this->get(route('media.public', $asset))->assertNotFound();
    }

    public function test_public_media_rejects_a_directory_even_when_its_path_is_inside_managed_media_scope(): void
    {
        Storage::fake('local');
        $experience = Experience::create([
            'title' => 'Dizin hedefi deneyimi',
            'slug' => 'dizin-hedefi-deneyimi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $path = 'orvionart-media/2026/10/directory.jpg';
        Storage::disk('local')->makeDirectory($path);
        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Dosya yerine dizin',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'directory.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 0,
        ]);

        $this->get(route('media.public', $asset))->assertNotFound();
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id, 'path' => $path]);
    }

    public function test_public_media_does_not_follow_a_symlink_outside_private_storage(): void
    {
        Storage::fake('local');
        $experience = Experience::create([
            'title' => 'Sembolik bağlantı deneyimi',
            'slug' => 'sembolik-baglanti-deneyimi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $path = 'orvionart-media/2026/10/outside.jpg';
        $disk = Storage::disk('local');
        $linkPath = $disk->path($path);
        @mkdir(dirname($linkPath), 0777, true);
        $outsidePath = tempnam(sys_get_temp_dir(), 'orvionart-outside-');
        file_put_contents($outsidePath, 'outside-private-root');

        if (!@symlink($outsidePath, $linkPath)) {
            @unlink($outsidePath);
            $this->markTestSkipped('Bu ortamda sembolik bağlantı oluşturulamıyor.');
        }

        $asset = MediaAsset::create([
            'experience_id' => $experience->id,
            'title' => 'Sembolik bağlantı medyası',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'outside.jpg',
            'mime_type' => 'image/jpeg',
            'kind' => 'image',
            'file_size' => 20,
        ]);

        try {
            $this->get(route('media.public', $asset))->assertNotFound();
        } finally {
            @unlink($linkPath);
            @unlink($outsidePath);
        }
    }

    public function test_qr_preview_page_shows_print_controls_and_the_same_stable_nfc_url(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Önizleme çini tabağı',
            'slug' => 'onizleme-cini-tabagi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Önizleme etiketi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.preview', $link))
            ->assertOk()
            ->assertSee('QR kodunu yazdır')
            ->assertSee('ORVIONART')
            ->assertSee('Bu eserin hikâyesini ve ayrıntılarını görmek için QR kodu okutun.')
            ->assertSee('Adresi kopyala')
            ->assertSee($link->redirectUrl())
            ->assertSee(route('admin.qr-links.preview-image', $link));
    }

    public function test_qr_preview_image_is_inline_svg_and_still_checks_link_lifecycle(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Önizleme görsel hedefi',
            'slug' => 'onizleme-gorsel-hedefi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Önizleme SVG',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.preview-image', $link))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'inline; filename="orvionart-qr-'.$link->id.'.svg"')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('<svg', false);

        $link->update(['is_active' => false]);
        $this->actingAs($admin)->get(route('admin.qr-links.preview-image', $link))->assertNotFound();
    }

    public function test_qr_link_list_can_search_label_or_experience_and_filter_active_state(): void
    {
        $admin = $this->superAdmin();
        $matchingExperience = Experience::create([
            'title' => 'Mavi Seramik Vazo',
            'slug' => 'mavi-seramik-vazo-filtre',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $otherExperience = Experience::create([
            'title' => 'Kırmızı Çini Tabak',
            'slug' => 'kirmizi-cini-tabak-filtre',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $activeLink = QrLink::create([
            'experience_id' => $matchingExperience->id,
            'created_by' => $admin->id,
            'label' => 'Vazo ürün etiketi',
            'is_active' => true,
        ]);
        QrLink::create([
            'experience_id' => $matchingExperience->id,
            'created_by' => $admin->id,
            'label' => 'Eski vazo etiketi',
            'is_active' => false,
        ]);
        QrLink::create([
            'experience_id' => $otherExperience->id,
            'created_by' => $admin->id,
            'label' => 'Tabak etiketi',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.index', ['q' => 'Mavi', 'status' => 'active']))
            ->assertOk()
            ->assertSee('id="qr-search"', false)
            ->assertSee('id="qr-status"', false)
            ->assertSee($activeLink->redirectUrl())
            ->assertDontSee('Eski vazo etiketi')
            ->assertDontSee('Tabak etiketi');

        $this->actingAs($admin)
            ->get(route('admin.qr-links.index', ['q' => 'Eski vazo etiketi', 'status' => 'inactive']))
            ->assertOk()
            ->assertSee('Eski vazo etiketi')
            ->assertDontSee('Vazo ürün etiketi')
            ->assertDontSee('Tabak etiketi');
    }

    public function test_qr_link_list_offers_preview_and_nfc_copy_actions(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'title' => 'Liste QR deneyimi',
            'slug' => 'liste-qr-deneyimi',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Liste bağlantısı',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.qr-links.index'))
            ->assertOk()
            ->assertSee(route('admin.qr-links.preview', $link))
            ->assertSee('NFC adresini kopyala')
            ->assertSee($link->redirectUrl());
    }



    public function test_super_admin_can_export_filtered_qr_nfc_inventory_with_formula_safe_cells(): void
    {
        $admin = $this->superAdmin();
        $matching = Experience::create([
            'title' => 'Çini Vazo',
            'slug' => 'cini-vazo-export',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $matching->id,
            'created_by' => $admin->id,
            'label' => '=HYPERLINK("https://bad.example","open")',
            'is_active' => true,
            'scan_count' => 7,
        ]);
        $otherExperience = Experience::create([
            'title' => 'Seramik Kase',
            'slug' => 'seramik-kase-export',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        QrLink::create([
            'experience_id' => $otherExperience->id,
            'created_by' => $admin->id,
            'label' => 'Arşiv etiketi',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin)->get('/admin/qr-links/export?q=Vazo&status=active');
        $response->assertOk()
            ->assertDownload()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'private, no-store, no-cache, must-revalidate');
        $csv = $response->streamedContent();
        $this->assertStringContainsString("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString($link->redirectUrl(), $csv);
        $this->assertStringContainsString('Çini Vazo', $csv);
        $this->assertStringContainsString(',7,', $csv);
        $this->assertStringNotContainsString('Arşiv etiketi', $csv);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'qr_link.export_prepared',
        ]);
    }

    public function test_non_admin_cannot_export_qr_nfc_inventory(): void
    {
        $user = User::create([
            'name' => 'Normal Kullanıcı',
            'email' => 'regular-qr-export@example.test',
            'password' => 'test-password-long-enough',
            'role' => 'user',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/admin/qr-links/export')->assertForbidden();
    }
}
