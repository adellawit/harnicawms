<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FgBarcodeStockReportRequest extends FormRequest
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
            'warehouse_id' => ['nullable', 'uuid'],
            'product_id' => ['nullable', 'uuid'],
            'variant_id' => ['nullable', 'uuid'],
            'unit_id' => ['nullable', 'uuid'],
            'serial' => ['nullable', 'string', 'max:20'],
            'mismatch_only' => ['nullable', 'boolean'],
            'null_variant' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'in:10,20,50,100'],
            'export' => ['nullable', 'string', 'in:summary,serials'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('mismatch_only')) {
            $this->merge([
                'mismatch_only' => filter_var($this->input('mismatch_only'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        if ($this->has('null_variant')) {
            $this->merge([
                'null_variant' => filter_var($this->input('null_variant'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
