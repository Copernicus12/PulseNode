<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatteryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('battery.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_battery_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('battery.index'));

        $response->assertOk();
        $response->assertSeeText([
            'Battery overview at a glance.',
            'What to do next',
            'How to read the score',
            'Socket overview',
        ]);
    }
}
