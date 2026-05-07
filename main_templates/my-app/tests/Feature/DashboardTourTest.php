<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_mark_the_dashboard_tour_as_completed(): void
    {
        $user = User::factory()->create([
            'dashboard_tour_completed_at' => null,
        ]);

        $response = $this->actingAs($user)->post(route('dashboard.tour.complete'));

        $response->assertNoContent();

        $this->assertNotNull($user->fresh()->dashboard_tour_completed_at);
    }
}
