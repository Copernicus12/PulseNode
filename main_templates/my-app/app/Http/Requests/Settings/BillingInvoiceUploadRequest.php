<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class BillingInvoiceUploadRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'billing_period' => ['required', 'date_format:Y-m'],
            'files' => ['required', 'array', 'min:1', 'max:12'],
            'files.*' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }
}
