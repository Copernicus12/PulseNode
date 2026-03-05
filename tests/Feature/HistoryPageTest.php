<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $response = $this->get(route('history.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_history_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('history.index'));

        $response->assertOk();
        $response->assertSeeText([
            'Consumption history for analysis and decisions.',
            'Weekly trend',
            'Socket contribution',
            'Ideas to complete the thesis',
        ]);
    }
}
