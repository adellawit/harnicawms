<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'promotion_id' => ['nullable', 'uuid', Rule::exists('pgsql.product.promotions', 'id')],
            'reactivates_reseller' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'string'],
            'ends_at' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0'],
            'banner' => ['nullable', 'image', 'max:4096'],
        ];
    }
}
