<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgentCuttingPriceReportRequest extends FormRequest
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
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
            'agent_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'variant_id' => ['nullable', 'uuid'],
            'branch_id' => ['nullable', 'uuid'],
            'min_gap_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'export' => ['nullable', 'in:summary,detail'],
        ];
    }
}
