<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateResellerMappingRequest extends FormRequest
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
            'action' => ['nullable', 'string', 'in:assign,unassign'],
        ];
    }
}
