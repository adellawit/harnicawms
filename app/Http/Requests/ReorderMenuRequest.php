<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'menus' => ['required', 'array', 'min:1'],
            'menus.*.id' => ['required', 'uuid'],
            'menus.*.children' => ['present', 'array'],
            'menus.*.children.*.id' => ['required', 'uuid'],
            'menus.*.children.*.children' => ['present', 'array'],
            'menus.*.children.*.children.*.id' => ['required', 'uuid'],
            'menus.*.children.*.children.*.children' => ['present', 'array', 'max:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'menus.required' => 'Menu order is required.',
            'menus.array' => 'Menu order must be a valid hierarchy.',
            'menus.min' => 'At least one menu is required.',
            'menus.*.id.uuid' => 'One or more menu IDs are invalid.',
            'menus.*.children.*.id.uuid' => 'One or more child menu IDs are invalid.',
            'menus.*.children.*.children.*.id.uuid' => 'One or more grandchild menu IDs are invalid.',
            'menus.*.children.*.children.*.children.max' => 'Menu hierarchy cannot exceed three levels.',
        ];
    }
}
