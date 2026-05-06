<?php

namespace App\Http\Requests\Devices;

use Illuminate\Foundation\Http\FormRequest;

class StoreSocketScheduleRequest extends FormRequest
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
            'action' => ['required', 'string', 'in:on,off'],
            'days_of_week' => ['required', 'array', 'min:1', 'max:7'],
            'days_of_week.*' => ['required', 'string', 'in:mon,tue,wed,thu,fri,sat,sun'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
