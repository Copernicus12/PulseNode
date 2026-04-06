<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BillingInvoiceFolderUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'folder_type' => ['required', 'string', Rule::in(['year', 'period'])],
            'folder_key' => ['required', 'string'],
            'target_year' => ['nullable', 'string', 'regex:/^\d{4}$/'],
            'target_period' => ['nullable', 'date_format:Y-m'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $folderType = $this->input('folder_type');
            $folderKey = (string) $this->input('folder_key');

            if ($folderType === 'year') {
                if (! preg_match('/^\d{4}$/', $folderKey)) {
                    $validator->errors()->add('folder_key', 'The selected year folder is invalid.');
                }

                if (! $this->filled('target_year')) {
                    $validator->errors()->add('target_year', 'Choose the destination year.');
                }
            }

            if ($folderType === 'period') {
                if (! preg_match('/^\d{4}-\d{2}$/', $folderKey)) {
                    $validator->errors()->add('folder_key', 'The selected month folder is invalid.');
                }

                if (! $this->filled('target_period')) {
                    $validator->errors()->add('target_period', 'Choose the destination month.');
                }
            }
        });
    }
}
