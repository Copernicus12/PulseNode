<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $this->partialMock(NotificationCenter::class, function ($mock): void {
            $mock->shouldReceive('accountRequestSubmitted')->once();
        });

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'role' => User::ROLE_MODERATOR,
            'account_status' => User::ACCOUNT_STATUS_PENDING,
        ]);
    }
}
