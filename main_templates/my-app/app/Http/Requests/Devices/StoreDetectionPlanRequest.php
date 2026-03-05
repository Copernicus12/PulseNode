<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class StoreDetectionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'strategy' => ['required', 'string', 'in:fast,balanced,strict'],
            'socket_scope' => ['nullable', 'integer', 'in:1,2,3'],
            'window_samples' => ['required', 'integer', 'min:30', 'max:240'],
            'min_samples' => ['required', 'integer', 'min:2', 'max:30'],
            'match_threshold' => ['required', 'integer', 'min:40', 'max:95'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'strategy.in' => 'Strategia trebuie sa fie fast, balanced sau strict.',
            'socket_scope.in' => 'Socket scope trebuie sa fie 1, 2, 3 sau gol pentru toate socket-urile.',
        ];
    }
}
