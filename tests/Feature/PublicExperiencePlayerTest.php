<?php

namespace Tests\Feature;

use App\Models\Experience;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicExperiencePlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_experience_is_available_on_its_stable_public_url(): void
    {
        $experience = Experience::create([
            'title' => 'Çini Atölyesi',
            'slug' => 'cini-atolyesi',
            'status' => 'published',
            'summary' => 'Geleneksel üretim hikâyesi.',
            'body' => "Birinci satır.\nİkinci satır.",
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))
            ->assertOk()
            ->assertSee('Çini Atölyesi')
            ->assertSee('Geleneksel üretim hikâyesi.')
            ->assertSee('Birinci satır.')
            ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');
    }

    public function test_draft_and_archived_experiences_are_not_publicly_available(): void
    {
        foreach (['draft', 'archived'] as $status) {
            $experience = Experience::create([
                'title' => 'Gizli içerik ' . $status,
                'slug' => 'gizli-' . $status,
                'status' => $status,
                'published_at' => now()->subDay(),
            ]);

            $this->get(route('experiences.public', [
                'publicId' => $experience->public_id,
                'slug' => $experience->slug,
            ]))->assertNotFound();
        }
    }

    public function test_future_scheduled_experience_is_not_publicly_available(): void
    {
        $experience = Experience::create([
            'title' => 'Gelecekte açılacak',
            'slug' => 'gelecekte-acilacak',
            'status' => 'scheduled',
            'scheduled_at' => now()->addDay(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))->assertNotFound();
    }

    public function test_due_scheduled_experience_is_available_without_a_background_job(): void
    {
        $experience = Experience::create([
            'title' => 'Zamanı gelen deneyim',
            'slug' => 'zamani-gelen-deneyim',
            'status' => 'scheduled',
            'scheduled_at' => now()->subMinute(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))->assertOk()->assertSee('Zamanı gelen deneyim');
    }

    public function test_expired_published_experience_is_not_publicly_available(): void
    {
        $experience = Experience::create([
            'title' => 'Süresi dolan deneyim',
            'slug' => 'suresi-dolan-deneyim',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'ends_at' => now()->subMinute(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))->assertNotFound();

        $this->assertFalse($experience->fresh()->isPubliclyAvailable());
        $this->assertFalse(Experience::query()->publiclyAvailable()->whereKey($experience->id)->exists());
    }

    public function test_experience_with_future_end_time_remains_publicly_available(): void
    {
        $experience = Experience::create([
            'title' => 'Bitiş tarihi gelecek deneyim',
            'slug' => 'bitis-tarihi-gelecek-deneyim',
            'status' => 'published',
            'published_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))->assertOk()->assertSee('Bitiş tarihi gelecek deneyim');
    }

    public function test_changed_slug_redirects_to_the_canonical_slug_without_changing_public_id(): void
    {
        $experience = Experience::create([
            'title' => 'Yeni Başlık',
            'slug' => 'yeni-baslik',
            'status' => 'published',
            'published_at' => now()->subMinute(),
        ]);
        $publicId = $experience->public_id;

        $this->get(route('experiences.public', [
            'publicId' => $publicId,
            'slug' => 'eski-baslik',
        ]))->assertRedirect(route('experiences.public', [
            'publicId' => $publicId,
            'slug' => 'yeni-baslik',
        ]));

        $this->assertSame($publicId, $experience->fresh()->public_id);
    }

    public function test_html_in_experience_body_is_rendered_as_text(): void
    {
        $experience = Experience::create([
            'title' => 'Güvenli içerik',
            'slug' => 'guvenli-icerik',
            'status' => 'published',
            'body' => '<script>alert(1)</script>',
            'published_at' => now()->subMinute(),
        ]);

        $this->get(route('experiences.public', [
            'publicId' => $experience->public_id,
            'slug' => $experience->slug,
        ]))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
