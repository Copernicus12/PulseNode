<?php

namespace App\Http\Requests\PowerStrip;

use Illuminate\Foundation\Http\FormRequest;

class StorePowerStripGuardPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'enabled' => ['sometimes', 'boolean'],
            'scope_mode' => ['required', 'string', 'in:common,per_socket'],
            'common_threshold_amps' => ['required_if:scope_mode,common', 'nullable', 'numeric', 'min:0.1', 'max:100'],
            'socket_threshold_amps_1' => ['required_if:scope_mode,per_socket', 'nullable', 'numeric', 'min:0.1', 'max:100'],
            'socket_threshold_amps_2' => ['required_if:scope_mode,per_socket', 'nullable', 'numeric', 'min:0.1', 'max:100'],
            'socket_threshold_amps_3' => ['required_if:scope_mode,per_socket', 'nullable', 'numeric', 'min:0.1', 'max:100'],
            'action' => ['required', 'string', 'in:off-1,off-2,off-3,off-all'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'has_end_date' => ['sometimes', 'boolean'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
