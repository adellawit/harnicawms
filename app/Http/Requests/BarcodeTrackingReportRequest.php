<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BarcodeTrackingReportRequest extends FormRequest
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
            'branch_id' => ['nullable', 'uuid'],
            'agent_id' => ['nullable', 'uuid'],
            'customer_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'variant_id' => ['nullable', 'uuid'],
            'unit_id' => ['nullable', 'uuid'],
            'serial' => ['nullable', 'string', 'max:20'],
            'sales_number' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
        ];
    }
}
