<?php

namespace App\Http\Requests\Settings;

use App\Models\BillingInvoiceFile;
use App\Models\BillingInvoiceFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BillingInvoiceFolderStoreRequest extends FormRequest
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
            $ownerKey = (string) ($this->user()?->getAuthIdentifier() ?? '');

            if ($folderType === 'year' && ! preg_match('/^\d{4}$/', $folderKey)) {
                $validator->errors()->add('folder_key', 'Choose a valid year folder.');

                return;
            }

            if ($folderType === 'period' && ! preg_match('/^\d{4}-\d{2}$/', $folderKey)) {
                $validator->errors()->add('folder_key', 'Choose a valid billing month folder.');

                return;
            }

            $folderExists = BillingInvoiceFolder::query()
                ->where('owner_key', $ownerKey)
                ->where('folder_type', $folderType)
                ->where('folder_key', $folderKey)
                ->exists();

            if ($folderExists) {
                $validator->errors()->add('folder_key', 'This folder already exists.');

                return;
            }

            $fileExists = BillingInvoiceFile::query()
                ->where('owner_key', $ownerKey)
                ->when(
                    $folderType === 'year',
                    fn ($query) => $query->where('billing_year', (int) $folderKey),
                    fn ($query) => $query->where('billing_period', $folderKey),
                )
                ->exists();

            if ($fileExists) {
                $validator->errors()->add('folder_key', 'This folder already exists.');
            }
        });
    }
}
