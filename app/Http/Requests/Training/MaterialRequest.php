<?php

namespace App\Http\Requests\Training;

use App\Support\YouTube;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->input('type');
        // On create a file is required for pdf/image; on update (material already has a file) it is optional.
        $fileRequired = in_array($type, ['pdf', 'image'], true) && ! $this->route('material');

        return [
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['pdf', 'image', 'youtube'])],
            'estimated_minutes' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', $this->fileMimeRule($type)],
            'youtube_url' => [$type === 'youtube' ? 'required' : 'nullable', 'string', 'max:500', $this->youtubeRule($type)],
        ];
    }

    private function fileMimeRule(?string $type): string
    {
        return match ($type) {
            'pdf' => 'mimes:pdf',
            'image' => 'mimes:jpg,jpeg,png,webp',
            default => 'prohibited',
        };
    }

    private function youtubeRule(?string $type): \Closure
    {
        return function ($attr, $value, $fail) use ($type) {
            if ($type === 'youtube' && $value && ! YouTube::embedId($value)) {
                $fail('URL YouTube tidak dikenali.');
            }
        };
    }
}
