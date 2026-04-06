<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BillingInvoiceFolderDestroyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'folder_type' => ['required', 'string', Rule::in(['year', 'period'])],
            'folder_key' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $folderType = $this->input('folder_type');
            $folderKey = (string) $this->input('folder_key');

            if ($folderType === 'year' && ! preg_match('/^\d{4}$/', $folderKey)) {
                $validator->errors()->add('folder_key', 'The selected year folder is invalid.');
            }

            if ($folderType === 'period' && ! preg_match('/^\d{4}-\d{2}$/', $folderKey)) {
                $validator->errors()->add('folder_key', 'The selected month folder is invalid.');
            }
        });
    }
}
