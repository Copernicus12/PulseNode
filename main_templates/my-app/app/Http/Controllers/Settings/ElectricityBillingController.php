<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ElectricityBillingProfileStoreRequest;
use App\Http\Requests\Settings\ElectricityBillingUpdateRequest;
use App\Models\BillingTariffProfile;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ElectricityBillingController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $pricePerWh = (float) ($user->electricity_price_per_wh ?? 0);
        $pricePerKwh = $pricePerWh * 1000;

        return Inertia::render('settings/ElectricityBilling', [
            'billingSettings' => [
                'electricity_price_per_kwh' => rtrim(rtrim(number_format($pricePerKwh, 6, '.', ''), '0'), '.'),
                'billing_currency' => $user->billing_currency ?? 'RON',
                'billing_tax_percent' => rtrim(rtrim(number_format((float) ($user->billing_tax_percent ?? 21), 2, '.', ''), '0'), '.'),
            ],
            'billingProfiles' => BillingTariffProfile::query()
                ->where('owner_key', $this->ownerKey($user))
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (BillingTariffProfile $profile) => [
                    'id' => (string) $profile->id,
                    'name' => (string) $profile->name,
                    'electricity_price_per_kwh' => rtrim(rtrim(number_format((float) $profile->electricity_price_per_kwh, 6, '.', ''), '0'), '.'),
                    'billing_currency' => (string) $profile->billing_currency,
                    'billing_tax_percent' => rtrim(rtrim(number_format((float) $profile->billing_tax_percent, 2, '.', ''), '0'), '.'),
                    'created_at' => optional($profile->created_at)?->toISOString(),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function update(ElectricityBillingUpdateRequest $request): RedirectResponse
    {
        $request->user()->forceFill([
            'electricity_price_per_wh' => ((float) $request->validated('electricity_price_per_kwh')) / 1000,
            'billing_currency' => strtoupper($request->validated('billing_currency')),
            'billing_tax_percent' => $request->validated('billing_tax_percent'),
        ])->save();

        return to_route('electricity-billing.edit');
    }

    public function storeProfile(ElectricityBillingProfileStoreRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        BillingTariffProfile::query()->create([
            'id' => (string) Str::ulid(),
            'owner_key' => $this->ownerKey($user),
            'owner_email' => (string) ($user?->email ?? ''),
            'name' => $data['name'],
            'electricity_price_per_kwh' => (float) $data['electricity_price_per_kwh'],
            'billing_currency' => strtoupper($data['billing_currency']),
            'billing_tax_percent' => (float) $data['billing_tax_percent'],
        ]);

        return to_route('electricity-billing.edit');
    }

    public function destroyProfile(Request $request, string $profileId): RedirectResponse
    {
        BillingTariffProfile::query()
            ->where('owner_key', $this->ownerKey($request->user()))
            ->where('id', $profileId)
            ->delete();

        return to_route('electricity-billing.edit');
    }

    private function ownerKey(?Authenticatable $user): string
    {
        return (string) ($user?->getAuthIdentifier() ?? '');
    }
}
