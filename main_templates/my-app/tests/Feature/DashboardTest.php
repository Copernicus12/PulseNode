<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create([
            'electricity_price_per_wh' => 0.00142,
            'billing_currency' => 'RON',
            'billing_tax_percent' => 21,
        ]);
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response
            ->assertOk()
            ->assertSeeText('Today Cost')
            ->assertSeeText('Active tariff')
            ->assertDontSeeText('Hardware specifications')
            ->assertDontSeeText('JSON Payload');
    }
}
