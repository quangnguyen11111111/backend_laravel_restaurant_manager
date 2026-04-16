<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends BaseApiRequest
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
        $accountId = $this->route('id');

        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:256'],
            'email' => ['required', 'email', Rule::unique('accounts', 'email')->ignore($accountId)],
            'avatar' => ['nullable', 'url'],
            'changePassword' => ['nullable', 'boolean'],
            'role' => ['nullable', Rule::in(['Owner', 'Employee'])],
        ];

        // Conditional validation for password when changePassword is true
        if ($this->input('changePassword')) {
            $rules['password'] = ['required', 'string', 'min:6', 'max:100'];
            $rules['confirmPassword'] = ['required', 'string', 'same:password'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6', 'max:100'];
            $rules['confirmPassword'] = ['nullable', 'string', 'same:password'];
        }

        return $rules;
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
            'email.required' => 'Email là bắt buộc',
            'email.email' => 'Email không hợp lệ',
            'email.unique' => 'Email đã tồn tại',
            'avatar.url' => 'Avatar phải là URL hợp lệ',
            'password.required' => 'Hãy nhập mật khẩu mới và xác nhận mật khẩu mới',
            'password.min' => 'Mật khẩu phải có ít nhất 6 ký tự',
            'password.max' => 'Mật khẩu không được vượt quá 100 ký tự',
            'confirmPassword.required' => 'Hãy nhập mật khẩu mới và xác nhận mật khẩu mới',
            'confirmPassword.same' => 'Mật khẩu không khớp',
            'role.in' => 'Role không hợp lệ',
        ];
    }
}
