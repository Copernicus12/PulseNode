<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingInvoiceFolder extends Model
{
    protected $table = 'billing_invoice_folders';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'owner_key',
        'owner_email',
        'folder_type',
        'folder_key',
        'folder_year',
        'folder_month',
    ];

    protected function casts(): array
    {
        return [
            'folder_year' => 'integer',
            'folder_month' => 'integer',
        ];
    }
}
