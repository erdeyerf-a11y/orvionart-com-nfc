<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Models\Experience;
use App\Models\QrLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name'=>'Maintenance Admin','email'=>'maintenance@example.test','password'=>'A-very-long-test-password-2026','role'=>'super_admin','is_active'=>true]);
    }

    public function test_public_destinations_share_maintenance_response_when_enabled(): void
    {
        SiteSetting::updateOrCreate(['setting_key'=>'general'], ['setting_value'=>[
            'site_name'=>'ORVIONART','maintenance_mode'=>true,'maintenance_message'=>'Planlı bakım sürüyor.',
        ]]);

        $this->get('/')->assertStatus(503)->assertSee('Planlı bakım sürüyor.')
            ->assertHeader('X-Robots-Tag','noindex, nofollow, noarchive')
            ->assertHeader('Cache-Control','must-revalidate, no-cache, no-store, private');
        $this->get('/demo')->assertStatus(503)->assertSee('Planlı bakım sürüyor.');
        $experience = Experience::create(['title'=>'Bakım deneyimi','slug'=>'bakim-deneyimi','status'=>'published','published_at'=>now()->subMinute()]);
        $link = QrLink::create(['experience_id'=>$experience->id,'label'=>'Bakım QR','is_active'=>true]);
        $this->get(route('qr.redirect', ['token'=>$link->token]))->assertStatus(503);
        $this->assertSame(0, $link->fresh()->scan_count);
        $this->get('/sitemap.xml')->assertStatus(503);
    }

    public function test_admin_and_health_remain_available_during_maintenance(): void
    {
        SiteSetting::updateOrCreate(['setting_key'=>'general'], ['setting_value'=>[
            'site_name'=>'ORVIONART','maintenance_mode'=>true,'maintenance_message'=>'Planlı bakım sürüyor.',
        ]]);

        $this->get('/health')->assertOk(); // Health reports DB/application readiness independently.
        $this->actingAs($this->admin())->get('/admin')->assertOk();
        $this->get('/robots.txt')->assertOk();
    }

    public function test_public_destinations_work_normally_when_maintenance_is_disabled(): void
    {
        SiteSetting::updateOrCreate(['setting_key'=>'general'], ['setting_value'=>[
            'site_name'=>'ORVIONART','maintenance_mode'=>false,
        ]]);
        $this->get('/')->assertNotFound(); // No homepage has been published yet.
    }
}
