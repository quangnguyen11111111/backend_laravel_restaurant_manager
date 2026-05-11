<?php

namespace App\Http\Requests;

class GuestLoginRequest extends BaseApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:256'],
            'tableNumber' => ['required', 'integer'],
            'token' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc',
            'name.min' => 'Tên phải có ít nhất 2 ký tự',
            'name.max' => 'Tên không được vượt quá 256 ký tự',
            'tableNumber.required' => 'Số bàn là bắt buộc',
            'tableNumber.integer' => 'Số bàn phải là số nguyên',
            'token.required' => 'Mã token là bắt buộc',
        ];
    }
}
