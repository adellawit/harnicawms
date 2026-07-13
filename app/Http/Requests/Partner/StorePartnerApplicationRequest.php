<?php

namespace App\Http\Requests\Partner;

use App\Http\Requests\Partner\Concerns\ValidatesPartnerApplication;
use App\Models\Partner\PartnerApplication;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePartnerApplicationRequest extends FormRequest
{
    use ValidatesPartnerApplication;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'filled_at' => now()->toDateString(),
        ]);

        if (Auth::user()?->partnerAgent) {
            $this->merge(['partner_type' => PartnerApplication::TYPE_RESELLER]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->partnerApplicationRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->partnerApplicationMessages();
    }

    public function withValidator($validator): void
    {
        $this->withPartnerApplicationValidator($validator);
    }
}
