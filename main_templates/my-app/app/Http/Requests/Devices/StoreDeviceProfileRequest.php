<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'socket_index' => ['required', 'integer', 'in:1,2,3'],
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'category' => ['required', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'socket_index.in' => 'Socket-ul selectat trebuie sa fie 1, 2 sau 3.',
            'name.required' => 'Numele profilului este obligatoriu.',
            'category.required' => 'Categoria profilului este obligatorie.',
        ];
    }
}
