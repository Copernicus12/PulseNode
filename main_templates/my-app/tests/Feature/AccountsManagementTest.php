<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountsManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_accounts_center_and_sees_header_entry(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $page = $this->get(route('dashboard'));
        $page->assertOk();
        $page->assertSee('id="app-accounts-panel"', false);
        $page->assertSee(route('accounts.index'), false);

        $response = $this->get(route('accounts.index'));

        $response->assertOk();
        $response->assertSee('id="accounts-page-root"', false);
        $response->assertSee('accounts-page.ts', false);
    }

    public function test_non_admin_users_cannot_access_the_accounts_center(): void
    {
        $moderator = User::factory()->moderator()->create();
        $this->actingAs($moderator);

        $response = $this->get(route('accounts.index'));

        $response->assertForbidden();
    }

    public function test_admin_can_create_a_guest_account_with_an_expiry_window(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $response = $this->post(route('accounts.store'), [
            'name' => 'Guest Tester',
            'email' => 'guest@example.com',
            'password' => 'StrongPass123!',
            'password_confirmation' => 'StrongPass123!',
            'role' => User::ROLE_GUEST,
            'guest_duration_hours' => 2,
        ]);

        $response->assertRedirect(route('accounts.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'guest@example.com',
            'role' => User::ROLE_GUEST,
            'is_blocked' => false,
        ]);

        $guest = User::query()->where('email', 'guest@example.com')->firstOrFail();
        $this->assertNotNull($guest->guest_expires_at);
    }

    public function test_expired_guest_accounts_are_blocked_and_logged_out_on_next_request(): void
    {
        $guest = User::factory()->guest(now()->subHour())->create();
        $this->actingAs($guest);

        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'id' => $guest->id,
            'is_blocked' => true,
        ]);
    }

    public function test_admin_can_update_their_profile_from_accounts(): void
    {
        $admin = User::factory()->admin()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->patch(route('accounts.profile.update'), [
            'name' => 'Updated Admin',
            'email' => 'updated-admin@example.com',
        ]);

        $response->assertRedirect(route('accounts.index'));

        $admin->refresh();

        $this->assertSame('Updated Admin', $admin->name);
        $this->assertSame('updated-admin@example.com', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }

    public function test_admin_can_update_their_password_from_accounts(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $response = $this->put(route('accounts.password.update'), [
            'current_password' => 'password',
            'password' => 'VeryStrongPass123!',
            'password_confirmation' => 'VeryStrongPass123!',
        ]);

        $response->assertRedirect(route('accounts.index'));

        $this->assertTrue(Hash::check('VeryStrongPass123!', $admin->fresh()->password));
    }

    public function test_admin_keeps_settings_landing_but_is_redirected_from_legacy_account_settings_pages(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->get('/settings')->assertRedirect(route('electricity-billing.edit'));
        $this->get(route('profile.edit'))->assertRedirect(route('accounts.index'));
        $this->get(route('user-password.edit'))->assertRedirect(route('accounts.index'));
        $this->get(route('two-factor.show'))->assertRedirect(route('accounts.index'));
    }
}
