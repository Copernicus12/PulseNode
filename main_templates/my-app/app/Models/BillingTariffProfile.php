<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BillingTariffProfile extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'billing_tariff_profiles';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_key',
        'owner_email',
        'name',
        'electricity_price_per_kwh',
        'billing_currency',
        'billing_tax_percent',
    ];

    protected function casts(): array
    {
        return [
            'electricity_price_per_kwh' => 'float',
            'billing_tax_percent' => 'float',
        ];
    }
}
