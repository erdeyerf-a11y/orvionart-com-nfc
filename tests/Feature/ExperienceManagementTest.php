<?php

namespace Tests\Feature;

use App\Models\Experience;
use App\Models\QrLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExperienceManagementTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::create([
            'name' => 'Test Super Admin',
            'email' => 'admin@example.test',
            'password' => 'test-password-long-enough',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_experience_management(): void
    {
        $this->get(route('admin.experiences.index'))->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_can_open_experience_list(): void
    {
        $this->actingAs($this->superAdmin())
            ->get(route('admin.experiences.index'))
            ->assertOk()
            ->assertSee('Deneyimler')
            ->assertSee('Yeni deneyim');
    }

    public function test_super_admin_can_create_experience_and_audit_log(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.experiences.store'), [
            'title' => 'Seramik Atölyesi',
            'summary' => 'Seramik üretiminin hikâyesi.',
            'body' => 'İçerik taslağı.',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('experiences', [
            'title' => 'Seramik Atölyesi',
            'slug' => 'seramik-atolyesi',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'experience.created',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_scheduled_experience_requires_a_schedule_date(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('admin.experiences.create'))
            ->post(route('admin.experiences.store'), [
                'title' => 'Zamanlı Deneyim',
                'status' => 'scheduled',
            ])
            ->assertSessionHasErrors('scheduled_at');
    }


    public function test_experience_editor_supports_an_optional_access_end_time(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin)->post(route('admin.experiences.store'), [
            'title' => 'Süreli Deneyim',
            'status' => 'published',
            'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ])->assertRedirect();

        $this->assertDatabaseHas('experiences', [
            'title' => 'Süreli Deneyim',
            'status' => 'published',
        ]);
        $experience = Experience::query()->where('title', 'Süreli Deneyim')->firstOrFail();
        $this->assertNotNull($experience->ends_at);
        $this->actingAs($admin)->get(route('admin.experiences.edit', $experience))
            ->assertOk()->assertSee('İçeriğin erişim bitiş zamanı');
    }

    public function test_scheduled_experience_end_time_must_follow_publication_time(): void
    {
        $this->actingAs($this->superAdmin())
            ->from(route('admin.experiences.create'))
            ->post(route('admin.experiences.store'), [
                'title' => 'Geçersiz zaman aralığı',
                'status' => 'scheduled',
                'scheduled_at' => now()->addDays(3)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_moving_experience_to_draft_deactivates_active_qr_links_and_audits_the_change(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'created_by' => $admin->id,
            'title' => 'Yayındaki deneyim',
            'slug' => 'yayindaki-deneyim',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Sabit QR',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.experiences.update', $experience), [
            'title' => $experience->title,
            'slug' => $experience->slug,
            'status' => 'draft',
            'summary' => '',
            'body' => '',
        ])->assertRedirect();

        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'qr_link.auto_deactivated',
            'subject_type' => QrLink::class,
            'subject_id' => (string) $link->id,
        ]);
        $this->get(route('qr.redirect', ['token' => $link->token]))->assertNotFound();
    }

    public function test_soft_deleting_experience_deactivates_qr_links_without_deleting_the_qr_record(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'created_by' => $admin->id,
            'title' => 'Arşivlenecek deneyim',
            'slug' => 'arsivlenecek-deneyim-qr',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $link = QrLink::create([
            'experience_id' => $experience->id,
            'created_by' => $admin->id,
            'label' => 'Arşiv QR',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->delete(route('admin.experiences.destroy', $experience))->assertRedirect();

        $this->assertSoftDeleted('experiences', ['id' => $experience->id]);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'qr_link.auto_deactivated',
            'subject_id' => (string) $link->id,
        ]);
        $this->assertDatabaseHas('qr_links', ['id' => $link->id, 'token' => $link->token]);
    }

    public function test_deleting_experience_soft_deletes_it_and_keeps_audit_record(): void
    {
        $admin = $this->superAdmin();
        $experience = Experience::create([
            'created_by' => $admin->id,
            'title' => 'Silinecek değil arşivlenecek',
            'slug' => 'arsivlenecek-deneyim',
            'status' => 'draft',
        ]);

        $this->actingAs($admin)->delete(route('admin.experiences.destroy', $experience))->assertRedirect(route('admin.experiences.index'));
        $this->assertSoftDeleted('experiences', ['id' => $experience->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'experience.deleted', 'subject_id' => (string) $experience->id]);
    }
}
