<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AppearanceSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_users_can_view_the_appearance_settings_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Appearance')
                ->where('interfaceLanguage', 'en')
            );
    }

    public function test_interface_language_cookie_is_shared_with_the_appearance_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withCookie('interface_language', 'ro')
            ->get(route('appearance.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Appearance')
                ->where('interfaceLanguage', 'ro')
            );
    }
}
