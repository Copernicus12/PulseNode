<?php

namespace App\Support;

use App\Models\BillingTariffProfile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;

class EnergyBillingCalculator
{
    /**
     * @param Collection<int, BillingTariffProfile> $profiles
     * @param array<string, mixed> $selectedDay
     */
    public function forDay(Authenticatable $user, array $selectedDay, Collection $profiles): array
    {
        $currency = strtoupper(trim((string) ($user->billing_currency ?? 'RON')));
        if ($currency === '') {
            $currency = 'RON';
        }

        $pricePerKwh = max(0.0, ((float) ($user->electricity_price_per_wh ?? 0)) * 1000);
        $taxPercent = max(0.0, (float) ($user->billing_tax_percent ?? 21));

        /** @var BillingTariffProfile|null $matchedProfile */
        $matchedProfile = $profiles->first(fn (BillingTariffProfile $profile) => $this->matchesCurrentTariff(
            $profile,
            $pricePerKwh,
            $currency,
            $taxPercent,
        ));

        $dayTotal = $this->costLine((float) ($selectedDay['total_kwh'] ?? 0), $pricePerKwh, $taxPercent);

        $socketCosts = collect($selectedDay['socket_stats'] ?? [])
            ->map(function (array $socket) use ($pricePerKwh, $taxPercent): array {
                return [
                    'name' => (string) ($socket['name'] ?? 'Socket'),
                    ...$this->costLine((float) ($socket['energy_kwh'] ?? 0), $pricePerKwh, $taxPercent),
                ];
            })
            ->values()
            ->all();

        return [
            'profile_name' => $matchedProfile?->name,
            'profile_label' => $matchedProfile?->name ?: 'Current settings',
            'profile_source' => $matchedProfile !== null ? 'saved_profile' : 'current_settings',
            'currency' => $currency,
            'price_per_kwh' => round($pricePerKwh, 6),
            'price_per_kwh_with_tax' => round($pricePerKwh * (1 + ($taxPercent / 100)), 6),
            'tax_percent' => round($taxPercent, 2),
            'day' => $dayTotal,
            'sockets' => $socketCosts,
        ];
    }

    private function matchesCurrentTariff(
        BillingTariffProfile $profile,
        float $pricePerKwh,
        string $currency,
        float $taxPercent,
    ): bool {
        return abs((float) $profile->electricity_price_per_kwh - $pricePerKwh) < 0.000001
            && strtoupper((string) $profile->billing_currency) === $currency
            && abs((float) $profile->billing_tax_percent - $taxPercent) < 0.000001;
    }

    /**
     * @return array<string, float>
     */
    private function costLine(float $energyKwh, float $pricePerKwh, float $taxPercent): array
    {
        $energyKwh = max(0.0, $energyKwh);
        $subtotal = $energyKwh * $pricePerKwh;
        $taxAmount = $subtotal * ($taxPercent / 100);
        $totalCost = $subtotal + $taxAmount;

        return [
            'energy_kwh' => round($energyKwh, 4),
            'subtotal' => round($subtotal, 4),
            'tax_amount' => round($taxAmount, 4),
            'total_cost' => round($totalCost, 4),
        ];
    }
}
