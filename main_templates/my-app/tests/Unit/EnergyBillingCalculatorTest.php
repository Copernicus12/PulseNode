<?php

namespace Tests\Unit;

use App\Models\BillingTariffProfile;
use App\Models\User;
use App\Support\EnergyBillingCalculator;
use Tests\TestCase;

class EnergyBillingCalculatorTest extends TestCase
{
    public function test_it_builds_daily_costs_from_the_active_saved_tariff_profile(): void
    {
        $calculator = new EnergyBillingCalculator();
        $user = new User([
            'electricity_price_per_wh' => 0.00102,
            'billing_currency' => 'RON',
            'billing_tax_percent' => 19,
        ]);

        $summary = $calculator->forDay($user, [
            'total_kwh' => 3.75,
            'socket_stats' => [
                ['name' => 'Socket 1', 'energy_kwh' => 1.5],
                ['name' => 'Socket 2', 'energy_kwh' => 1.25],
                ['name' => 'Socket 3', 'energy_kwh' => 1.0],
            ],
        ], collect([
            new BillingTariffProfile([
                'name' => 'Tarif casnic',
                'electricity_price_per_kwh' => 1.02,
                'billing_currency' => 'RON',
                'billing_tax_percent' => 19,
            ]),
        ]));

        $this->assertSame('Tarif casnic', $summary['profile_name']);
        $this->assertSame('saved_profile', $summary['profile_source']);
        $this->assertSame('RON', $summary['currency']);
        $this->assertSame(1.02, $summary['price_per_kwh']);
        $this->assertSame(1.2138, $summary['price_per_kwh_with_tax']);
        $this->assertSame(3.825, $summary['day']['subtotal']);
        $this->assertSame(0.7268, $summary['day']['tax_amount']);
        $this->assertSame(4.5518, $summary['day']['total_cost']);
        $this->assertCount(3, $summary['sockets']);
        $this->assertSame(1.8207, $summary['sockets'][0]['total_cost']);
        $this->assertSame(1.5173, $summary['sockets'][1]['total_cost']);
        $this->assertSame(1.2138, $summary['sockets'][2]['total_cost']);
    }

    public function test_it_falls_back_to_current_settings_when_no_saved_profile_matches(): void
    {
        $calculator = new EnergyBillingCalculator();
        $user = new User([
            'electricity_price_per_wh' => 0.0008,
            'billing_currency' => 'EUR',
            'billing_tax_percent' => 21,
        ]);

        $summary = $calculator->forDay($user, [
            'total_kwh' => 0.5,
            'socket_stats' => [
                ['name' => 'Socket 1', 'energy_kwh' => 0.2],
                ['name' => 'Socket 2', 'energy_kwh' => 0.2],
                ['name' => 'Socket 3', 'energy_kwh' => 0.1],
            ],
        ], collect([
            new BillingTariffProfile([
                'name' => 'Alt profil',
                'electricity_price_per_kwh' => 0.92,
                'billing_currency' => 'EUR',
                'billing_tax_percent' => 21,
            ]),
        ]));

        $this->assertNull($summary['profile_name']);
        $this->assertSame('Current settings', $summary['profile_label']);
        $this->assertSame('current_settings', $summary['profile_source']);
        $this->assertSame(0.8, $summary['price_per_kwh']);
        $this->assertSame(0.968, $summary['price_per_kwh_with_tax']);
        $this->assertSame(0.4, $summary['day']['subtotal']);
        $this->assertSame(0.084, $summary['day']['tax_amount']);
        $this->assertSame(0.484, $summary['day']['total_cost']);
    }
}
