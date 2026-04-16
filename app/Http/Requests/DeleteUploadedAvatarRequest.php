<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class DeleteUploadedAvatarRequest extends BaseApiRequest
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
            'avatarS3Key' => ['required', 'string', 'max:512'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'avatarS3Key.required' => 'Thiếu khóa ảnh S3 cần xóa',
            'avatarS3Key.string' => 'Khóa ảnh S3 không hợp lệ',
            'avatarS3Key.max' => 'Khóa ảnh S3 không hợp lệ',
        ];
    }
}
