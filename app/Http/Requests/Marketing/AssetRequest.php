<?php

namespace App\Http\Requests\Marketing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        $fileRequired = in_array($type, ['image', 'pdf'], true) && ! $this->route('id');

        return [
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'string', Rule::exists('pgsql.marketing.asset_categories', 'id')],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['image', 'video', 'pdf', 'text'])],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', $this->fileMimeRule($type)],
            'link_url' => [$type === 'video' ? 'required' : 'nullable', 'string', 'max:500', 'url'],
            'body_text' => [$type === 'text' ? 'required' : 'nullable', 'string'],
            'usable_in_marketing' => ['nullable', 'boolean'],
            'usable_in_training' => ['nullable', 'boolean'],
            'can_be_thumbnail' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['draft', 'active'])],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function fileMimeRule(?string $type): string
    {
        return match ($type) {
            'image' => 'mimes:jpg,jpeg,png,webp',
            'pdf' => 'mimes:pdf',
            default => 'prohibited',
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $type = $this->input('type');
            $inMarketing = $this->boolean('usable_in_marketing');
            $inTraining = $this->boolean('usable_in_training');

            if (! $inMarketing && ! $inTraining) {
                $v->errors()->add('usable_in_marketing', 'Pilih minimal satu scope (Marketing atau Training).');
            }
            if ($type === 'text' && $inTraining) {
                $v->errors()->add('usable_in_training', 'Teks WA tidak bisa dipakai di Training.');
            }
            if ($this->boolean('can_be_thumbnail') && $type !== 'image') {
                $v->errors()->add('can_be_thumbnail', 'Hanya aset gambar yang bisa dijadikan thumbnail.');
            }
        });
    }
}
