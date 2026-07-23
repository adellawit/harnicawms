<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class BulkResellerMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'agent_id' => ['nullable', 'uuid'],
            'action' => ['required', 'string', 'in:assign,unassign'],
            'reseller_ids' => ['required', 'array', 'min:1'],
            'reseller_ids.*' => ['uuid'],
        ];
    }
}
