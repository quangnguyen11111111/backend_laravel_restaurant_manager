<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class ChangePasswordRequest extends BaseApiRequest
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
            'oldPassword' => ['required', 'string', 'min:6', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:100'],
            'confirmPassword' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'oldPassword.required' => 'Mật khẩu cũ là bắt buộc',
            'oldPassword.min' => 'Mật khẩu cũ phải có ít nhất 6 ký tự',
            'oldPassword.max' => 'Mật khẩu cũ không được vượt quá 100 ký tự',
            'password.required' => 'Mật khẩu mới là bắt buộc',
            'password.min' => 'Mật khẩu mới phải có ít nhất 6 ký tự',
            'password.max' => 'Mật khẩu mới không được vượt quá 100 ký tự',
            'confirmPassword.required' => 'Xác nhận mật khẩu là bắt buộc',
            'confirmPassword.same' => 'Mật khẩu mới không khớp',
        ];
    }
}
