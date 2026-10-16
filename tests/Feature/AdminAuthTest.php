<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AdminAuthTest extends TestCase
{
    use RefreshDatabase;
    public function test_guest_is_redirected_from_admin_dashboard(): void { $this->get('/admin')->assertRedirect('/admin/login'); }
    public function test_login_page_is_available_to_guests(): void { $this->get('/admin/login')->assertOk()->assertSee('Süper Admin'); }
    public function test_super_admin_can_log_in_and_access_dashboard(): void
    {
        $user = User::create(['name' => 'Test Admin', 'email' => 'admin@example.test', 'password' => 'A-very-long-test-password-2026', 'role' => 'super_admin', 'is_active' => true]);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'A-very-long-test-password-2026'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user); $this->get('/admin')->assertOk()->assertSee('Deneyim Yönetimi')->assertSee('Planlandı');
    }
    public function test_inactive_user_cannot_log_in(): void
    {
        User::create(['name' => 'Inactive Admin', 'email' => 'inactive@example.test', 'password' => 'A-very-long-test-password-2026', 'role' => 'super_admin', 'is_active' => false]);
        $this->post('/admin/login', ['email' => 'inactive@example.test', 'password' => 'A-very-long-test-password-2026'])->assertSessionHasErrors('email'); $this->assertGuest();
    }
    public function test_regular_role_cannot_access_admin_dashboard(): void
    {
        $user = User::create(['name' => 'Regular User', 'email' => 'user@example.test', 'password' => 'A-very-long-test-password-2026', 'role' => 'user', 'is_active' => true]);
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }
}
