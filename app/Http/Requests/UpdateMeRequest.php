<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateMeRequest extends BaseApiRequest
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
            'avatar' => ['nullable', 'url', 'required_with:avatarS3Key'],
            'avatarS3Key' => ['nullable', 'string', 'max:512', 'required_with:avatar'],
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
            'avatar.url' => 'Avatar phải là URL hợp lệ',
            'avatar.required_with' => 'Avatar là bắt buộc khi gửi khóa ảnh S3',
            'avatarS3Key.required_with' => 'Khóa ảnh S3 là bắt buộc khi gửi avatar',
            'avatarS3Key.string' => 'Khóa ảnh S3 không hợp lệ',
            'avatarS3Key.max' => 'Khóa ảnh S3 không hợp lệ',
        ];
    }
}
