<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScanSalesOrderBarcodeRequest extends FormRequest
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
            'sales_order_item_id' => ['nullable', 'uuid'],
            'serial_number' => ['required', 'string', 'max:20'],
        ];
    }
}
