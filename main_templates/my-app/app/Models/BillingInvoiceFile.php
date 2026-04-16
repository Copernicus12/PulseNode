<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BillingInvoiceFile extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'billing_invoice_files';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_key',
        'owner_email',
        'billing_period',
        'billing_year',
        'billing_month',
        'original_name',
        'storage_path',
        'mime_type',
        'file_extension',
        'size_bytes',
    ];

    protected function casts(): array
    {
        return [
            'billing_year' => 'integer',
            'billing_month' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
