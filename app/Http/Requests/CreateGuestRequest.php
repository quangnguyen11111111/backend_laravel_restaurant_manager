<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class CreateGuestRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:256'],
            'tableNumber' => ['required', 'integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc',
            'name.min' => 'Tên phải có ít nhất 2 ký tự',
            'name.max' => 'Tên không được vượt quá 256 ký tự',
            'tableNumber.required' => 'Số bàn là bắt buộc',
            'tableNumber.integer' => 'Số bàn phải là số nguyên',
        ];
    }
}
