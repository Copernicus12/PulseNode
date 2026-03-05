<?php

namespace Tests\Feature;

use App\Models\DetectionPlan;
use App\Models\DeviceProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicesManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_devices_management_pages()
    {
        $this->get(route('devices.index'))->assertRedirect(route('login'));
        $this->post(route('devices.plans.store'), [])->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_create_detection_plans()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->post(route('devices.plans.store'), [
            'name' => 'Balanced Home',
            'strategy' => 'balanced',
            'socket_scope' => '',
            'window_samples' => 90,
            'min_samples' => 4,
            'match_threshold' => 70,
            'is_active' => '1',
            'notes' => 'Main household baseline.',
        ]);

        $response->assertRedirect(route('devices.index'));

        $this->assertDatabaseHas('detection_plans', [
            'name' => 'Balanced Home',
            'strategy' => 'balanced',
            'socket_scope' => null,
            'window_samples' => 90,
            'min_samples' => 4,
            'match_threshold' => 70,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_users_can_view_the_devices_management_page()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('devices.index'));

        $response->assertOk();
        $response->assertSee('Detection Plans');
        $response->assertSee('Profile Library');
    }

    public function test_activating_a_plan_deactivates_other_active_plans_from_the_same_scope()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $activePlan = DetectionPlan::factory()->active()->create([
            'socket_scope' => null,
            'name' => 'Old Global Plan',
        ]);

        $inactivePlan = DetectionPlan::factory()->create([
            'socket_scope' => null,
            'is_active' => false,
            'name' => 'New Global Plan',
        ]);

        $response = $this->post(route('devices.plans.activate', $inactivePlan));

        $response->assertRedirect(route('devices.index'));

        $this->assertDatabaseHas('detection_plans', [
            'id' => $activePlan->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('detection_plans', [
            'id' => $inactivePlan->id,
            'is_active' => true,
        ]);
    }

    public function test_authenticated_users_can_delete_device_profiles()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $profile = DeviceProfile::query()->create([
            'name' => 'Office Monitor',
            'category' => 'Display',
            'notes' => 'Used on weekdays.',
            'expected_power_min' => 15.0,
            'expected_power_max' => 95.0,
            'avg_power_w' => 42.0,
            'peak_power_w' => 78.0,
            'avg_current_a' => 0.182,
            'variability_pct' => 11.6,
            'startup_ratio' => 1.18,
            'signature_snapshot' => ['source' => 'test'],
            'trained_from_socket' => 2,
            'last_trained_at' => now(),
        ]);

        $response = $this->delete(route('devices.profiles.destroy', $profile));

        $response->assertRedirect(route('devices.index'));
        $this->assertDatabaseMissing('device_profiles', ['id' => $profile->id]);
    }
}
