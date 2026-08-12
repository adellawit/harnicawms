<?php

namespace App\Http\Requests\Partner;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgentPksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File PKS wajib diunggah.',
            'file.mimes' => 'File PKS harus PDF, DOC, DOCX, JPG, atau PNG.',
            'file.max' => 'Ukuran file PKS maksimal 10 MB.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal mulai.',
        ];
    }
}
