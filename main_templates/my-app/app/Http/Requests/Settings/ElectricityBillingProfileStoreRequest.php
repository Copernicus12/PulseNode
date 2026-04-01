<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ElectricityBillingProfileStoreRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'electricity_price_per_kwh' => ['required', 'numeric', 'min:0', 'max:999999.999999'],
            'billing_currency' => ['required', 'string', 'size:3', Rule::in(['RON', 'EUR', 'USD', 'GBP', 'CHF', 'HUF'])],
            'billing_tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
